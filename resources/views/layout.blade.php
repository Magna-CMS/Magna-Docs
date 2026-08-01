@php
    $docsSettings  = \Magna\Docs\Settings\DocsSettings::get();
    $docsSiteName  = filled($docsSettings->site_name) ? $docsSettings->site_name : config('app.name', 'Docs');
    // Logo resolution: the docs logo if one is set, otherwise the bundled Magna
    // brand logo (public/magna-logo.svg). Defensive: only treat a non-empty
    // string path as a logo, and never let a bad value crash the whole site.
    $docsLogoPath = is_string($docsSettings->logo_path ?? null) ? trim($docsSettings->logo_path) : '';
    $docsLogoUrl = null;
    if ($docsLogoPath !== '') {
        try {
            $docsLogoUrl = \Illuminate\Support\Facades\Storage::disk('public')->url($docsLogoPath);
        } catch (\Throwable $e) {
            $docsLogoUrl = null;
        }
    }
    // Bundled Magna default when no docs logo is configured.
    $docsLogoUrl ??= asset('magna-logo.svg');
    $docsFaviconUrl = filled($docsSettings->favicon_path)
                       ? \Illuminate\Support\Facades\Storage::disk('public')->url($docsSettings->favicon_path)
                       : asset('favicon.svg');
    // Languages available for the CURRENT page (English + its translations).
    $pageLocales = $pageLocales ?? ['en'];
    $docsCurrentLocale = $currentLocale ?? 'en';
@endphp
<!DOCTYPE html>
<html lang="{{ request()->cookie('docs_lang', app()->getLocale()) }}" class="scroll-smooth">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <link rel="icon" href="{{ $docsFaviconUrl }}">

    {{-- ── SEO ────────────────────────────────────────────────────────────── --}}
    <title>{{ $title ?? $docsSiteName }}</title>
    <meta name="description" content="{{ $description ?? '' }}">
    <meta name="robots" content="index, follow">
    <link rel="canonical" href="{{ $canonical ?? url()->current() }}">
    <meta property="og:type" content="article">
    <meta property="og:title" content="{{ $title ?? $docsSiteName }}">
    <meta property="og:description" content="{{ $description ?? '' }}">
    <meta property="og:url" content="{{ $canonical ?? url()->current() }}">
    <meta property="og:site_name" content="{{ $docsSiteName }}">
    <meta name="twitter:card" content="summary">
    <link rel="alternate" type="application/xml" title="Docs sitemap" href="{{ route('docs.web.sitemap') }}">

    @isset($jsonLd)
        @foreach((array) $jsonLd as $ld)
            <script type="application/ld+json">{!! $ld !!}</script>
        @endforeach
    @endisset

    @isset($prev)<link rel="prefetch" href="{{ route('docs.web.show', $prev->slug) }}">@endisset
    @isset($next)<link rel="prefetch" href="{{ route('docs.web.show', $next->slug) }}">@endisset

    {{-- Apply saved theme before paint to avoid a flash --}}
    <script>
        if (localStorage.getItem('color-theme') === 'dark' ||
            (!('color-theme' in localStorage) && window.matchMedia('(prefers-color-scheme: dark)').matches)) {
            document.documentElement.classList.add('dark');
        }
    </script>

    {{-- Self-hosted, pre-compiled Tailwind + Prism, shipped inside the plugin and
         served by it — no third-party CDNs and no host build step. --}}
    <link rel="stylesheet" href="{{ \Magna\Docs\Http\Controllers\DocsPageController::assetUrl('docs.css') }}">
    <link rel="stylesheet" href="{{ \Magna\Docs\Http\Controllers\DocsPageController::assetUrl('prism-tomorrow.css') }}">

    <style>
        /* ── Page shell geometry ───────────────────────────────────────────────
           One place that decides how much of the viewport the docs actually use.
           Written as plain CSS (not Tailwind utilities) on purpose: docs.css is a
           pre-compiled stylesheet shipped with the plugin, so tuning these values
           must never require a Tailwind rebuild on the host.

           The old shell was max-w-7xl (1280px), which left ~320px of dead margin
           on each side of a 1920px screen. The shell is now 1600px (1728px on
           ultrawide) with fluid gutters, while the article column stays capped at
           a readable measure — extra width goes to the nav rails and the column
           gap, not to over-long lines of prose. */
        :root {
            --docs-shell-max: 100rem;                        /* 1600px */
            --docs-gutter: clamp(1rem, 2.2vw, 2.25rem);      /* 16px → 36px  */
            --docs-sidebar-w: 17rem;                         /* 272px */
            --docs-toc-w: 15rem;                             /* 240px */
            --docs-col-gap: clamp(1.75rem, 3vw, 3.5rem);
            --docs-content-max: 54rem;                       /* ~864px */
        }
        @media (min-width: 1800px) {
            :root { --docs-shell-max: 108rem; --docs-content-max: 58rem; }
        }
        .docs-shell { width: 100%; max-width: var(--docs-shell-max); margin-inline: auto; padding-inline: var(--docs-gutter); }
        @media (min-width: 768px) { .docs-searchbox { max-width: 34rem; } }
        @media (min-width: 1024px) {
            .docs-cols { gap: var(--docs-col-gap); }
            #left-sidebar { width: var(--docs-sidebar-w); }
            #docs-main { max-width: var(--docs-content-max); }
        }
        @media (min-width: 1280px) {
            /* margin-left:auto pushes any width left over after the article hits
               its measure cap into the gap, so the "On this page" rail stays
               pinned to the right edge instead of floating mid-page. */
            #right-sidebar { width: var(--docs-toc-w); margin-left: auto; }
        }

        ::-webkit-scrollbar { width: 6px; height: 6px; }
        ::-webkit-scrollbar-track { background: transparent; }
        ::-webkit-scrollbar-thumb { background: #ddd6fe; border-radius: 4px; }
        .dark ::-webkit-scrollbar-thumb { background: #2e2e33; border-radius: 4px; }

        .collapsible-content { transition: max-height .25s cubic-bezier(0,1,0,1); max-height: 0; overflow: hidden; }
        .collapsible-content.show { max-height: 2000px; transition: max-height .4s ease-in-out; }

        [x-cloak], .hidden { }
        [data-hidden] { display: none !important; }

        /* ── Sidebar nav links ─────────────────────────────────────────────── */
        .docs-nav-link{display:block;border-left:1px solid transparent;margin-left:-1px;padding:.25rem 0 .25rem 1rem;font-size:.875rem;color:#475569;transition:color .15s,border-color .15s}
        .dark .docs-nav-link{color:#a1a1aa}
        .docs-nav-link:hover{color:#0f172a}
        .dark .docs-nav-link:hover{color:#f4f4f5}
        .docs-nav-link.active{border-color:#8b5cf6;color:#8b5cf6;font-weight:600}

        /* ── TOC links ─────────────────────────────────────────────────────── */
        .docs-toc-link{display:block;border-left:1px solid transparent;margin-left:-1px;padding:.1rem 0 .1rem .75rem;font-size:.75rem;color:#64748b;transition:color .15s,border-color .15s}
        .dark .docs-toc-link{color:#a1a1aa}
        .docs-toc-link:hover{color:#0f172a}
        .dark .docs-toc-link:hover{color:#f4f4f5}
        .docs-toc-link.active{border-color:#8b5cf6;color:#8b5cf6;font-weight:500}
        .docs-toc-link.sub{padding-left:1.5rem}

        /* ── Search results dropdown ───────────────────────────────────────── */
        .docs-search-results{display:none;position:absolute;top:calc(100% + .5rem);left:0;right:0;z-index:60;
            background:#fff;border:1px solid #e2e8f0;border-radius:.75rem;box-shadow:0 12px 40px rgba(15,23,42,.14);overflow:hidden}
        .dark .docs-search-results{background:#161619;border-color:#27272a;box-shadow:0 12px 40px rgba(0,0,0,.5)}
        .docs-search-results.open{display:block}
        .docs-sr-item{display:block;padding:.6rem .9rem;border-bottom:1px solid #f1f5f9;text-decoration:none}
        .dark .docs-sr-item{border-color:#232327}
        .docs-sr-item:last-child{border-bottom:0}
        .docs-sr-item:hover,.docs-sr-item.focused{background:#f5f3ff}
        .dark .docs-sr-item:hover,.dark .docs-sr-item.focused{background:#1e1b2e}
        .docs-sr-title{font-size:.85rem;font-weight:600;color:#0f172a}
        .dark .docs-sr-title{color:#f4f4f5}
        .docs-sr-excerpt{font-size:.75rem;color:#64748b;margin-top:.1rem}
        .dark .docs-sr-excerpt{color:#a1a1aa}
        .docs-sr-empty{padding:.9rem;text-align:center;font-size:.82rem;color:#64748b}

        /* ── Language menu ─────────────────────────────────────────────────── */
        .docs-lang-menu{display:none;position:absolute;top:calc(100% + .5rem);right:0;min-width:170px;z-index:60;
            background:#fff;border:1px solid #e2e8f0;border-radius:.75rem;box-shadow:0 12px 40px rgba(15,23,42,.12);padding:.35rem}
        .dark .docs-lang-menu{background:#161619;border-color:#27272a;box-shadow:0 12px 40px rgba(0,0,0,.5)}
        .docs-lang-menu.open{display:block}
        .docs-lang-item{display:flex;align-items:center;gap:.6rem;width:100%;padding:.45rem .6rem;border:0;background:transparent;
            border-radius:.5rem;font-size:.83rem;color:#334155;cursor:pointer;text-align:left}
        .dark .docs-lang-item{color:#d4d4d8}
        .docs-lang-item:hover{background:#f5f3ff}
        .dark .docs-lang-item:hover{background:#1e1b2e}
        .docs-lang-item.active{color:#8b5cf6;font-weight:600}

        /* ── SPA loading bar ───────────────────────────────────────────────── */
        .docs-loadbar{position:fixed;top:0;left:0;right:0;height:2px;z-index:100;opacity:0;transition:opacity .2s;pointer-events:none;overflow:hidden}
        .docs-loadbar::after{content:'';position:absolute;top:0;left:0;height:100%;width:35%;background:linear-gradient(90deg,transparent,#8b5cf6,#a78bfa,transparent)}
        body.docs-loading .docs-loadbar{opacity:1}
        body.docs-loading .docs-loadbar::after{animation:docs-loadbar 900ms ease-in-out infinite}
        @keyframes docs-loadbar{from{transform:translateX(-120%)}to{transform:translateX(400%)}}
        #docs-main{transition:opacity .15s ease}
        body.docs-loading #docs-main{opacity:.5}

        /* ── Featured image ────────────────────────────────────────────────── */
        .docs-featured-image{width:100%;aspect-ratio:16/9;object-fit:cover;display:block;border-radius:1rem;
            border:1px solid #e2e8f0;margin:0 0 2rem;box-shadow:0 8px 24px rgba(15,23,42,.08)}
        .dark .docs-featured-image{border-color:#27272a;box-shadow:0 8px 24px rgba(0,0,0,.35)}

        /* ── Article typography (markdown output) ──────────────────────────── */
        .docs-prose{color:#334155;line-height:1.75;font-size:1rem}
        .dark .docs-prose{color:#d4d4d8}
        .docs-prose > *:first-child{margin-top:0}
        .docs-prose h2{font-size:1.5rem;font-weight:700;letter-spacing:-.01em;margin:2.5rem 0 .9rem;color:#0f172a;scroll-margin-top:5rem}
        .dark .docs-prose h2{color:#fff}
        .docs-prose h3{font-size:1.2rem;font-weight:650;margin:1.9rem 0 .6rem;color:#0f172a;scroll-margin-top:5rem}
        .dark .docs-prose h3{color:#fff}
        .docs-prose h4{font-size:1.02rem;font-weight:600;margin:1.4rem 0 .5rem;color:#0f172a}
        .dark .docs-prose h4{color:#f4f4f5}
        .docs-prose p{margin:1rem 0}
        .docs-prose a{color:#8b5cf6;font-weight:500;text-decoration:none}
        .docs-prose a:hover{text-decoration:underline}
        .docs-prose ul,.docs-prose ol{margin:1rem 0;padding-left:1.4rem}
        .docs-prose li{margin:.35rem 0}
        .docs-prose ul li{list-style:disc}
        .docs-prose ol li{list-style:decimal}
        .docs-prose blockquote{border-left:3px solid #8b5cf6;background:#f5f3ff;border-radius:0 .5rem .5rem 0;
            padding:.8rem 1.1rem;margin:1.5rem 0;color:#475569}
        .dark .docs-prose blockquote{background:rgba(139,92,246,.1);color:#d4d4d8}
        .docs-prose code:not(pre code){font-family:ui-monospace,'JetBrains Mono',monospace;font-size:.85em;
            background:#f1f5f9;border:1px solid #e2e8f0;color:#7c3aed;padding:.12em .4em;border-radius:.35rem}
        .dark .docs-prose code:not(pre code){background:#27272a;border-color:#3f3f46;color:#a78bfa}
        .docs-prose pre{margin:1.5rem 0;border-radius:.75rem;font-size:.875rem;box-shadow:0 8px 24px rgba(15,23,42,.1)}
        .docs-prose table{width:100%;border-collapse:collapse;margin:1.5rem 0;font-size:.9rem;border-radius:.6rem;overflow:hidden;border:1px solid #e2e8f0}
        .dark .docs-prose table{border-color:#27272a}
        .docs-prose th,.docs-prose td{border:1px solid #e2e8f0;padding:.55rem .85rem;text-align:left}
        .dark .docs-prose th,.dark .docs-prose td{border-color:#27272a}
        .docs-prose th{background:#f8fafc;font-weight:600}
        .dark .docs-prose th{background:#1c1c1f}
        .docs-prose img{max-width:100%;border-radius:.6rem}
        /* Heading permalink "#": hidden by default, faint on hover (no more trailing #) */
        .docs-prose .heading-permalink{opacity:0;margin-left:.4rem;color:#a78bfa;font-weight:400;text-decoration:none;transition:opacity .15s}
        .docs-prose h2:hover .heading-permalink,.docs-prose h3:hover .heading-permalink,.docs-prose h4:hover .heading-permalink{opacity:.55}

        /* Code copy button */
        .code-block-wrapper{position:relative}
        .copy-btn{position:absolute;top:.55rem;right:.55rem;padding:.2rem .6rem;font-size:.7rem;
            background:rgba(255,255,255,.08);color:#a1a1aa;border:1px solid rgba(255,255,255,.15);border-radius:.35rem;cursor:pointer;transition:all .15s}
        .copy-btn:hover{background:rgba(255,255,255,.18);color:#fff}
        .copy-btn.copied{color:#a78bfa;border-color:#a78bfa}

        .feedback-btn.voted{border-color:#8b5cf6 !important;color:#8b5cf6 !important;font-weight:600;pointer-events:none}
    </style>
</head>
<body class="bg-white text-slate-900 dark:bg-darkBg dark:text-zinc-100 antialiased font-sans transition-colors duration-200">

<div class="docs-loadbar" aria-hidden="true"></div>

<a href="#docs-main" class="sr-only focus:not-sr-only focus:fixed focus:top-2 focus:left-2 focus:z-[999] focus:rounded-lg focus:bg-brand focus:px-4 focus:py-2 focus:text-white">Skip to content</a>

{{-- ── HEADER ─────────────────────────────────────────────────────────────── --}}
<header class="sticky top-0 z-40 w-full border-b border-slate-200/80 bg-white/80 backdrop-blur-md dark:border-zinc-800/80 dark:bg-darkBg/80">
    <div class="docs-shell flex h-16 items-center justify-between gap-4">

        <button id="left-sidebar-toggle" class="lg:hidden p-2 text-slate-600 dark:text-zinc-400 hover:bg-slate-100 dark:hover:bg-zinc-900 rounded-xl transition-colors" aria-label="Open navigation">
            <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M4 6h16M4 12h16M4 18h16"/></svg>
        </button>

        {{-- Logo --}}
        <a href="{{ route('docs.web.index') }}" class="flex items-center gap-2.5 shrink-0 mr-auto lg:mr-0" aria-label="{{ $docsSiteName }} home">
            @if ($docsLogoUrl)
                <img src="{{ $docsLogoUrl }}" alt="{{ $docsSiteName }}" class="h-9 w-auto max-w-[150px] object-contain">
            @else
                <span class="flex h-9 w-9 items-center justify-center rounded-xl bg-gradient-to-br from-brand to-fuchsia-600 text-white font-extrabold text-xl shadow-md shadow-brand/20">{{ strtoupper(substr($docsSiteName, 0, 1)) }}</span>
            @endif
            <span class="text-xl font-extrabold tracking-tight bg-gradient-to-r from-slate-900 to-slate-700 dark:from-white dark:to-zinc-300 bg-clip-text text-transparent">{{ $docsSiteName }}</span>
        </a>

        {{-- Desktop search --}}
        <div class="docs-searchbox flex-1 max-w-md hidden md:block">
            <div class="relative group" id="searchWrap">
                <div class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3">
                    <svg class="h-4 w-4 text-slate-400 group-focus-within:text-brand transition-colors" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                </div>
                <input type="text" placeholder="Search documentation... ('/' to focus)" id="search-input" autocomplete="off"
                    class="w-full rounded-xl border border-slate-200 bg-slate-50/50 py-1.5 pl-9 pr-4 text-sm text-slate-900 placeholder-slate-400 focus:border-brand focus:bg-white focus:outline-none focus:ring-1 focus:ring-brand dark:border-zinc-800 dark:bg-zinc-900/40 dark:text-zinc-100 dark:placeholder-zinc-500 dark:focus:border-brand dark:focus:bg-zinc-900 transition-all">
                <div class="docs-search-results" id="search-results" role="listbox"></div>
            </div>
        </div>

        {{-- Right zone --}}
        <div class="flex items-center gap-3 shrink-0">
            {{-- Language switcher --}}
            {{-- Language switcher — only appears when the page has translations --}}
            <div class="relative" id="langWrap" @if(count($pageLocales) <= 1) style="display:none" @endif>
                <button id="langToggle" class="rounded-xl p-2 text-slate-500 hover:bg-slate-100 dark:text-zinc-400 dark:hover:bg-zinc-900 focus:outline-none transition-colors" aria-label="Language" aria-haspopup="true" aria-expanded="false">
                    <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="m10.5 21 5.25-11.25L21 21m-9-3h6M3.75 6.75h11.25M9 3.75v3M11.25 6.75c0 3.5-2.5 7.5-7.5 9"/></svg>
                </button>
                <div class="docs-lang-menu" id="langMenu" role="menu">
                    @foreach($pageLocales as $code)
                        <a class="docs-lang-item {{ $docsCurrentLocale === $code ? 'active' : '' }}" role="menuitem"
                           href="{{ url()->current() . ($code === 'en' ? '' : '?lang='.$code) }}">
                            <span aria-hidden="true">{{ \Magna\Docs\Support\DocLocales::flag($code) }}</span>
                            <span>{{ \Magna\Docs\Support\DocLocales::label($code) }}</span>
                            @if($docsCurrentLocale === $code)<svg class="ml-auto h-3 w-3" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="3"><polyline points="20 6 9 17 4 12"/></svg>@endif
                        </a>
                    @endforeach
                </div>
            </div>

            {{-- Theme toggle --}}
            <button id="theme-toggle" class="rounded-xl p-2 text-slate-500 hover:bg-slate-100 dark:text-zinc-400 dark:hover:bg-zinc-900 focus:outline-none transition-colors" aria-label="Toggle theme">
                <svg id="theme-toggle-sun" class="hidden h-5 w-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 3v1m0 16v1m9-9h-1M4 12H3m15.364-6.364l-.707.707M6.343 17.657l-.707.707m12.728 0l-.707-.707M6.343 6.343l-.707-.707m2.828 9.9a5 5 0 117.072 0 5 5 0 01-7.072 0z"/></svg>
                <svg id="theme-toggle-moon" class="hidden h-5 w-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M20.354 15.354A9 9 0 018.646 3.646 9.003 9.003 0 0012 21a9.003 9.003 0 008.354-5.646z"/></svg>
            </button>
        </div>
    </div>
</header>

{{-- Mobile search --}}
<div class="docs-shell p-4 border-b border-slate-200/60 bg-white/60 dark:bg-zinc-900/20 md:hidden">
    <div class="relative" id="searchWrapMobile">
        <input type="text" placeholder="Search documentation..." id="search-input-mobile" autocomplete="off"
            class="w-full rounded-xl border border-slate-200 bg-slate-50 py-2 px-4 text-sm text-slate-900 dark:border-zinc-800 dark:bg-zinc-900 dark:text-zinc-100 focus:outline-none focus:ring-1 focus:ring-brand">
        <div class="docs-search-results" id="search-results-mobile" role="listbox"></div>
    </div>
</div>

{{-- ── BODY ───────────────────────────────────────────────────────────────── --}}
<div class="docs-shell">
    <div class="docs-cols flex flex-col lg:flex-row gap-8">

        {{-- LEFT SIDEBAR --}}
        <div id="left-sidebar-backdrop" class="fixed inset-0 z-50 bg-slate-950/40 backdrop-blur-sm hidden lg:hidden"></div>
        <aside id="left-sidebar" class="fixed inset-y-0 left-0 z-50 w-72 bg-white dark:bg-darkBg p-6 shadow-2xl transform -translate-x-full transition-transform duration-300 lg:transition-none lg:transform-none lg:relative lg:w-64 lg:p-0 lg:pt-8 lg:z-0 lg:shadow-none lg:h-[calc(100vh-4rem)] lg:sticky lg:top-16 overflow-y-auto">
            <div class="flex items-center justify-between mb-6 lg:hidden">
                <span class="font-bold text-xs text-slate-400 uppercase tracking-widest">Navigation</span>
                <button id="left-sidebar-close" class="p-1.5 rounded-xl hover:bg-slate-100 dark:hover:bg-zinc-900 transition-colors"><svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg></button>
            </div>
            <nav class="space-y-4 pr-2" id="docs-sidebar-nav">
                @include('docs::partials.sidebar', ['nodes' => $tree ?? [], 'currentSlug' => $currentSlug ?? null])
            </nav>
        </aside>

        {{-- MAIN --}}
        <main class="flex-1 min-w-0 pt-6 lg:pt-8 pb-16" id="docs-main" tabindex="-1">
            @yield('content')
        </main>

        {{-- RIGHT SIDEBAR (TOC) --}}
        <div id="right-sidebar-backdrop" class="fixed inset-0 z-50 bg-slate-950/40 backdrop-blur-sm hidden xl:hidden"></div>
        <aside id="right-sidebar" class="fixed inset-y-0 right-0 z-50 w-72 bg-white dark:bg-darkBg p-6 shadow-2xl transform translate-x-full transition-transform duration-300 xl:transition-none xl:transform-none xl:relative xl:w-48 xl:p-0 xl:pt-8 xl:z-0 xl:shadow-none xl:h-[calc(100vh-4rem)] xl:sticky xl:top-16 overflow-y-auto">
            <div class="flex items-center justify-between mb-6 xl:hidden">
                <span class="font-bold text-xs text-slate-400 uppercase tracking-widest">On this page</span>
                <button id="right-sidebar-close" class="p-1.5 rounded-xl hover:bg-slate-100 dark:hover:bg-zinc-900 transition-colors"><svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg></button>
            </div>
            <div class="space-y-4">
                <h5 class="text-xs font-bold uppercase tracking-wider text-slate-900 dark:text-zinc-200 hidden xl:block">On this page</h5>
                <ul class="space-y-2.5 border-l border-slate-100 dark:border-zinc-800 ml-0.5" id="docs-toc-list">
                    @yield('toc')
                </ul>
            </div>
        </aside>

    </div>

    {{-- Footer — starts after the left sidebar column (aligned with the content) --}}
    <footer class="border-t border-slate-200 dark:border-zinc-800 py-6 mt-4 flex items-center justify-between gap-4 flex-wrap text-sm text-slate-500 dark:text-zinc-400">
        <span>{{ $docsSettings->copyright_text ?? '' }}</span>
        <a href="https://github.com/jish-44/Magna-Docs" target="_blank" rel="noopener" data-no-spa
           class="inline-flex items-center gap-2 font-medium hover:text-brand transition-colors">
            <svg class="h-[17px] w-[17px]" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M12 .5C5.7.5.5 5.7.5 12c0 5.1 3.3 9.4 7.9 10.9.6.1.8-.3.8-.6 0-.3 0-1 0-2-3.2.7-3.9-1.5-3.9-1.5-.5-1.3-1.3-1.7-1.3-1.7-1.1-.7.1-.7.1-.7 1.2.1 1.8 1.2 1.8 1.2 1 1.8 2.7 1.3 3.4 1 .1-.8.4-1.3.7-1.6-2.6-.3-5.3-1.3-5.3-5.8 0-1.3.5-2.3 1.2-3.1-.1-.3-.5-1.5.1-3.1 0 0 1-.3 3.3 1.2a11.4 11.4 0 0 1 6 0C17 4.6 18 4.9 18 4.9c.6 1.6.2 2.8.1 3.1.8.8 1.2 1.8 1.2 3.1 0 4.5-2.7 5.5-5.3 5.8.4.4.8 1.1.8 2.3 0 1.6 0 2.9 0 3.3 0 .3.2.7.8.6 4.6-1.5 7.9-5.8 7.9-10.9C23.5 5.7 18.3.5 12 .5Z"/></svg>
            Made with Magna Docs
        </a>
    </footer>
</div>

{{-- Mobile "On this page" trigger — minimal pill --}}
<button id="mobile-toc-fab" class="xl:hidden fixed bottom-5 right-5 z-40 inline-flex items-center gap-2 rounded-full border border-slate-200 dark:border-zinc-800 bg-white/90 dark:bg-darkCard/90 backdrop-blur px-3.5 py-2 text-xs font-medium text-slate-600 dark:text-zinc-300 shadow-sm hover:text-brand hover:border-brand focus:outline-none transition-colors" aria-label="On this page">
    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M4 6h16M4 12h16M4 18h7"/></svg>
    On this page
</button>

<script src="{{ \Magna\Docs\Http\Controllers\DocsPageController::assetUrl('prism.js') }}" defer></script>

<script>
// ── Theme toggle (class "dark" + localStorage 'color-theme') ───────────────────
(function () {
    const sun = document.getElementById('theme-toggle-sun');
    const moon = document.getElementById('theme-toggle-moon');
    function sync() {
        const dark = document.documentElement.classList.contains('dark');
        sun.classList.toggle('hidden', !dark);
        moon.classList.toggle('hidden', dark);
    }
    sync();
    document.getElementById('theme-toggle').addEventListener('click', () => {
        const dark = document.documentElement.classList.toggle('dark');
        localStorage.setItem('color-theme', dark ? 'dark' : 'light');
        sync();
    });
})();

// ── Drawers (mobile left nav + right TOC) ──────────────────────────────────────
(function () {
    const L = document.getElementById('left-sidebar'), LB = document.getElementById('left-sidebar-backdrop');
    const R = document.getElementById('right-sidebar'), RB = document.getElementById('right-sidebar-backdrop');
    const openL = () => { L.classList.remove('-translate-x-full'); LB.classList.remove('hidden'); };
    const closeL = () => { L.classList.add('-translate-x-full'); LB.classList.add('hidden'); };
    const openR = () => { R.classList.remove('translate-x-full'); RB.classList.remove('hidden'); };
    const closeR = () => { R.classList.add('translate-x-full'); RB.classList.add('hidden'); };
    document.getElementById('left-sidebar-toggle').addEventListener('click', openL);
    document.getElementById('left-sidebar-close').addEventListener('click', closeL);
    LB.addEventListener('click', closeL);
    document.getElementById('mobile-toc-fab').addEventListener('click', openR);
    document.getElementById('right-sidebar-close').addEventListener('click', closeR);
    RB.addEventListener('click', closeR);
    window.__docsCloseDrawers = () => { closeL(); closeR(); };
})();

// ── Sidebar accordion ──────────────────────────────────────────────────────────
function docsInitAccordions() {
    document.querySelectorAll('.sidebar-toggle-btn').forEach(btn => {
        if (btn.__bound) return; btn.__bound = true;
        btn.addEventListener('click', () => {
            const content = btn.nextElementSibling;
            const arrow = btn.querySelector('.arrow-icon');
            content.classList.toggle('show');
            arrow && arrow.classList.toggle('rotate-90', content.classList.contains('show'));
        });
    });
}

// ── Language menu ────────────────────────────────────────────────────────────────
(function () {
    const t = document.getElementById('langToggle'), m = document.getElementById('langMenu');
    t.addEventListener('click', e => { e.stopPropagation(); const o = m.classList.toggle('open'); t.setAttribute('aria-expanded', o); });
    document.addEventListener('click', () => { m.classList.remove('open'); t.setAttribute('aria-expanded', 'false'); });
})();

// ── Live search (desktop + mobile) ──────────────────────────────────────────────
function docsAttachSearch(input, results, wrap) {
    if (!input || input.__bound) return; input.__bound = true;
    let timer, focusIdx = -1;
    const esc = s => String(s).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;');
    const close = () => { results.innerHTML = ''; results.classList.remove('open'); };
    const render = items => {
        if (!items.length) { results.innerHTML = '<div class="docs-sr-empty">No results found.</div>'; results.classList.add('open'); return; }
        results.innerHTML = items.map(i => `<a class="docs-sr-item" href="${i.url}"><div class="docs-sr-title">${esc(i.title)}</div>${i.excerpt ? `<div class="docs-sr-excerpt">${esc(i.excerpt)}</div>` : ''}</a>`).join('');
        results.classList.add('open'); focusIdx = -1;
    };
    input.addEventListener('input', () => {
        clearTimeout(timer);
        const q = input.value.trim();
        if (q.length < 2) { close(); return; }
        timer = setTimeout(() => {
            fetch('{{ route("docs.web.search") }}?q=' + encodeURIComponent(q)).then(r => r.json()).then(render).catch(close);
        }, 200);
    });
    input.addEventListener('keydown', e => {
        const items = results.querySelectorAll('.docs-sr-item');
        if (e.key === 'Escape') { close(); input.blur(); return; }
        if (!items.length) return;
        if (e.key === 'ArrowDown') { e.preventDefault(); focusIdx = Math.min(focusIdx + 1, items.length - 1); items.forEach((el, i) => el.classList.toggle('focused', i === focusIdx)); }
        if (e.key === 'ArrowUp') { e.preventDefault(); focusIdx = Math.max(focusIdx - 1, 0); items.forEach((el, i) => el.classList.toggle('focused', i === focusIdx)); }
        if (e.key === 'Enter' && focusIdx >= 0) { e.preventDefault(); items[focusIdx].click(); }
    });
    document.addEventListener('click', e => { if (wrap && !wrap.contains(e.target)) close(); });
}
docsAttachSearch(document.getElementById('search-input'), document.getElementById('search-results'), document.getElementById('searchWrap'));
docsAttachSearch(document.getElementById('search-input-mobile'), document.getElementById('search-results-mobile'), document.getElementById('searchWrapMobile'));
document.addEventListener('keydown', e => {
    if (e.key === '/' && !/^(INPUT|TEXTAREA)$/.test(document.activeElement.tagName)) { e.preventDefault(); document.getElementById('search-input').focus(); }
});

// ── Content enhancers (re-run after each SPA navigation) ───────────────────────
function docsInitCopyButtons() {
    document.querySelectorAll('#docs-main pre').forEach(pre => {
        if (pre.parentNode.classList.contains('code-block-wrapper')) return;
        const w = document.createElement('div'); w.className = 'code-block-wrapper';
        pre.parentNode.insertBefore(w, pre); w.appendChild(pre);
        const btn = document.createElement('button'); btn.className = 'copy-btn'; btn.textContent = 'Copy';
        btn.addEventListener('click', () => {
            navigator.clipboard.writeText(pre.querySelector('code')?.innerText ?? pre.innerText).then(() => {
                btn.textContent = 'Copied!'; btn.classList.add('copied');
                setTimeout(() => { btn.textContent = 'Copy'; btn.classList.remove('copied'); }, 2000);
            });
        });
        w.appendChild(btn);
    });
}
function docsTocSpy() {
    const links = document.querySelectorAll('#docs-toc-list .docs-toc-link');
    if (!links.length) return;
    const heads = Array.from(document.querySelectorAll('#docs-main h2[id],#docs-main h3[id]'));
    const y = window.scrollY + 96;
    let cur = heads[0]?.id;
    heads.forEach(h => { if (h.offsetTop <= y) cur = h.id; });
    links.forEach(a => a.classList.toggle('active', a.getAttribute('href') === '#' + cur));
}
window.addEventListener('scroll', docsTocSpy, { passive: true });
function docsUpdateTocVisibility() {
    // The "On this page" rail stays visible on desktop (shows an empty-state note
    // when a page has no headings); only the mobile pill is hidden when there is
    // nothing to open.
    const fab = document.getElementById('mobile-toc-fab');
    const has = !!document.querySelector('#docs-toc-list .docs-toc-link');
    if (fab) fab.style.display = has ? '' : 'none';
}
function docsFeedback(vote) {
    const el = document.getElementById('docsFeedback'); if (!el) return;
    const slug = el.dataset.slug, key = 'docs_fb_' + slug;
    if (localStorage.getItem(key)) return;
    fetch('{{ route("docs.web.feedback") }}', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content ?? '',
        },
        body: JSON.stringify({ slug, vote }),
    })
        .then(r => r.json()).then(d => {
            if (d.ok) {
                document.querySelectorAll('.feedback-btn').forEach(b => b.classList.toggle('voted', b.dataset.vote === vote));
                const t = document.getElementById('feedbackThanks'); if (t) t.classList.remove('hidden');
                if (vote === 'yes') docsShowHelpfulCount(d.count);
                localStorage.setItem(key, vote);
            }
        }).catch(() => {});
}
function docsShowHelpfulCount(count) {
    const wrap = document.getElementById('feedbackCount');
    if (!wrap || typeof count !== 'number') return;
    const num = document.getElementById('feedbackCountNumber');
    const word = document.getElementById('feedbackCountWord');
    if (num) num.textContent = String(count);
    if (word) word.textContent = count === 1 ? 'person' : 'people';
    wrap.classList.remove('hidden');
}
function docsInitFeedback() {
    const el = document.getElementById('docsFeedback'); if (!el) return;
    const voted = localStorage.getItem('docs_fb_' + el.dataset.slug);
    if (voted) {
        document.querySelectorAll('.feedback-btn').forEach(b => b.classList.toggle('voted', b.dataset.vote === voted));
        const t = document.getElementById('feedbackThanks'); if (t) t.classList.remove('hidden');
    }
}
function docsEnhance() {
    // Isolate syntax highlighting: a Prism plugin error must never abort the
    // rest of the page enhancers (accordions, feedback, TOC).
    try { if (window.Prism) window.Prism.highlightAll(); } catch (e) { /* highlighting is non-critical */ }
    docsInitCopyButtons();
    docsInitAccordions();
    docsInitFeedback();
    docsUpdateTocVisibility();
    docsTocSpy();
}

// ── SPA navigation ──────────────────────────────────────────────────────────────
(function () {
    const basePath = new URL('{{ route("docs.web.index") }}', location.origin).pathname;
    function isSpaLink(a) {
        if (!a || a.target === '_blank' || a.hasAttribute('download') || a.dataset.noSpa !== undefined) return false;
        if (a.origin !== location.origin) return false;
        const href = a.getAttribute('href') || '';
        if (href.startsWith('#')) return false;
        return a.pathname === basePath || a.pathname.startsWith(basePath.replace(/\/?$/, '/'));
    }
    async function navigate(url, push) {
        document.body.classList.add('docs-loading');
        try {
            const res = await fetch(url, { headers: { 'X-Requested-With': 'fetch' } });
            if (!res.ok) throw new Error('bad status');
            const doc = new DOMParser().parseFromString(await res.text(), 'text/html');
            const nm = doc.getElementById('docs-main'); if (!nm) throw new Error('no content');
            document.getElementById('docs-main').innerHTML = nm.innerHTML;
            const nt = doc.getElementById('docs-toc-list'), ct = document.getElementById('docs-toc-list');
            if (ct) ct.innerHTML = nt ? nt.innerHTML : '';
            // Language switcher reflects the destination page's translations.
            const nlm = doc.getElementById('langMenu'), clm = document.getElementById('langMenu');
            if (clm && nlm) clm.innerHTML = nlm.innerHTML;
            const lw = document.getElementById('langWrap');
            if (lw && clm) lw.style.display = clm.querySelectorAll('a').length > 1 ? '' : 'none';
            const t = doc.querySelector('title'); if (t) document.title = t.textContent;
            const path = new URL(url, location.origin).pathname;
            const links = document.querySelectorAll('#docs-sidebar-nav .docs-nav-link');
            let matched = false;
            links.forEach(a => { const on = a.pathname === path; if (on) matched = true; a.classList.toggle('active', on); });
            if (!matched && path === basePath.replace(/\/$/, '') && links[0]) links[0].classList.add('active');
            if (push) history.pushState({ spa: true }, '', url);
            window.__docsCloseDrawers && window.__docsCloseDrawers();
            window.scrollTo({ top: 0 });
            docsEnhance();
        } catch (e) { location.href = url; }
        finally { document.body.classList.remove('docs-loading'); }
    }
    document.addEventListener('click', e => {
        if (e.defaultPrevented || e.button !== 0 || e.metaKey || e.ctrlKey || e.shiftKey || e.altKey) return;
        const a = e.target.closest('a'); if (!a) return;

        // In-page anchor (TOC / heading links): smooth-scroll only, never navigate.
        const raw = a.getAttribute('href') || '';
        if (raw.startsWith('#')) {
            e.preventDefault();
            if (raw.length > 1) {
                const el = document.getElementById(decodeURIComponent(raw.slice(1)));
                if (el) {
                    el.scrollIntoView({ behavior: 'smooth', block: 'start' });
                    history.replaceState(null, '', raw);
                }
            }
            window.__docsCloseDrawers && window.__docsCloseDrawers();
            return;
        }

        if (!isSpaLink(a)) return;
        e.preventDefault();
        if (a.href.split('#')[0] === location.href.split('#')[0]) {
            const hash = a.hash;
            if (hash) { const el = document.getElementById(decodeURIComponent(hash.slice(1))); if (el) el.scrollIntoView({ behavior: 'smooth', block: 'start' }); }
            return;
        }
        navigate(a.href, true);
    });
    window.addEventListener('popstate', () => navigate(location.href, false));
})();

if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', docsEnhance);
else docsEnhance();
</script>
</body>
</html>
