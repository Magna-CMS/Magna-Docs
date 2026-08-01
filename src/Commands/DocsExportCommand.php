<?php

declare(strict_types=1);

namespace Magna\Docs\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;
use Magna\Docs\Transfer\DocsExporter;
use Magna\Docs\Transfer\ExportOptions;
use Throwable;

/**
 * CLI half of the docs portability tool. Same archive the admin screen
 * produces — this exists for installs big enough that a browser request is the
 * wrong place to do it, and for scripted backups/migrations.
 */
class DocsExportCommand extends Command
{
    protected $signature = 'magna:docs:export
        {--o|output= : Write the archive to this path instead of storage/app/magna-docs-exports}
        {--collection=* : Only export these collection slugs (repeatable); default is everything}
        {--no-drafts : Export published pages only}
        {--no-media : Skip images and other media files}
        {--settings : Include the docs branding settings}';

    protected $description = 'Export docs collections, pages, translations and media to a portable .zip archive.';

    public function handle(DocsExporter $exporter): int
    {
        /** @var list<string> $collections */
        $collections = array_values(array_map('strval', (array) $this->option('collection')));

        $options = new ExportOptions(
            collections: $collections,
            includeDrafts: ! (bool) $this->option('no-drafts'),
            includeMedia: ! (bool) $this->option('no-media'),
            includeSettings: (bool) $this->option('settings'),
        );

        $this->info('Exporting docs…');

        try {
            $result = $exporter->export($options);
        } catch (Throwable $e) {
            $this->error('Export failed: '.$e->getMessage());

            return self::FAILURE;
        }

        $destination = $this->moveToOutput($result->disk, $result->path, $result->filename);

        $this->table(['What', 'Count'], [
            ['Collections', (string) ($result->counts['collections'] ?? 0)],
            ['Pages', (string) ($result->counts['pages'] ?? 0)],
            ['Translations', (string) ($result->counts['translations'] ?? 0)],
            ['Media files', (string) ($result->counts['media'] ?? 0)],
        ]);

        $this->info("Archive written to {$destination} ({$result->humanSize()}).");

        return self::SUCCESS;
    }

    /** Returns wherever the archive ended up, for the closing message. */
    private function moveToOutput(string $disk, string $path, string $filename): string
    {
        $output = $this->option('output');
        $source = Storage::disk($disk)->path($path);

        if (! is_string($output) || trim($output) === '') {
            return $source;
        }

        $output = rtrim(trim($output), '/\\');
        $target = is_dir($output) ? $output.'/'.$filename : $output;

        if (! @rename($source, $target)) {
            $this->warn("Could not write to {$target}; the archive is still at {$source}.");

            return $source;
        }

        return $target;
    }
}
