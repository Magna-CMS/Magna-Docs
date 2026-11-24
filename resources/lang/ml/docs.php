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
    'skip_to_content' => 'പ്രധാന ഭാഗത്തേക്ക് പോവുക',
    'open_navigation' => 'മെനു തുറക്കുക',
    'close' => 'അടയ്ക്കുക',
    'home_link' => ':site ഹോം',
    'search_label' => 'സഹായ രേഖകളിൽ തിരയുക',
    'search_placeholder' => 'തിരയുക... (കീബോർഡിൽ \'/\' അമർത്തുക)',
    'search_placeholder_short' => 'തിരയുക...',
    'no_results' => 'വിവരങ്ങളൊന്നും കണ്ടെത്തിയില്ല.',
    'language' => 'ഭാഷ',
    'toggle_theme' => 'തീം മാറ്റുക',
    'navigation' => 'നാവിഗേഷൻ',
    'no_pages' => 'പേജുകളൊന്നും ലഭ്യമല്ല.',
    'sitemap' => 'സൈറ്റ്മാപ്പ്',

    // ── "On this page" rail ───────────────────────────────────────────────
    'on_this_page' => 'ഈ പേജിൽ',
    'no_sections' => 'ഈ പേജിൽ വിഭാഗങ്ങളൊന്നുമില്ല.',

    // ── Article ───────────────────────────────────────────────────────────
    'breadcrumb' => 'ബ്രെഡ്ക്രംബ്',
    'breadcrumb_root' => 'സഹായ രേഖകൾ',
    'previous' => '← മുമ്പത്തേത്',
    'next' => 'അടുത്തത് →',
    'copy' => 'പകർത്തുക',
    'copied' => 'പകർത്തി!',
    'reading_time' => ':minutes മിനിറ്റ് വായന',
    'last_updated' => 'അവസാനം പുതുക്കിയത് :date',

    // ── "Was this page helpful?" ──────────────────────────────────────────
    'helpful_question' => 'ഈ പേജ് ഉപകാരപ്രദമായിരുന്നോ?',
    'helpful_yes' => 'ഉവ്വ്',
    'helpful_no' => 'ഇല്ല',
    'helpful_thanks' => 'അഭിപ്രായത്തിന് നന്ദി!',
    'helpful_count_one' => ':count ആൾക്ക് ഈ പേജ് ഉപകാരപ്രദമായി.',
    'helpful_count_other' => ':count പേർക്ക് ഈ പേജ് ഉപകാരപ്രദമായി.',

    // ── Footer ────────────────────────────────────────────────────────────
    'made_with' => 'Magna Docs ഉപയോഗിച്ച് നിർമ്മിച്ചത്',

    // ── Titles and the empty docs site ────────────────────────────────────
    'page_title' => ':title — സഹായ രേഖകൾ',
    'documentation' => 'സഹായ രേഖകൾ',
    'browse_documentation' => 'സഹായ രേഖകൾ പരിശോധിക്കുക.',
    'nothing_published' => 'സഹായ രേഖകളൊന്നും ഇതുവരെ പ്രസിദ്ധീകരിച്ചിട്ടില്ല. ഒരു പേജ് പ്രസിദ്ധീകരിച്ചാലുടൻ അത് ഹോം പേജായി മാറും.',
];
