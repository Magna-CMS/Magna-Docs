<?php

declare(strict_types=1);

namespace Magna\Docs\Transfer;

use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Magna\Media\MediaIngestor;
use Throwable;

/**
 * Restores the binaries carried by an archive.
 *
 * Every file goes through the core MediaIngestor rather than being written
 * straight to the public disk. That is the security boundary for an import: an
 * archive is untrusted input, and the ingestor content-sniffs the MIME type,
 * enforces the allowlist, re-encodes rasters, and sanitises SVG. It also means
 * imported images land in the media library like any other upload — visible in
 * /media, reusable, and with conversions generated.
 *
 * A file that fails ingestion is reported and skipped; it never aborts the
 * import, because one bad image should not cost a thousand good pages.
 */
final class MediaRestorer
{
    private const TEMP_DIRECTORY = 'magna-docs-imports/tmp';

    public function __construct(private readonly MediaIngestor $ingestor) {}

    public function restore(ArchiveReader $archive, ImportReport $report, bool $dryRun): MediaMap
    {
        $prefixes = $archive->sourcePrefixes();

        $map = new MediaMap(
            sourceUrlPrefix: $prefixes['url'],
            sourcePathPrefix: $prefixes['path'],
            targetUrlPrefix: MediaReferences::storageUrlPrefix(),
            targetPathPrefix: MediaReferences::storagePathPrefix(),
        );

        if (! $archive->has(DocsArchive::MEDIA_INDEX)) {
            return $map;
        }

        $disk = Storage::disk('local');
        $workDir = self::TEMP_DIRECTORY.'-'.Str::random(10);
        $disk->makeDirectory($workDir);

        try {
            foreach ($archive->lines(DocsArchive::MEDIA_INDEX) as $number => $line) {
                if ($line === null) {
                    $report->error("media.ndjson line {$number}: not valid JSON — skipped.");
                    $report->add('media_failed');

                    continue;
                }

                $this->restoreOne($archive, $line, $map, $report, $dryRun, $disk->path($workDir));
            }
        } finally {
            $disk->deleteDirectory($workDir);
        }

        return $map;
    }

    /**
     * @param  array<string, mixed>  $line
     */
    private function restoreOne(
        ArchiveReader $archive,
        array $line,
        MediaMap $map,
        ImportReport $report,
        bool $dryRun,
        string $workPath,
    ): void {
        $path = (string) ($line['path'] ?? '');
        $entry = (string) ($line['file'] ?? '');

        if ($path === '' || ! DocsArchive::isSafeEntry($entry) || ! $archive->has($entry)) {
            $report->error("Media '{$path}' is listed in the archive but its file is missing — skipped.");
            $report->add('media_failed');

            return;
        }

        if ($dryRun) {
            $report->add('media_imported');

            return;
        }

        $temp = $workPath.'/'.basename($entry);

        try {
            if (! $archive->extractTo($entry, $temp)) {
                throw new \RuntimeException('could not be extracted');
            }

            $media = $this->ingestor->ingest($temp, basename($path), 'public');

            $map->add($path, $media->path);
            $report->add('media_imported');
        } catch (Throwable $e) {
            $report->error("Media '{$path}' was rejected: ".$e->getMessage());
            $report->add('media_failed');
        } finally {
            if (is_file($temp)) {
                @unlink($temp);
            }
        }
    }
}
