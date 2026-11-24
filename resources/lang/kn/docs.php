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
    'skip_to_content' => 'ನೇರವಾಗಿ ವಿಷಯಕ್ಕೆ ಹೋಗಿ',
    'open_navigation' => 'ಮೆನು ತೆರೆಯಿರಿ',
    'close' => 'ಮುಚ್ಚಿ',
    'home_link' => ':site ಮುಖಪುಟ',
    'search_label' => 'ಮಾರ್ಗದರ್ಶಿಯಲ್ಲಿ ಹುಡುಕಿ',
    'search_placeholder' => 'ಮಾರ್ಗದರ್ಶಿಯಲ್ಲಿ ಹುಡುಕಿ... (ಟೈಪ್ ಮಾಡಲು \'/\' ಒತ್ತಿ)',
    'search_placeholder_short' => 'ಮಾರ್ಗದರ್ಶಿಯಲ್ಲಿ ಹುಡುಕಿ...',
    'no_results' => 'ಯಾವುದೇ ಫಲಿತಾಂಶಗಳು ಸಿಗಲಿಲ್ಲ.',
    'language' => 'ಭಾಷೆ',
    'toggle_theme' => 'ಥೀಮ್ ಬದಲಿಸಿ',
    'navigation' => 'ಮೆನು',
    'no_pages' => 'ಇನ್ನೂ ಯಾವುದೇ ಪುಟಗಳಿಲ್ಲ.',
    'sitemap' => 'ಪುಟಗಳ ನಕ್ಷೆ',

    // ── "On this page" rail ───────────────────────────────────────────────
    'on_this_page' => 'ಈ ಪುಟದಲ್ಲಿ',
    'no_sections' => 'ಈ ಪುಟದಲ್ಲಿ ಯಾವುದೇ ವಿಭಾಗಗಳಿಲ್ಲ.',

    // ── Article ───────────────────────────────────────────────────────────
    'breadcrumb' => 'ಮಾರ್ಗಸೂಚಿ',
    'breadcrumb_root' => 'ಮಾರ್ಗದರ್ಶಿ',
    'previous' => '← ಹಿಂದಿನದು',
    'next' => 'ಮುಂದಿನದು →',
    'copy' => 'ಕಾಪಿ ಮಾಡಿ',
    'copied' => 'ಕಾಪಿ ಆಗಿದೆ!',
    'reading_time' => ':minutes ನಿಮಿಷಗಳ ಓದು',
    'last_updated' => 'ಕೊನೆಯದಾಗಿ ನವೀಕರಿಸಿದ್ದು: :date',

    // ── "Was this page helpful?" ──────────────────────────────────────────
    'helpful_question' => 'ಈ ಪುಟ ನಿಮಗೆ ಉಪಯುಕ್ತವಾಯಿತೇ?',
    'helpful_yes' => 'ಹೌದು',
    'helpful_no' => 'ಇಲ್ಲ',
    'helpful_thanks' => 'ನಿಮ್ಮ ಪ್ರತಿಕ್ರಿಯೆಗೆ ಧನ್ಯವಾದಗಳು!',
    'helpful_count_one' => ':count ಒಬ್ಬರಿಗೆ ಈ ಪುಟ ಉಪಯುಕ್ತವಾಗಿದೆ.',
    'helpful_count_other' => ':count ಜನರಿಗೆ ಈ ಪುಟ ಉಪಯುಕ್ತವಾಗಿದೆ.',

    // ── Footer ────────────────────────────────────────────────────────────
    'made_with' => 'Magna Docs ನಲ್ಲಿ ನಿರ್ಮಿಸಲಾಗಿದೆ',

    // ── Titles and the empty docs site ────────────────────────────────────
    'page_title' => ':title — ಮಾರ್ಗದರ್ಶಿ',
    'documentation' => 'ಮಾರ್ಗದರ್ಶಿ',
    'browse_documentation' => 'ಮಾರ್ಗದರ್ಶಿ ಪುಟಗಳನ್ನು ನೋಡಿ.',
    'nothing_published' => 'ಇನ್ನೂ ಯಾವುದೇ ಮಾರ್ಗದರ್ಶಿ ಪ್ರಕಟವಾಗಿಲ್ಲ. ಮೊದಲ ಪುಟ ಪ್ರಕಟವಾದ ತಕ್ಷಣ ಅದೇ ಮುಖಪುಟವಾಗುತ್ತದೆ.',
];
