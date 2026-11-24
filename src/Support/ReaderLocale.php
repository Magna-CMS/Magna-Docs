<?php

declare(strict_types=1);

namespace Magna\Docs\Support;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cookie;

/**
 * Which language a reader gets the docs in.
 *
 * The reader picks a language once (the language menu links to `?lang=xx`)
 * and the choice is remembered in the `docs_lang` cookie, so plain sidebar,
 * prev/next and search links stay in that language afterwards. `?lang=en`
 * is a real choice too: it is how a reader goes back to English.
 *
 * A page is only ever shown in a language it actually has; otherwise it falls
 * back to English, the page itself.
 */
final class ReaderLocale
{
    public const COOKIE = 'docs_lang';

    /** One year, in minutes (what Cookie::queue() takes). */
    public const COOKIE_MINUTES = 60 * 24 * 365;

    public const DEFAULT = 'en';

    /** A usable `?lang=` value on this request, or null when there is none. */
    public static function requested(Request $request): ?string
    {
        return self::clean($request->query('lang'));
    }

    /**
     * The language the reader asked for: an explicit `?lang=` on this request,
     * else the remembered cookie, else English. An explicit `?lang=` wins over
     * the cookie even when the page lacks that language — the reader asked
     * for it just now, so an older preference must not answer instead.
     */
    public static function preferred(Request $request): string
    {
        return self::requested($request)
            ?? self::clean($request->cookie(self::COOKIE))
            ?? self::DEFAULT;
    }

    /**
     * The language a page renders in: the preferred one when the page has it,
     * otherwise English.
     *
     * @param  list<string>  $available  Locales the page has, 'en' included.
     */
    public static function resolve(Request $request, array $available): string
    {
        $preferred = self::preferred($request);

        return in_array($preferred, $available, true) ? $preferred : self::DEFAULT;
    }

    /** Remember an explicit `?lang=` choice for a year. No-op without one. */
    public static function remember(Request $request): void
    {
        $requested = self::requested($request);

        if ($requested !== null) {
            Cookie::queue(self::COOKIE, $requested, self::COOKIE_MINUTES);
        }
    }

    /**
     * A well-formed locale code, or null for anything else. Not limited to
     * DocLocales: an imported archive may carry a translation such as `pt-br`,
     * and a reader must still be able to pick it.
     */
    public static function clean(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $value = strtolower(trim($value));

        return DocLocales::isWellFormed($value) ? $value : null;
    }
}
