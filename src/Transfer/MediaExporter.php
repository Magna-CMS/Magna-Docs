<?php

declare(strict_types=1);

namespace Magna\Docs\Transfer;

use Illuminate\Support\Facades\Storage;

/**
 * Accumulates the public-disk files an export depends on (featured images plus
 * anything referenced inside page Markdown), de-duplicated, then resolves them
 * to archive entries. Only paths that actually exist on disk are exported — a
 * page pointing at a deleted image must not fail the whole export.
 */
final class MediaExporter
{
    /** @var array<string, true> Source path => noted. Keyed for O(1) de-duplication. */
    private array $paths = [];

    public function __construct(
        private readonly string $urlPrefix,
        private readonly string $pathPrefix,
    ) {}

    public function noteStoredPath(?string $path): void
    {
        $path = trim((string) $path);

        if ($path !== '') {
            $this->paths[$path] = true;
        }
    }

    public function noteContent(?string $content): void
    {
        foreach (MediaReferences::fromContent((string) $content, $this->urlPrefix, $this->pathPrefix) as $path) {
            $this->paths[$path] = true;
        }
    }

    /**
     * Resolve every noted path to an archive entry, skipping missing files.
     *
     * @return list<array{path: string, file: string, bytes: int, sha256: string, absolute: string}>
     */
    public function entries(): array
    {
        $disk = Storage::disk('public');
        $entries = [];

        foreach (array_keys($this->paths) as $path) {
            if (! $disk->exists($path)) {
                continue;
            }

            $absolute = $disk->path($path);

            if (! is_file($absolute)) {
                continue;
            }

            $entries[] = [
                'path' => $path,
                'file' => DocsArchive::mediaEntryName($path),
                'bytes' => (int) (filesize($absolute) ?: 0),
                'sha256' => (string) hash_file('sha256', $absolute),
                'absolute' => $absolute,
            ];
        }

        return $entries;
    }
}
