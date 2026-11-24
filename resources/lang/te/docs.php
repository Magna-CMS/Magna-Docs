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
    'skip_to_content' => 'విషయానికి వెళ్ళండి',
    'open_navigation' => 'నావిగేషన్ తెరవండి',
    'close' => 'మూసివేయండి',
    'home_link' => ':site హోమ్',
    'search_label' => 'డాక్యుమెంటేషన్ వెతకండి',
    'search_placeholder' => 'డాక్యుమెంటేషన్ వెతకండి... (\'/\' నొక్కండి)',
    'search_placeholder_short' => 'డాక్యుమెంటేషన్ వెతకండి...',
    'no_results' => 'ఫలితాలేమీ దొరకలేదు.',
    'language' => 'భాష',
    'toggle_theme' => 'థీమ్ మార్చండి',
    'navigation' => 'నావిగేషన్',
    'no_pages' => 'ఇంకా పేజీలు లేవు.',
    'sitemap' => 'డాక్స్ సైట్‌మ్యాప్',

    // ── "On this page" rail ───────────────────────────────────────────────
    'on_this_page' => 'ఈ పేజీలో',
    'no_sections' => 'ఈ పేజీలో విభాగాలేమీ లేవు.',

    // ── Article ───────────────────────────────────────────────────────────
    'breadcrumb' => 'బ్రెడ్‌క్రంబ్',
    'breadcrumb_root' => 'డాక్స్',
    'previous' => '← మునుపటిది',
    'next' => 'తదుపరి →',
    'copy' => 'కాపీ',
    'copied' => 'కాపీ అయింది!',
    'reading_time' => ':minutes నిమిషాలు చదవాలి',
    'last_updated' => 'చివరిగా :date నవీకరించబడింది',

    // ── "Was this page helpful?" ──────────────────────────────────────────
    'helpful_question' => 'ఈ పేజీ ఉపయోగకరంగా ఉందా?',
    'helpful_yes' => 'అవును',
    'helpful_no' => 'కాదు',
    'helpful_thanks' => 'మీ అభిప్రాయానికి ధన్యవాదాలు!',
    'helpful_count_one' => ':count వ్యక్తికి ఈ పేజీ ఉపయోగపడింది.',
    'helpful_count_other' => ':count మందికి ఈ పేజీ ఉపయోగపడింది.',

    // ── Footer ────────────────────────────────────────────────────────────
    'made_with' => 'Magna Docs తో రూపొందించబడింది',

    // ── Titles and the empty docs site ────────────────────────────────────
    'page_title' => ':title — డాక్యుమెంటేషన్',
    'documentation' => 'డాక్యుమెంటేషన్',
    'browse_documentation' => 'డాక్యుమెంటేషన్ చూడండి.',
    'nothing_published' => 'ఇంకా ఎలాంటి డాక్యుమెంటేషన్ ప్రచురించలేదు. ఒక పేజీ ప్రచురించగానే అదే హోమ్ పేజీ అవుతుంది.',
];
