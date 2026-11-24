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
    'skip_to_content' => 'মূল লেখায় যান',
    'open_navigation' => 'নেভিগেশন খুলুন',
    'close' => 'বন্ধ করুন',
    'home_link' => ':site হোম',
    'search_label' => 'ডকুমেন্টেশন খুঁজুন',
    'search_placeholder' => 'ডকুমেন্টেশন খুঁজুন... (\'/\' চাপুন)',
    'search_placeholder_short' => 'ডকুমেন্টেশন খুঁজুন...',
    'no_results' => 'কিছু পাওয়া যায়নি।',
    'language' => 'ভাষা',
    'toggle_theme' => 'থিম বদলান',
    'navigation' => 'নেভিগেশন',
    'no_pages' => 'এখনো কোনো পাতা নেই।',
    'sitemap' => 'ডক্স সাইটম্যাপ',

    // ── "On this page" rail ───────────────────────────────────────────────
    'on_this_page' => 'এই পাতায় যা আছে',
    'no_sections' => 'এই পাতায় কোনো সেকশন নেই।',

    // ── Article ───────────────────────────────────────────────────────────
    'breadcrumb' => 'ব্রেডক্রাম্ব',
    'breadcrumb_root' => 'ডক্স',
    'previous' => '← আগের পাতা',
    'next' => 'পরের পাতা →',
    'copy' => 'কপি',
    'copied' => 'কপি হয়েছে!',
    'reading_time' => ':minutes মিনিটে পড়া যাবে',
    'last_updated' => 'সর্বশেষ আপডেট :date',

    // ── "Was this page helpful?" ──────────────────────────────────────────
    'helpful_question' => 'এই পাতাটি কি কাজে লেগেছে?',
    'helpful_yes' => 'হ্যাঁ',
    'helpful_no' => 'না',
    'helpful_thanks' => 'মতামতের জন্য ধন্যবাদ!',
    'helpful_count_one' => ':count জনের পাতাটি কাজে লেগেছে।',
    'helpful_count_other' => ':count জনের পাতাটি কাজে লেগেছে।',

    // ── Footer ────────────────────────────────────────────────────────────
    'made_with' => 'Magna Docs দিয়ে তৈরি',

    // ── Titles and the empty docs site ────────────────────────────────────
    'page_title' => ':title — ডকুমেন্টেশন',
    'documentation' => 'ডকুমেন্টেশন',
    'browse_documentation' => 'ডকুমেন্টেশন দেখুন।',
    'nothing_published' => 'এখনো কোনো ডকুমেন্টেশন প্রকাশিত হয়নি। কোনো পাতা প্রকাশিত হলে সেটিই হোম পেজ হবে।',
];
