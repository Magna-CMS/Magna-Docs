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
    'skip_to_content' => 'உள்ளடக்கத்திற்குச் செல்லவும்',
    'open_navigation' => 'வழிகாட்டியைத் திற',
    'close' => 'மூடு',
    'home_link' => ':site முகப்பு',
    'search_label' => 'ஆவணங்களில் தேடு',
    'search_placeholder' => 'ஆவணங்களில் தேடு... (\'/\' அழுத்தவும்)',
    'search_placeholder_short' => 'ஆவணங்களில் தேடு...',
    'no_results' => 'முடிவுகள் எதுவும் இல்லை.',
    'language' => 'மொழி',
    'toggle_theme' => 'தோற்றத்தை மாற்று',
    'navigation' => 'வழிகாட்டி',
    'no_pages' => 'இன்னும் பக்கங்கள் எதுவும் இல்லை.',
    'sitemap' => 'ஆவணப் பட்டியல்',

    // ── "On this page" rail ───────────────────────────────────────────────
    'on_this_page' => 'இப்பக்கத்தில்',
    'no_sections' => 'இப்பக்கத்தில் பகுதிகள் எதுவும் இல்லை.',

    // ── Article ───────────────────────────────────────────────────────────
    'breadcrumb' => 'பாதை',
    'breadcrumb_root' => 'ஆவணங்கள்',
    'previous' => '← முந்தையது',
    'next' => 'அடுத்தது →',
    'copy' => 'நகலெடு',
    'copied' => 'நகலெடுக்கப்பட்டது!',
    'reading_time' => ':minutes நிமிடப் படிப்பு',
    'last_updated' => 'கடைசியாகப் புதுப்பிக்கப்பட்டது: :date',

    // ── "Was this page helpful?" ──────────────────────────────────────────
    'helpful_question' => 'இந்தப் பக்கம் பயனுள்ளதாக இருந்ததா?',
    'helpful_yes' => 'ஆம்',
    'helpful_no' => 'இல்லை',
    'helpful_thanks' => 'உங்கள் கருத்துக்கு நன்றி!',
    'helpful_count_one' => ':count நபருக்கு இப்பக்கம் பயனுள்ளதாக இருந்தது.',
    'helpful_count_other' => ':count நபர்களுக்கு இப்பக்கம் பயனுள்ளதாக இருந்தது.',

    // ── Footer ────────────────────────────────────────────────────────────
    'made_with' => 'Magna Docs மூலம் உருவாக்கப்பட்டது',

    // ── Titles and the empty docs site ────────────────────────────────────
    'page_title' => ':title — ஆவணங்கள்',
    'documentation' => 'ஆவணங்கள்',
    'browse_documentation' => 'ஆவணங்களைப் பார்க்கவும்.',
    'nothing_published' => 'இன்னும் எந்த ஆவணமும் வெளியிடப்படவில்லை. ஒரு பக்கம் வெளியிடப்பட்டதும் அது முகப்புப் பக்கமாக மாறும்.',
];
