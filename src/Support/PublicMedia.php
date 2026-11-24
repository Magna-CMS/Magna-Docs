<?php

declare(strict_types=1);

namespace Magna\Docs\Support;

use Illuminate\Support\Facades\Storage;
use Throwable;

/**
 * Absolute URLs for files the docs settings and pages store on the public
 * disk — what Open Graph and Twitter cards need, since a crawler fetching a
 * shared link has no base URL to resolve a relative path against.
 */
final class PublicMedia
{
    /** Absolute URL for a stored path (or an already-absolute URL); null when blank or unresolvable. */
    public static function url(mixed $path): ?string
    {
        if (! is_string($path) || trim($path) === '') {
            return null;
        }

        $path = trim($path);

        if (preg_match('#^https?://#i', $path) === 1) {
            return $path;
        }

        try {
            return url(Storage::disk('public')->url($path));
        } catch (Throwable) {
            // A misconfigured disk must not take the whole docs site down with it.
            return null;
        }
    }
}
