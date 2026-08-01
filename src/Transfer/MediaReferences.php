<?php

declare(strict_types=1);

namespace Magna\Docs\Transfer;

use Illuminate\Support\Facades\Storage;

/**
 * Finds the media a page's Markdown depends on, and knows the two URL shapes a
 * stored file can appear as inside that Markdown:
 *
 *   absolute  https://old-site.test/storage/media/2026/07/diagram.png
 *   rooted    /storage/media/2026/07/diagram.png
 *
 * Both reduce to the same public-disk path (`media/2026/07/diagram.png`), which
 * is what the archive stores. On import the path changes (files are re-ingested
 * through the media library), so both shapes are rewritten — see MediaRestorer.
 */
final class MediaReferences
{
    /** Absolute public-disk URL prefix for this install, e.g. `https://site.test/storage/`. */
    public static function storageUrlPrefix(): string
    {
        $probe = Storage::disk('public')->url('__probe__');

        return substr($probe, 0, strrpos($probe, '__probe__') ?: strlen($probe));
    }

    /** The path-only form of the prefix, e.g. `/storage/`. */
    public static function storagePathPrefix(): string
    {
        $path = parse_url(self::storageUrlPrefix(), PHP_URL_PATH);

        return is_string($path) && $path !== '' ? $path : '/storage/';
    }

    /**
     * Public-disk paths referenced by a page body, in appearance order and
     * de-duplicated. Matches Markdown images/links and raw HTML `src`/`href`
     * alike, because all of them carry the same URL shape.
     *
     * @return list<string>
     */
    public static function fromContent(string $content, string $urlPrefix, string $pathPrefix): array
    {
        $prefixes = array_unique(array_filter([$urlPrefix, $pathPrefix]));
        $found = [];

        foreach ($prefixes as $prefix) {
            $pattern = '#'.preg_quote($prefix, '#').'([A-Za-z0-9._\-/%]+)#';

            if (preg_match_all($pattern, $content, $matches) === false) {
                continue;
            }

            foreach ($matches[1] as $path) {
                $clean = rawurldecode(rtrim($path, '.,)'));

                if ($clean !== '' && ! in_array($clean, $found, true)) {
                    $found[] = $clean;
                }
            }
        }

        return $found;
    }
}
