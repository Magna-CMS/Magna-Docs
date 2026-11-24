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
    'skip_to_content' => 'मुख्य सामग्री पर जाएं',
    'open_navigation' => 'मेनू खोलें',
    'close' => 'बंद करें',
    'home_link' => ':site होम',
    'search_label' => 'डॉक्यूमेंटेशन खोजें',
    'search_placeholder' => 'डॉक्यूमेंटेशन खोजें... (\'/\' दबाएं)',
    'search_placeholder_short' => 'डॉक्यूमेंटेशन खोजें...',
    'no_results' => 'कोई नतीजा नहीं मिला।',
    'language' => 'भाषा',
    'toggle_theme' => 'थीम बदलें',
    'navigation' => 'मेनू',
    'no_pages' => 'अभी कोई पेज नहीं है।',
    'sitemap' => 'डॉक्यूमेंटेशन साइटमैप',

    // ── "On this page" rail ───────────────────────────────────────────────
    'on_this_page' => 'इस पेज पर',
    'no_sections' => 'इस पेज पर कोई सेक्शन नहीं है।',

    // ── Article ───────────────────────────────────────────────────────────
    'breadcrumb' => 'ब्रेडक्रंब',
    'breadcrumb_root' => 'गाइड',
    'previous' => '← पिछला',
    'next' => 'अगला →',
    'copy' => 'कॉपी करें',
    'copied' => 'कॉपी हो गया!',
    'reading_time' => ':minutes मिनट में पढ़ें',
    'last_updated' => 'आखिरी अपडेट: :date',

    // ── "Was this page helpful?" ──────────────────────────────────────────
    'helpful_question' => 'क्या यह पेज काम का लगा?',
    'helpful_yes' => 'हां',
    'helpful_no' => 'नहीं',
    'helpful_thanks' => 'फ़ीडबैक के लिए धन्यवाद!',
    'helpful_count_one' => ':count व्यक्ति को यह पेज काम का लगा।',
    'helpful_count_other' => ':count लोगों को यह पेज काम का लगा।',

    // ── Footer ────────────────────────────────────────────────────────────
    'made_with' => 'Magna Docs से बना',

    // ── Titles and the empty docs site ────────────────────────────────────
    'page_title' => ':title — डॉक्यूमेंटेशन',
    'documentation' => 'डॉक्यूमेंटेशन',
    'browse_documentation' => 'डॉक्यूमेंटेशन देखें।',
    'nothing_published' => 'अभी कोई डॉक्यूमेंटेशन पब्लिश नहीं हुआ है। पब्लिश होते ही पहला पेज होम पेज बन जाएगा।',
];
