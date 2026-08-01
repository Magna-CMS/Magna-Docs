<?php

declare(strict_types=1);

namespace Magna\Docs\Transfer;

use Illuminate\Support\Facades\Storage;
use Magna\Docs\Models\DocCollection;
use Magna\Docs\Settings\DocsSettings;

/**
 * Restores a docs archive produced by DocsExporter.
 *
 * Order matters and is the whole design:
 *
 *   1. media      — files are re-ingested first, so pages can be rewritten to
 *                   point at their new locations as they are written
 *   2. collections— so every page has a container to attach to
 *   3. pages      — streamed and batch-inserted, parent-less for now
 *   4. parents    — resolved once every page has an id (ParentLinker)
 *   5. settings   — optional branding, media paths already remapped
 *
 * There is deliberately no single wrapping transaction. A 1000-page import
 * would hold locks for its entire duration and unwind everything for one bad
 * row; instead each pass is incremental and the whole import is idempotent
 * (keyed on slug), so a failed or partial run can simply be re-run with the
 * "update existing" strategy. Run a dry run first to see exactly what an
 * archive would do before it does it.
 */
final class DocsImporter
{
    public function __construct(private readonly MediaRestorer $mediaRestorer) {}

    /** Import an archive stored on a Laravel disk (the admin upload path). */
    public function import(string $disk, string $path, ImportOptions $options): ImportReport
    {
        return $this->importArchive(Storage::disk($disk)->path($path), $options);
    }

    /** Import an archive by absolute filesystem path (the CLI path). */
    public function importArchive(string $absolutePath, ImportOptions $options): ImportReport
    {
        $archive = ArchiveReader::open($absolutePath);
        $report = new ImportReport($options->dryRun);

        try {
            $media = $options->importMedia
                ? $this->mediaRestorer->restore($archive, $report, $options->dryRun)
                : $this->emptyMediaMap($archive);

            $collections = (new CollectionImporter($options, $report))->import($archive);

            $pages = new PageImporter(
                $options,
                $report,
                $media,
                new TranslationImporter($options, $report, $media),
                $collections,
            );
            $pages->import($archive);

            (new ParentLinker($report))->link(
                $pages->links(),
                $pages->map(),
                $pages->existing(),
                $options->dryRun,
            );

            if (! $options->dryRun) {
                // Collections were inserted flat (see CollectionImporter); this
                // is what gives them gapless, unique ordering again.
                DocCollection::resequence();
            }

            if ($options->importSettings) {
                $this->applySettings($archive, $media, $options->dryRun);
            }
        } finally {
            $archive->close();
        }

        return $report;
    }

    /**
     * Manifest-level summary of an archive without importing anything — used by
     * the admin screen to show what an upload contains before it is applied.
     *
     * @return array{format: string, version: int, exported_at: string, app_name: string, counts: array<string, int>}
     */
    public function inspect(string $disk, string $path): array
    {
        $archive = ArchiveReader::open(Storage::disk($disk)->path($path));

        try {
            $manifest = $archive->manifest();
            $generator = $manifest['generator'] ?? [];
            $generator = is_array($generator) ? $generator : [];

            return [
                'format' => (string) ($manifest['format'] ?? ''),
                'version' => (int) ($manifest['version'] ?? 0),
                'exported_at' => (string) ($manifest['exported_at'] ?? ''),
                'app_name' => (string) ($generator['app_name'] ?? ''),
                'counts' => $archive->declaredCounts(),
            ];
        } finally {
            $archive->close();
        }
    }

    /** Media references are still rewritten between URL shapes even when files are not restored. */
    private function emptyMediaMap(ArchiveReader $archive): MediaMap
    {
        $prefixes = $archive->sourcePrefixes();

        return new MediaMap(
            sourceUrlPrefix: $prefixes['url'],
            sourcePathPrefix: $prefixes['path'],
            targetUrlPrefix: MediaReferences::storageUrlPrefix(),
            targetPathPrefix: MediaReferences::storagePathPrefix(),
        );
    }

    private function applySettings(ArchiveReader $archive, MediaMap $media, bool $dryRun): void
    {
        $values = $archive->json(DocsArchive::SETTINGS);

        if ($values === null || $dryRun) {
            return;
        }

        $settings = DocsSettings::get();
        $settings->site_name = trim((string) ($values['site_name'] ?? $settings->site_name));
        $settings->copyright_text = trim((string) ($values['copyright_text'] ?? $settings->copyright_text));
        $settings->logo_path = (string) ($media->path($this->stringOrNull($values['logo_path'] ?? null)) ?? '');
        $settings->favicon_path = (string) ($media->path($this->stringOrNull($values['favicon_path'] ?? null)) ?? '');
        $settings->save();
    }

    private function stringOrNull(mixed $value): ?string
    {
        $value = is_scalar($value) ? trim((string) $value) : '';

        return $value === '' ? null : $value;
    }
}
