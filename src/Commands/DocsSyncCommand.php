<?php

declare(strict_types=1);

namespace Magna\Docs\Commands;

use Illuminate\Console\Command;
use Magna\Docs\Models\DocCollection;
use Magna\Docs\Models\DocPage;

/**
 * Synchronise canonical Markdown documentation (the SDK's docs, the source of
 * truth) into the Magna Docs database so the rendered site stays current.
 *
 * Structure: each immediate subdirectory of the source is a collection; the
 * markdown files inside it are its pages. A numeric prefix on a directory
 * (01-getting-started) sets the collection's order; a numeric prefix on a file
 * (20-installation.md) sets the page's order. Both prefixes are stripped from
 * slugs so URLs stay clean. Top-level markdown files (no subdirectory) fall back
 * to the collection named by --collection.
 *
 * Idempotent and non-destructive: pages are matched by their unique slug, so
 * re-running only updates changed content and never duplicates a page or
 * rewrites its URL. Collections are created once then preserved.
 */
class DocsSyncCommand extends Command
{
    protected $signature = 'magna:docs:sync
        {--source= : Markdown source directory (defaults to the sibling magna-plugin-sdk/docs)}
        {--collection=sdk : Collection slug for top-level (non-subdirectory) markdown files}
        {--dry-run : Report what would change without writing}';

    protected $description = 'Sync canonical Markdown docs into the Magna Docs site.';

    public function handle(): int
    {
        $source = $this->resolveSource();
        if (! is_dir($source)) {
            $this->error("Markdown source directory not found: {$source}");

            return self::FAILURE;
        }

        $dryRun = (bool) $this->option('dry-run');

        $subdirs = glob($source.'/*', GLOB_ONLYDIR) ?: [];
        sort($subdirs);
        $topFiles = glob($source.'/*.md') ?: [];
        sort($topFiles);

        if ($subdirs === [] && $topFiles === []) {
            $this->info("No Markdown files found in {$source}.");

            return self::SUCCESS;
        }

        $rows = [];

        foreach ($subdirs as $dir) {
            $files = glob($dir.'/*.md') ?: [];
            if ($files === []) {
                continue;
            }
            sort($files);

            [$slug, $order, $title] = $this->parseDirname($dir);
            $collection = $this->resolveCollection($slug, $title, $order, $dryRun);
            $rows = array_merge($rows, $this->syncFiles($collection, $slug, $files, $dryRun));
        }

        if ($topFiles !== []) {
            $slug = (string) ($this->option('collection') ?: 'sdk');
            $collection = $this->resolveCollection($slug, $this->humanize($slug), null, $dryRun);
            $rows = array_merge($rows, $this->syncFiles($collection, $slug, $topFiles, $dryRun));
        }

        $this->table(['Collection', 'Page', $dryRun ? 'Would' : 'Result'], $rows);
        $this->info(($dryRun ? '[dry run] ' : '').count($rows).' page(s) processed.');

        return self::SUCCESS;
    }

    /**
     * @param  list<string>  $files
     * @return list<array{0: string, 1: string, 2: string}>
     */
    private function syncFiles(?DocCollection $collection, string $collectionSlug, array $files, bool $dryRun): array
    {
        $maxOrder = $collection?->pages()->max('order');
        $sequential = is_numeric($maxOrder) ? (int) $maxOrder : -1;

        $rows = [];
        foreach ($files as $file) {
            [$slug, $explicitOrder] = $this->parseFilename($file);
            $content = (string) file_get_contents($file);
            $title = $this->extractTitle($content, $slug);

            $existing = DocPage::query()->where('slug', $slug)->first();
            $result = $this->determineResult($existing, $content, $collection?->id);

            $order = $explicitOrder ?? ++$sequential;

            if (! $dryRun && $result !== 'unchanged' && $collection !== null) {
                $this->writePage($existing, $collection, $slug, $title, $content, $order, $explicitOrder !== null);
            }

            $rows[] = [$collectionSlug, $slug, $result];
        }

        return $rows;
    }

    private function resolveSource(): string
    {
        $source = $this->option('source');

        return is_string($source) && $source !== ''
            ? rtrim($source, '/\\')
            : base_path('../magna-plugin-sdk/docs');
    }

    private function resolveCollection(string $slug, string $title, ?int $order, bool $dryRun): ?DocCollection
    {
        $existing = DocCollection::query()->where('slug', $slug)->first();
        if ($existing !== null || $dryRun) {
            return $existing;
        }

        return DocCollection::create([
            'title' => $title,
            'slug' => $slug,
            'description' => $title.' documentation.',
            'icon' => 'book-open',
            'order' => $order ?? 0,
            'is_public' => true,
        ]);
    }

    private function determineResult(?DocPage $existing, string $content, ?int $collectionId): string
    {
        if ($existing === null) {
            return 'created';
        }

        if ($existing->content === $content && $existing->collection_id === $collectionId) {
            return 'unchanged';
        }

        return 'updated';
    }

    private function writePage(?DocPage $existing, DocCollection $collection, string $slug, string $title, string $content, int $order, bool $orderIsExplicit = false): void
    {
        if ($existing !== null) {
            $attributes = [
                'title' => $title,
                'content' => $content,
                'collection_id' => $collection->id,
                'status' => 'published',
                'is_published' => true,
                'published_at' => $existing->published_at ?? now(),
            ];

            // Only overwrite order when the filename explicitly declares one;
            // otherwise preserve any hand-tuned ordering set in the admin.
            if ($orderIsExplicit) {
                $attributes['order'] = $order;
            }

            $existing->update($attributes);

            return;
        }

        DocPage::create([
            'collection_id' => $collection->id,
            'title' => $title,
            'slug' => $slug,
            'content' => $content,
            'status' => 'published',
            'order' => $order,
            'is_published' => true,
            'published_at' => now(),
        ]);
    }

    /**
     * Resolve a directory name into [slug, order, title]. A leading numeric
     * prefix (01-getting-started) sets the collection order and is stripped from
     * the slug.
     *
     * @return array{0: string, 1: int|null, 2: string}
     */
    private function parseDirname(string $dir): array
    {
        $base = basename($dir);

        if (preg_match('/^(\d+)[-_](.+)$/', $base, $m) === 1) {
            return [$m[2], (int) $m[1], $this->humanize($m[2])];
        }

        return [$base, null, $this->humanize($base)];
    }

    /**
     * Resolve a markdown filename into [slug, explicitOrder].
     *
     * @return array{0: string, 1: int|null}
     */
    private function parseFilename(string $file): array
    {
        $base = basename($file, '.md');

        if (preg_match('/^(\d+)[-_](.+)$/', $base, $m) === 1) {
            return [$m[2], (int) $m[1]];
        }

        return [$base, null];
    }

    private function extractTitle(string $content, string $slug): string
    {
        if (preg_match('/^\#\s+(.+)$/m', $content, $m) === 1) {
            return trim($m[1]);
        }

        return $this->humanize($slug);
    }

    private function humanize(string $slug): string
    {
        return ucwords(str_replace(['-', '_'], ' ', $slug));
    }
}
