<?php

declare(strict_types=1);

namespace Magna\Docs\Support;

class DocLocales
{
    /**
     * Supported docs languages. 'en' is always the default (the page itself);
     * the rest are stored as translations.
     *
     * Each entry is [flag, English name, native name]. The native name is what
     * a reader sees in the public language menu — someone looking for
     * Malayalam scans for "മലയാളം", not for the English word — while the admin
     * screens show both, so an editor can search either.
     *
     * @var array<string, array{0: string, 1: string, 2: string}>
     */
    public const LABELS = [
        'en' => ['🇬🇧', 'English', 'English'],
        'es' => ['🇪🇸', 'Spanish', 'Español'],
        'fr' => ['🇫🇷', 'French', 'Français'],
        'de' => ['🇩🇪', 'German', 'Deutsch'],
        'it' => ['🇮🇹', 'Italian', 'Italiano'],
        'pt' => ['🇵🇹', 'Portuguese', 'Português'],
        'nl' => ['🇳🇱', 'Dutch', 'Nederlands'],
        'ru' => ['🇷🇺', 'Russian', 'Русский'],
        'uk' => ['🇺🇦', 'Ukrainian', 'Українська'],
        'pl' => ['🇵🇱', 'Polish', 'Polski'],
        'sv' => ['🇸🇪', 'Swedish', 'Svenska'],
        'no' => ['🇳🇴', 'Norwegian', 'Norsk'],
        'da' => ['🇩🇰', 'Danish', 'Dansk'],
        'fi' => ['🇫🇮', 'Finnish', 'Suomi'],
        'cs' => ['🇨🇿', 'Czech', 'Čeština'],
        'el' => ['🇬🇷', 'Greek', 'Ελληνικά'],
        'tr' => ['🇹🇷', 'Turkish', 'Türkçe'],
        'ar' => ['🇸🇦', 'Arabic', 'العربية'],
        'he' => ['🇮🇱', 'Hebrew', 'עברית'],
        'fa' => ['🇮🇷', 'Persian', 'فارسی'],
        'ur' => ['🇵🇰', 'Urdu', 'اردو'],
        'hi' => ['🇮🇳', 'Hindi', 'हिन्दी'],
        'bn' => ['🇮🇳', 'Bengali', 'বাংলা'],
        'pa' => ['🇮🇳', 'Punjabi', 'ਪੰਜਾਬੀ'],
        'ta' => ['🇮🇳', 'Tamil', 'தமிழ்'],
        'te' => ['🇮🇳', 'Telugu', 'తెలుగు'],
        'ml' => ['🇮🇳', 'Malayalam', 'മലയാളം'],
        'kn' => ['🇮🇳', 'Kannada', 'ಕನ್ನಡ'],
        'mr' => ['🇮🇳', 'Marathi', 'मराठी'],
        'gu' => ['🇮🇳', 'Gujarati', 'ગુજરાતી'],
        'zh' => ['🇨🇳', 'Chinese', '中文'],
        'ja' => ['🇯🇵', 'Japanese', '日本語'],
        'ko' => ['🇰🇷', 'Korean', '한국어'],
        'vi' => ['🇻🇳', 'Vietnamese', 'Tiếng Việt'],
        'th' => ['🇹🇭', 'Thai', 'ไทย'],
        'id' => ['🇮🇩', 'Indonesian', 'Bahasa Indonesia'],
        'ms' => ['🇲🇾', 'Malay', 'Bahasa Melayu'],
        'fil' => ['🇵🇭', 'Filipino', 'Filipino'],
        'sw' => ['🇰🇪', 'Swahili', 'Kiswahili'],
        'af' => ['🇿🇦', 'Afrikaans', 'Afrikaans'],
        'ro' => ['🇷🇴', 'Romanian', 'Română'],
        'hu' => ['🇭🇺', 'Hungarian', 'Magyar'],
    ];

    /**
     * Shape of a locale code a translation may be stored under: a two- or
     * three-letter language (`ml`, `fil`), optionally with a region or script
     * (`pt-br`, `zh_hant`). Lower-case; callers normalise first.
     */
    public const PATTERN = '/^[a-z]{2,3}(?:[-_][a-z0-9]{2,8})?$/';

    public static function isValid(string $locale): bool
    {
        return array_key_exists($locale, self::LABELS);
    }

    public static function isWellFormed(string $locale): bool
    {
        return preg_match(self::PATTERN, $locale) === 1;
    }

    /** The language's own name for itself (e.g. "മലയാളം"), as readers see it. */
    public static function label(string $locale): string
    {
        return self::LABELS[$locale][2] ?? strtoupper($locale);
    }

    /** The language's English name (e.g. "Malayalam"), for admin screens and search. */
    public static function englishName(string $locale): string
    {
        return self::LABELS[$locale][1] ?? strtoupper($locale);
    }

    /** "മലയാളം (Malayalam)" — both names, or just one where they are the same. */
    public static function adminLabel(string $locale): string
    {
        $native = self::label($locale);
        $english = self::englishName($locale);

        return $native === $english ? $native : "{$native} ({$english})";
    }

    public static function flag(string $locale): string
    {
        return self::LABELS[$locale][0] ?? '🏳️';
    }

    /**
     * Open Graph locale (`language_TERRITORY`, e.g. `ml_IN`, `en_GB`). The
     * territory is read from the flag, which is two regional-indicator
     * symbols spelling the country code — so the table above stays the single
     * source of truth.
     */
    public static function ogLocale(string $locale): string
    {
        $territory = '';

        foreach (mb_str_split(self::LABELS[$locale][0] ?? '') as $symbol) {
            $codepoint = mb_ord($symbol);

            if ($codepoint >= 0x1F1E6 && $codepoint <= 0x1F1FF) {
                $territory .= chr(ord('A') + $codepoint - 0x1F1E6);
            }
        }

        return strlen($territory) === 2 ? $locale.'_'.$territory : $locale;
    }
}
