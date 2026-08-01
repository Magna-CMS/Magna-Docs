<?php

declare(strict_types=1);

namespace Magna\Docs\Transfer;

/**
 * The on-disk contract for a Magna Docs portability archive (`.zip`).
 *
 * Layout:
 *
 *   manifest.json         format + version + counts + checksums + rewrite hints
 *   collections.ndjson    one JSON collection per line
 *   pages.ndjson          one JSON page per line (translations nested)
 *   media.ndjson          one JSON media descriptor per line (only with media)
 *   settings.json         docs branding/settings snapshot (optional)
 *   media/<sha1>.<ext>    the referenced binaries, content-addressed
 *
 * NDJSON — not a single JSON document and not WordPress's WXR XML — because an
 * install with thousands of pages must export and import in constant memory:
 * every line is written and read on its own, so neither side ever holds the
 * whole corpus. Records are keyed by **slug**, never by database id, so an
 * archive is portable between installs whose auto-increment ids differ.
 */
final class DocsArchive
{
    public const FORMAT = 'magna-docs-export';

    /** Bumped only on a breaking layout change; readers accept <= this. */
    public const VERSION = 1;

    public const MANIFEST = 'manifest.json';

    public const COLLECTIONS = 'collections.ndjson';

    public const PAGES = 'pages.ndjson';

    public const MEDIA_INDEX = 'media.ndjson';

    public const SETTINGS = 'settings.json';

    public const MEDIA_DIR = 'media/';

    /**
     * Zip-bomb / runaway-archive guards, applied before anything is extracted.
     * Generous enough for a real large docs site (100s of collections, 1000s of
     * pages, their images) and far below what an abusive archive would claim.
     */
    public const MAX_ENTRIES = 200_000;

    public const MAX_UNCOMPRESSED_BYTES = 4 * 1024 * 1024 * 1024;

    /**
     * ZipArchive keeps a file handle open per added file until close(), so a
     * few thousand media files would exhaust the process's descriptor limit.
     * The writer flushes (close + reopen) every this many additions.
     */
    public const ZIP_FLUSH_EVERY = 256;

    /**
     * Rows written/read per batch. Big enough to keep the query count low on a
     * 1000-page import, small enough that one batch's rows stay small in memory.
     */
    public const BATCH = 200;

    /**
     * True when a zip entry name is safe to read from an untrusted archive:
     * no absolute paths, no drive letters, no `..` traversal, no backslashes,
     * and only entries this format actually defines.
     */
    public static function isSafeEntry(string $name): bool
    {
        if ($name === '' || str_contains($name, '\\') || str_contains($name, "\0")) {
            return false;
        }

        if (str_starts_with($name, '/') || preg_match('/^[A-Za-z]:/', $name) === 1) {
            return false;
        }

        foreach (explode('/', $name) as $segment) {
            if ($segment === '..' || $segment === '.') {
                return false;
            }
        }

        if (str_starts_with($name, self::MEDIA_DIR)) {
            return preg_match('/^media\/[A-Za-z0-9._-]+$/', $name) === 1;
        }

        return in_array($name, [
            self::MANIFEST,
            self::COLLECTIONS,
            self::PAGES,
            self::MEDIA_INDEX,
            self::SETTINGS,
        ], true);
    }

    /**
     * Content-addressed archive name for a source media path. Two pages that
     * reference the same file produce the same entry, so it is stored once.
     */
    public static function mediaEntryName(string $sourcePath): string
    {
        $extension = strtolower(pathinfo($sourcePath, PATHINFO_EXTENSION));
        $extension = preg_match('/^[a-z0-9]{1,8}$/', $extension) === 1 ? $extension : 'bin';

        return self::MEDIA_DIR.sha1($sourcePath).'.'.$extension;
    }
}
