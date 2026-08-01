<?php

declare(strict_types=1);

namespace Magna\Docs\Transfer;

use RuntimeException;
use ZipArchive;

/**
 * Thin ZipArchive wrapper that survives an export with thousands of files.
 *
 * ZipArchive defers reading an added file until close(), holding a descriptor
 * per pending entry — an install with a few thousand images would hit the
 * process's open-file limit and fail the whole export. This flushes (close +
 * reopen) every DocsArchive::ZIP_FLUSH_EVERY additions, which bounds the open
 * descriptors regardless of archive size.
 */
final class ZipWriter
{
    private ZipArchive $zip;

    private int $pending = 0;

    public function __construct(private readonly string $path)
    {
        $this->zip = new ZipArchive;

        if ($this->zip->open($path, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            throw new RuntimeException("Could not create the export archive at {$path}.");
        }
    }

    public function addString(string $entry, string $contents): void
    {
        if (! $this->zip->addFromString($entry, $contents)) {
            throw new RuntimeException("Could not write {$entry} into the export archive.");
        }
    }

    /**
     * @param  bool  $store  Skip deflate for this entry. Worth passing for media:
     *                       JPEG/PNG/WebP are already compressed, so deflating
     *                       them again burns CPU for ~0% size gain — which is
     *                       the difference between a slow and a fast export on a
     *                       site with thousands of images.
     */
    public function addFile(string $absolutePath, string $entry, bool $store = false): void
    {
        if (! $this->zip->addFile($absolutePath, $entry)) {
            throw new RuntimeException("Could not add {$entry} to the export archive.");
        }

        if ($store) {
            $this->zip->setCompressionName($entry, ZipArchive::CM_STORE);
        }

        if (++$this->pending >= DocsArchive::ZIP_FLUSH_EVERY) {
            $this->flush();
        }
    }

    public function finish(): void
    {
        if (! $this->zip->close()) {
            throw new RuntimeException('Could not finalise the export archive.');
        }
    }

    /** Commit what has been added so far and reopen for more. */
    private function flush(): void
    {
        if (! $this->zip->close()) {
            throw new RuntimeException('Could not flush the export archive.');
        }

        if ($this->zip->open($this->path) !== true) {
            throw new RuntimeException('Could not reopen the export archive to continue writing.');
        }

        $this->pending = 0;
    }
}
