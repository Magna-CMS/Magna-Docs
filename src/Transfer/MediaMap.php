<?php

declare(strict_types=1);

namespace Magna\Docs\Transfer;

/**
 * Translation table from "where a file lived on the source install" to "where
 * it lives here", plus the text substitutions that keeps page bodies pointing
 * at real files after an import.
 *
 * Imported files are re-ingested through the media library, so their paths
 * change — every reference in the Markdown has to move with them.
 */
final class MediaMap
{
    /** @var array<string, string> old public-disk path => new public-disk path */
    private array $paths = [];

    /** @var array<string, string> old URL/text => new URL/text, for strtr() */
    private array $replacements = [];

    public function __construct(
        private readonly string $sourceUrlPrefix,
        private readonly string $sourcePathPrefix,
        private readonly string $targetUrlPrefix,
        private readonly string $targetPathPrefix,
    ) {}

    public function add(string $oldPath, string $newPath): void
    {
        $this->paths[$oldPath] = $newPath;

        if ($this->sourceUrlPrefix !== '') {
            $this->replacements[$this->sourceUrlPrefix.$oldPath] = $this->targetUrlPrefix.$newPath;
        }

        $this->replacements[$this->sourcePathPrefix.$oldPath] = $this->targetPathPrefix.$newPath;
    }

    public function isEmpty(): bool
    {
        return $this->paths === [];
    }

    /** Rewrite every known media reference inside a page body. */
    public function rewrite(?string $content): string
    {
        $content = (string) $content;

        if ($content === '' || $this->replacements === []) {
            return $content;
        }

        // strtr() with an array matches the longest key first and never
        // re-scans replaced text, so an absolute URL can't be half-rewritten by
        // the rooted-path rule for the same file.
        return strtr($content, $this->replacements);
    }

    /** Map a stored path (e.g. a featured image) to its imported location. */
    public function path(?string $stored): ?string
    {
        $stored = trim((string) $stored);

        if ($stored === '') {
            return null;
        }

        return $this->paths[$stored] ?? $stored;
    }

    public function count(): int
    {
        return count($this->paths);
    }
}
