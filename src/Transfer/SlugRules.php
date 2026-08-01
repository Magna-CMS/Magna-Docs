<?php

declare(strict_types=1);

namespace Magna\Docs\Transfer;

/**
 * Slug is the archive's natural key and also a public URL segment
 * (`/docs/{slug}`), so an imported one is validated before it is trusted:
 * no slashes (the route would not match), no path or query characters, and
 * short enough for the unique index.
 */
final class SlugRules
{
    private const PATTERN = '/^[A-Za-z0-9][A-Za-z0-9._-]{0,190}$/';

    public static function clean(mixed $value): ?string
    {
        $slug = is_scalar($value) ? trim((string) $value) : '';

        return preg_match(self::PATTERN, $slug) === 1 ? $slug : null;
    }
}
