<?php

declare(strict_types=1);

namespace Magna\Docs\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\View\View;
use Magna\Docs\Models\DocPage;
use Magna\Docs\Support\DocTree;
use Magna\Docs\Support\MarkdownRenderer;
use Magna\Docs\Support\TocGenerator;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class DocsPageController
{
    public function index(): View
    {
        // VitePress-style: the docs home IS the first article, rendered at /docs.
        $firstSlug = DocTree::firstSlug();

        if ($firstSlug !== null) {
            $page = DocPage::query()
                ->where('slug', $firstSlug)
                ->where('status', 'published')
                ->first();

            if ($page !== null) {
                return $this->renderPage($page, isHome: true);
            }
        }

        // Nothing published yet — minimal empty state.
        return view('docs::pages.index', [
            'tree' => [],
            'currentSlug' => null,
            'title' => 'Documentation',
            'description' => 'Browse the documentation.',
            'canonical' => route('docs.web.index'),
        ]);
    }

    public function show(string $slug): View
    {
        $page = DocPage::query()
            ->where('slug', $slug)
            ->where('status', 'published')
            ->firstOrFail();

        return $this->renderPage($page, isHome: false);
    }

    private function renderPage(DocPage $page, bool $isHome): View
    {
        // Locale-aware content: ?lang=xx renders that translation (falls back to English).
        $requested = (string) request()->query('lang', 'en');
        $translation = ($requested !== 'en')
            ? $page->translations()->where('locale', $requested)->first()
            : null;
        $locale = $translation !== null ? $requested : 'en';

        $displayTitle = ($translation !== null && filled($translation->title)) ? $translation->title : $page->title;
        $displayContent = $translation !== null ? (string) $translation->content : (string) $page->content;

        $stamp = ($translation?->updated_at ?? $page->updated_at)->timestamp;
        $cacheKey = "docs:html:{$page->id}:{$locale}:{$stamp}";

        $html = Cache::remember(
            $cacheKey,
            now()->addDays(30),
            fn () => MarkdownRenderer::toHtml($displayContent),
        );

        $pageLocales = array_merge(
            ['en'],
            $page->translations()->orderBy('locale')->pluck('locale')->all(),
        );

        $toc = TocGenerator::generate($html);
        $breadcrumb = $page->breadcrumb();

        // Build prev/next from the sidebar tree order so the sequence matches
        // what the reader sees in the navigation panel.
        $allPagesMap = DocPage::query()
            ->where('status', 'published')
            ->get(['id', 'title', 'slug'])
            ->keyBy('slug');

        $orderedPages = collect(DocTree::flatPageSlugs())
            ->map(fn (string $slug) => $allPagesMap->get($slug))
            ->filter()
            ->values();

        $idx = $orderedPages->search(fn ($p) => $p->slug === $page->slug);
        $prev = ($idx > 0) ? $orderedPages[$idx - 1] : null;
        $next = ($idx !== false && $idx < $orderedPages->count() - 1) ? $orderedPages[$idx + 1] : null;

        $metaTitle = $page->meta_title ?: $displayTitle.' — Documentation';
        $metaDescription = $page->meta_description
            ?: ($page->excerpt ?? str($displayContent)->stripTags()->limit(160)->toString());

        return view('docs::pages.show', [
            'page' => $page,
            'displayTitle' => $displayTitle,
            'pageLocales' => $pageLocales,
            'currentLocale' => $locale,
            'html' => $html,
            'toc' => $toc,
            'tree' => DocTree::build(),
            'currentSlug' => $page->slug,
            'breadcrumb' => $breadcrumb,
            'prev' => $prev,
            'next' => $next,
            'isHome' => $isHome,
            'helpfulCount' => (int) Cache::get("docs:feedback:{$page->slug}:yes", 0),
            'title' => $metaTitle,
            'description' => $metaDescription,
            // Home renders at /docs but points canonical at the article's own URL.
            'canonical' => $isHome ? route('docs.web.index') : route('docs.web.show', $page->slug),
            'jsonLd' => [
                json_encode([
                    '@context' => 'https://schema.org',
                    '@type' => 'TechArticle',
                    'headline' => $page->title,
                    'description' => $page->excerpt,
                    'dateModified' => $page->updated_at->toAtomString(),
                    'mainEntityOfPage' => route('docs.web.show', $page->slug),
                ]),
            ],
        ]);
    }

    public function search(Request $request): JsonResponse
    {
        $q = trim((string) $request->input('q', ''));

        if (strlen($q) < 2) {
            return response()->json([]);
        }

        $results = DocPage::query()
            ->where('status', 'published')
            ->where(function ($query) use ($q): void {
                $query->where('title', 'like', "%{$q}%")
                    ->orWhere('excerpt', 'like', "%{$q}%")
                    ->orWhere('content', 'like', "%{$q}%");
            })
            ->orderByRaw('CASE WHEN title LIKE ? THEN 0 ELSE 1 END', ["%{$q}%"])
            ->limit(8)
            ->get(['id', 'title', 'slug', 'excerpt']);

        return response()->json($results->map(fn (DocPage $page) => [
            'title' => $page->title,
            'slug' => $page->slug,
            'excerpt' => $page->excerpt ? str($page->excerpt)->limit(100)->toString() : null,
            'url' => route('docs.web.show', $page->slug),
        ]));
    }

    public function feedback(Request $request): JsonResponse
    {
        // Feedback is stored in cache keyed by page slug + vote.
        // Lightweight: no DB table needed for simple thumbs up/down counts.
        $slug = (string) $request->input('slug', '');
        $vote = $request->input('vote'); // 'yes' | 'no'

        if (! in_array($vote, ['yes', 'no'], true)) {
            return response()->json(['ok' => false], 422);
        }

        // Only accept feedback for a real published page — prevents unbounded,
        // attacker-controlled cache keys from being created via the slug.
        $isPublishedPage = DocPage::query()
            ->where('slug', $slug)
            ->where('status', 'published')
            ->exists();

        if (! $isPublishedPage) {
            return response()->json(['ok' => false], 422);
        }

        $key = "docs:feedback:{$slug}:{$vote}";
        $count = (int) Cache::get($key, 0) + 1;
        Cache::put($key, $count, now()->addYear());

        return response()->json(['ok' => true, 'count' => $count]);
    }

    /**
     * Serve the plugin's own pre-compiled front-end assets (Tailwind CSS + Prism).
     *
     * These ship inside the plugin already built, so installing the plugin needs
     * no `npm`/build step on the host — matching the "install and go" model. A
     * strict filename whitelist means no user input ever reaches the filesystem.
     */
    /** Filenames this endpoint is allowed to serve. */
    private const ASSET_TYPES = [
        'docs.css' => 'text/css',
        'prism.js' => 'text/javascript',
        'prism-tomorrow.css' => 'text/css',
    ];

    /**
     * Cache-busting URL for a plugin asset — appends the file's mtime so a
     * rebuilt asset is fetched fresh instead of a stale cached copy.
     */
    public static function assetUrl(string $file): string
    {
        $path = dirname(__DIR__, 3).'/public/'.$file;
        $version = is_file($path) ? (string) filemtime($path) : null;

        return route('docs.web.asset', $file).($version !== null ? '?v='.$version : '');
    }

    public function asset(string $file): BinaryFileResponse
    {
        $types = self::ASSET_TYPES;

        $path = dirname(__DIR__, 3).'/public/'.$file;

        abort_unless(isset($types[$file]) && is_file($path), 404);

        return response()->file($path, [
            'Content-Type' => $types[$file],
            // Long-lived but revalidated (response()->file sets Last-Modified),
            // so a plugin update re-serves fresh content.
            'Cache-Control' => 'public, max-age=86400, must-revalidate',
        ]);
    }

    public function sitemap(): Response
    {
        $pages = DocPage::query()
            ->where('status', 'published')
            ->get(['slug', 'updated_at']);

        $urls = $pages->map(fn (DocPage $page) => sprintf(
            '<url><loc>%s</loc><lastmod>%s</lastmod></url>',
            e(route('docs.web.show', $page->slug)),
            $page->updated_at->toAtomString(),
        ))->implode('');

        $xml = '<?xml version="1.0" encoding="UTF-8"?>'
            .'<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">'
            .$urls
            .'</urlset>';

        return response($xml, 200, ['Content-Type' => 'application/xml']);
    }
}
