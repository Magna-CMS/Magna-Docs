<?php

declare(strict_types=1);

namespace Magna\Docs\Transfer;

use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Magna\Docs\Models\DocCollection;
use Magna\Docs\Models\DocPage;
use Magna\Docs\Settings\DocsSettings;
use RuntimeException;

/**
 * Writes a complete, portable snapshot of the docs site to a single .zip.
 *
 * Scale is the design constraint: an install with hundreds of collections and
 * thousands of pages must export without loading the corpus into memory. Every
 * table is streamed with lazyById() straight into an NDJSON file, media is
 * added to the zip by path (never read into PHP), and the archive is flushed
 * periodically so descriptors stay bounded — see ZipWriter.
 *
 * Records reference each other by slug, so the archive is portable across
 * installs. See DocsArchive for the layout, DocsImporter for the other half.
 */
final class DocsExporter
{
    /** Archives are private — they contain unpublished drafts. */
    public const DISK = 'local';

    public const DIRECTORY = 'magna-docs-exports';

    /** Generated archives are cleaned up after this long, on the next export. */
    private const RETENTION_HOURS = 24;

    public function export(ExportOptions $options): ExportResult
    {
        $disk = Storage::disk(self::DISK);
        $disk->makeDirectory(self::DIRECTORY);
        $this->pruneOldArchives();

        $work = self::DIRECTORY.'/tmp-'.Str::random(12);
        $disk->makeDirectory($work);
        $workPath = $disk->path($work);

        try {
            $media = new MediaExporter(
                MediaReferences::storageUrlPrefix(),
                MediaReferences::storagePathPrefix(),
            );

            $collections = $this->writeCollections($workPath.'/'.DocsArchive::COLLECTIONS, $options);
            $pageCounts = $this->writePages($workPath.'/'.DocsArchive::PAGES, $options, $media);

            $settings = $options->includeSettings ? $this->settingsSnapshot($media) : null;
            $mediaEntries = $options->includeMedia ? $media->entries() : [];
            $this->writeMediaIndex($workPath.'/'.DocsArchive::MEDIA_INDEX, $mediaEntries);

            $counts = [
                'collections' => $collections,
                'pages' => $pageCounts['pages'],
                'translations' => $pageCounts['translations'],
                'media' => count($mediaEntries),
            ];

            $filename = 'magna-docs-'.now()->format('Ymd-His').'.zip';
            $archivePath = self::DIRECTORY.'/'.$filename;

            $this->buildArchive($disk->path($archivePath), $workPath, $mediaEntries, $settings, $counts, $options);

            return new ExportResult(
                disk: self::DISK,
                path: $archivePath,
                filename: $filename,
                bytes: (int) ($disk->size($archivePath) ?: 0),
                counts: $counts,
            );
        } finally {
            $disk->deleteDirectory($work);
        }
    }

    // ── Archive assembly ─────────────────────────────────────────────────────

    /**
     * @param  list<array{path: string, file: string, bytes: int, sha256: string, absolute: string}>  $mediaEntries
     * @param  array<string, mixed>|null  $settings
     * @param  array<string, int>  $counts
     */
    private function buildArchive(
        string $archiveAbsolutePath,
        string $workPath,
        array $mediaEntries,
        ?array $settings,
        array $counts,
        ExportOptions $options,
    ): void {
        $zip = new ZipWriter($archiveAbsolutePath);

        $zip->addString(DocsArchive::MANIFEST, $this->encode(
            $this->manifest($workPath, $counts, $options),
            pretty: true,
        ));

        $zip->addFile($workPath.'/'.DocsArchive::COLLECTIONS, DocsArchive::COLLECTIONS);
        $zip->addFile($workPath.'/'.DocsArchive::PAGES, DocsArchive::PAGES);
        $zip->addFile($workPath.'/'.DocsArchive::MEDIA_INDEX, DocsArchive::MEDIA_INDEX);

        if ($settings !== null) {
            $zip->addString(DocsArchive::SETTINGS, $this->encode($settings, pretty: true));
        }

        foreach ($mediaEntries as $entry) {
            $zip->addFile($entry['absolute'], $entry['file'], store: true);
        }

        $zip->finish();
    }

    /**
     * @param  array<string, int>  $counts
     * @return array<string, mixed>
     */
    private function manifest(string $workPath, array $counts, ExportOptions $options): array
    {
        return [
            'format' => DocsArchive::FORMAT,
            'version' => DocsArchive::VERSION,
            'exported_at' => now()->toIso8601String(),
            'generator' => [
                'plugin' => 'magna/docs',
                'app_name' => (string) config('app.name'),
                'app_url' => (string) config('app.url'),
            ],
            // How media URLs looked on the source install. The importer rewrites
            // both shapes to wherever the files land locally.
            'source' => [
                'storage_url_prefix' => MediaReferences::storageUrlPrefix(),
                'storage_path_prefix' => MediaReferences::storagePathPrefix(),
            ],
            'options' => [
                'collections' => $options->collections,
                'include_drafts' => $options->includeDrafts,
                'include_media' => $options->includeMedia,
                'include_settings' => $options->includeSettings,
            ],
            'counts' => $counts,
            'checksums' => [
                DocsArchive::COLLECTIONS => 'sha256:'.hash_file('sha256', $workPath.'/'.DocsArchive::COLLECTIONS),
                DocsArchive::PAGES => 'sha256:'.hash_file('sha256', $workPath.'/'.DocsArchive::PAGES),
                DocsArchive::MEDIA_INDEX => 'sha256:'.hash_file('sha256', $workPath.'/'.DocsArchive::MEDIA_INDEX),
            ],
        ];
    }

    // ── Streamed writers ─────────────────────────────────────────────────────

    private function writeCollections(string $file, ExportOptions $options): int
    {
        $handle = $this->openWrite($file);
        $count = 0;

        $query = DocCollection::query()->orderBy('order')->orderBy('id');

        if ($options->isScoped()) {
            $query->whereIn('slug', $options->collections);
        }

        foreach ($query->lazyById(DocsArchive::BATCH) as $collection) {
            /** @var DocCollection $collection */
            fwrite($handle, $this->line([
                'slug' => (string) $collection->slug,
                'title' => (string) $collection->title,
                'description' => $collection->description,
                'icon' => $collection->icon,
                'color' => $collection->color,
                'order' => (int) $collection->order,
                'is_public' => (bool) $collection->is_public,
            ]));
            $count++;
        }

        fclose($handle);

        return $count;
    }

    /**
     * @return array{pages: int, translations: int}
     */
    private function writePages(string $file, ExportOptions $options, MediaExporter $media): array
    {
        $handle = $this->openWrite($file);
        $pages = 0;
        $translations = 0;

        $query = DocPage::query()
            ->with(['collection:id,slug', 'parent:id,slug', 'translations'])
            ->orderBy('id');

        if ($options->isScoped()) {
            $query->whereIn('collection_id', DocCollection::query()
                ->whereIn('slug', $options->collections)
                ->pluck('id'));
        }

        if (! $options->includeDrafts) {
            $query->where('status', 'published');
        }

        foreach ($query->lazyById(DocsArchive::BATCH) as $page) {
            /** @var DocPage $page */
            $media->noteStoredPath($page->featured_image);
            $media->noteContent($page->content);

            $rows = [];
            foreach ($page->translations as $translation) {
                $rows[] = [
                    'locale' => (string) $translation->locale,
                    'title' => (string) $translation->title,
                    'content' => (string) $translation->content,
                ];
                $translations++;
            }

            fwrite($handle, $this->line([
                'slug' => (string) $page->slug,
                'title' => (string) $page->title,
                'collection' => $page->collection?->slug,
                'parent' => $page->parent?->slug,
                'order' => (int) $page->order,
                'status' => (string) $page->status,
                'is_published' => (bool) $page->is_published,
                'published_at' => $page->published_at?->toIso8601String(),
                'excerpt' => $page->excerpt,
                'meta_title' => $page->meta_title,
                'meta_description' => $page->meta_description,
                'featured_image' => $page->featured_image,
                'show_featured_image' => (bool) $page->show_featured_image,
                'content' => (string) $page->content,
                'created_at' => $page->created_at?->toIso8601String(),
                'updated_at' => $page->updated_at?->toIso8601String(),
                'translations' => $rows,
            ]));

            $pages++;
        }

        fclose($handle);

        return ['pages' => $pages, 'translations' => $translations];
    }

    /**
     * @param  list<array{path: string, file: string, bytes: int, sha256: string, absolute: string}>  $entries
     */
    private function writeMediaIndex(string $file, array $entries): void
    {
        $handle = $this->openWrite($file);

        foreach ($entries as $entry) {
            fwrite($handle, $this->line([
                'path' => $entry['path'],
                'file' => $entry['file'],
                'bytes' => $entry['bytes'],
                'sha256' => $entry['sha256'],
            ]));
        }

        fclose($handle);
    }

    /**
     * Branding values worth carrying between installs. `custom_domain` and
     * `editor_roles` are deliberately excluded: a domain belongs to the host it
     * was configured on, and role handles are per-install.
     *
     * @return array<string, mixed>
     */
    private function settingsSnapshot(MediaExporter $media): array
    {
        $settings = DocsSettings::get();

        $media->noteStoredPath($settings->logo_path);
        $media->noteStoredPath($settings->favicon_path);

        return [
            'site_name' => $settings->site_name,
            'logo_path' => $settings->logo_path,
            'favicon_path' => $settings->favicon_path,
            'copyright_text' => $settings->copyright_text,
        ];
    }

    // ── Helpers ──────────────────────────────────────────────────────────────

    /** @return resource */
    private function openWrite(string $file)
    {
        $handle = fopen($file, 'wb');

        if ($handle === false) {
            throw new RuntimeException("Could not open {$file} for writing during export.");
        }

        return $handle;
    }

    /** @param array<string, mixed> $row */
    private function line(array $row): string
    {
        return $this->encode($row)."\n";
    }

    /** @param array<string, mixed> $value */
    private function encode(array $value, bool $pretty = false): string
    {
        $flags = JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE;

        if ($pretty) {
            $flags |= JSON_PRETTY_PRINT;
        }

        return (string) json_encode($value, $flags);
    }

    /** Delete archives older than the retention window, so exports don't pile up. */
    private function pruneOldArchives(): void
    {
        $disk = Storage::disk(self::DISK);
        $cutoff = now()->subHours(self::RETENTION_HOURS)->getTimestamp();

        foreach ($disk->files(self::DIRECTORY) as $file) {
            if (! str_ends_with($file, '.zip')) {
                continue;
            }

            if ($disk->lastModified($file) < $cutoff) {
                $disk->delete($file);
            }
        }
    }
}
