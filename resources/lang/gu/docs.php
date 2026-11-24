<?php

/*
 * Strings on the public docs site (layout, page, search, empty states).
 * Rendered in the reader's docs language as `docs::docs.<key>`; a key missing
 * from another language falls back to this file.
 *
 * Placeholders (:site, :title, :count, :minutes, :date) are filled in by the
 * plugin — keep them, in whatever position the sentence needs.
 */

return [
    // ── Header, navigation, search ────────────────────────────────────────
    'skip_to_content' => 'મુખ્ય લખાણ પર જાઓ',
    'open_navigation' => 'નેવિગેશન ખોલો',
    'close' => 'બંધ કરો',
    'home_link' => ':site હોમ',
    'search_label' => 'માર્ગદર્શિકામાં શોધો',
    'search_placeholder' => 'માર્ગદર્શિકામાં શોધો... (\'/\' દબાવો)',
    'search_placeholder_short' => 'માર્ગદર્શિકામાં શોધો...',
    'no_results' => 'કંઈ મળ્યું નથી.',
    'language' => 'ભાષા',
    'toggle_theme' => 'થીમ બદલો',
    'navigation' => 'નેવિગેશન',
    'no_pages' => 'હજુ કોઈ પેજ નથી.',
    'sitemap' => 'ડોક્સ સાઇટમેપ',

    // ── "On this page" rail ───────────────────────────────────────────────
    'on_this_page' => 'આ પેજ પર',
    'no_sections' => 'આ પેજ પર કોઈ વિભાગ નથી.',

    // ── Article ───────────────────────────────────────────────────────────
    'breadcrumb' => 'બ્રેડક્રમ્બ',
    'breadcrumb_root' => 'ડોક્સ',
    'previous' => '← પાછળ',
    'next' => 'આગળ →',
    'copy' => 'કોપી કરો',
    'copied' => 'કોપી થઈ ગયું!',
    'reading_time' => ':minutes મિનિટ વાંચન',
    'last_updated' => 'છેલ્લે અપડેટ: :date',

    // ── "Was this page helpful?" ──────────────────────────────────────────
    'helpful_question' => 'આ પેજ મદદરૂપ થયું?',
    'helpful_yes' => 'હા',
    'helpful_no' => 'ના',
    'helpful_thanks' => 'તમારા પ્રતિભાવ માટે આભાર!',
    'helpful_count_one' => ':count વ્યક્તિને આ પેજ મદદરૂપ લાગ્યું.',
    'helpful_count_other' => ':count વ્યક્તિઓને આ પેજ મદદરૂપ લાગ્યું.',

    // ── Footer ────────────────────────────────────────────────────────────
    'made_with' => 'Magna Docs થી બનાવેલ',

    // ── Titles and the empty docs site ────────────────────────────────────
    'page_title' => ':title — માર્ગદર્શિકા',
    'documentation' => 'માર્ગદર્શિકા',
    'browse_documentation' => 'માર્ગદર્શિકા જુઓ.',
    'nothing_published' => 'હજુ સુધી કોઈ માર્ગદર્શિકા પ્રકાશિત થઈ નથી. એક વાર પેજ પ્રકાશિત થાય એટલે તે હોમ પેજ બની જશે.',
];
