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
    'skip_to_content' => 'मजकुरावर जा',
    'open_navigation' => 'नेव्हिगेशन उघडा',
    'close' => 'बंद करा',
    'home_link' => ':site मुख्यपृष्ठ',
    'search_label' => 'मार्गदर्शकात शोधा',
    'search_placeholder' => 'मार्गदर्शकात शोधा... (\'/\' दाबून सर्चवर जा)',
    'search_placeholder_short' => 'मार्गदर्शकात शोधा...',
    'no_results' => 'कोणतेही निकाल सापडले नाहीत.',
    'language' => 'भाषा',
    'toggle_theme' => 'थीम बदला',
    'navigation' => 'नेव्हिगेशन',
    'no_pages' => 'अद्याप कोणतीही पाने नाहीत.',
    'sitemap' => 'मार्गदर्शकाचा नकाशा',

    // ── "On this page" rail ───────────────────────────────────────────────
    'on_this_page' => 'या पानावर',
    'no_sections' => 'या पानावर कोणतेही विभाग नाहीत.',

    // ── Article ───────────────────────────────────────────────────────────
    'breadcrumb' => 'ब्रेडक्रंब',
    'breadcrumb_root' => 'मार्गदर्शक',
    'previous' => '← मागील',
    'next' => 'पुढील →',
    'copy' => 'कॉपी करा',
    'copied' => 'कॉपी केले!',
    'reading_time' => ':minutes मिनिटांत वाचन',
    'last_updated' => 'शेवटचा बदल :date',

    // ── "Was this page helpful?" ──────────────────────────────────────────
    'helpful_question' => 'हे पान उपयुक्त ठरले का?',
    'helpful_yes' => 'होय',
    'helpful_no' => 'नाही',
    'helpful_thanks' => 'अभिप्रायाबद्दल धन्यवाद!',
    'helpful_count_one' => ':count व्यक्तीला हे पान उपयुक्त वाटले.',
    'helpful_count_other' => ':count व्यक्तींना हे पान उपयुक्त वाटले.',

    // ── Footer ────────────────────────────────────────────────────────────
    'made_with' => 'Magna Docs वर तयार केलेले',

    // ── Titles and the empty docs site ────────────────────────────────────
    'page_title' => ':title — मार्गदर्शक',
    'documentation' => 'मार्गदर्शक',
    'browse_documentation' => 'मार्गदर्शकातील पाने पहा.',
    'nothing_published' => 'अद्याप कोणतेही मार्गदर्शक प्रसिद्ध केलेले नाही. पान प्रसिद्ध झाल्यावर ते मुख्यपृष्ठ बनेल.',
];
