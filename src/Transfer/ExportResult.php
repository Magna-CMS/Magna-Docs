<?php

declare(strict_types=1);

namespace Magna\Docs\Transfer;

/**
 * Where the finished archive landed, plus what went into it.
 */
final class ExportResult
{
    /**
     * @param  string  $disk  Laravel disk the archive was written to.
     * @param  string  $path  Path on that disk.
     * @param  array<string, int>  $counts  collections/pages/translations/media
     */
    public function __construct(
        public readonly string $disk,
        public readonly string $path,
        public readonly string $filename,
        public readonly int $bytes,
        public readonly array $counts,
    ) {}

    public function humanSize(): string
    {
        $units = ['B', 'KB', 'MB', 'GB'];
        $size = (float) $this->bytes;
        $unit = 0;

        while ($size >= 1024 && $unit < count($units) - 1) {
            $size /= 1024;
            $unit++;
        }

        return sprintf('%.1f %s', $size, $units[$unit]);
    }
}
