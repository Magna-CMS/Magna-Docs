<?php

declare(strict_types=1);

namespace Magna\Docs\Commands;

use Illuminate\Console\Command;
use Magna\Docs\Transfer\ConflictStrategy;
use Magna\Docs\Transfer\DocsImporter;
use Magna\Docs\Transfer\ImportOptions;
use Throwable;

/**
 * CLI half of the docs importer. Prefer `--dry-run` first on a site with
 * existing content: it reports exactly what would be created, updated and
 * skipped without writing anything.
 */
class DocsImportCommand extends Command
{
    protected $signature = 'magna:docs:import
        {archive : Path to a .zip produced by magna:docs:export}
        {--conflict=update : What to do with slugs that already exist: update, skip or duplicate}
        {--dry-run : Report what would change without writing}
        {--no-media : Do not restore media files}
        {--settings : Also apply the archive\'s docs branding settings}';

    protected $description = 'Import a Magna Docs archive (collections, pages, translations, media).';

    public function handle(DocsImporter $importer): int
    {
        $archive = (string) $this->argument('archive');

        if (! is_file($archive)) {
            $this->error("Archive not found: {$archive}");

            return self::FAILURE;
        }

        $conflict = ConflictStrategy::tryFrom((string) $this->option('conflict'));

        if ($conflict === null) {
            $this->error('Unknown --conflict value. Use one of: update, skip, duplicate.');

            return self::FAILURE;
        }

        $options = new ImportOptions(
            conflict: $conflict,
            dryRun: (bool) $this->option('dry-run'),
            importMedia: ! (bool) $this->option('no-media'),
            importSettings: (bool) $this->option('settings'),
        );

        $this->info($options->dryRun ? 'Dry run — nothing will be written…' : 'Importing docs…');

        try {
            $report = $importer->importArchive($archive, $options);
        } catch (Throwable $e) {
            $this->error('Import failed: '.$e->getMessage());

            return self::FAILURE;
        }

        $rows = [];
        foreach ($report->counts() as $bucket => $count) {
            $rows[] = [str_replace('_', ' ', $bucket), (string) $count];
        }
        $this->table([$options->dryRun ? 'Would' : 'Result', 'Count'], $rows);

        foreach ($report->errors() as $error) {
            $this->warn($error);
        }

        $this->info(($options->dryRun ? '[dry run] ' : '').$report->summary());

        return self::SUCCESS;
    }
}
