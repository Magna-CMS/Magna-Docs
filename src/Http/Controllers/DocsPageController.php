<?php

declare(strict_types=1);

namespace Magna\Docs\Http\Controllers;

use Illuminate\Contracts\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Magna\Docs\Models\DocPage;
use Magna\Docs\Models\DocPageTranslation;
use Magna\Docs\Support\DocTree;
use Magna\Docs\Support\MarkdownRenderer;
use Magna\Docs\Support\PublicMedia;
use Magna\Docs\Support\ReaderLocale;
use Magna\Docs\Support\TocGenerator;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class DocsPageController
{
    public function index(Request $request): Response
    {
        // VitePress-style: the docs home IS the first article, rendered at /docs.
        $firstSlug = DocTree::firstSlug();

        if ($firstSlug !== null) {
            $page = DocPage::query()
                ->where('slug', $firstSlug)
                ->where('status', 'published')
                ->first();

            if ($page !== null) {
                return $this->renderPage($request, $page, isHome: true);
            }
        }

        // Nothing published yet — minimal empty state. There is no content to
        // match, so the chrome simply follows the reader's chosen language.
        ReaderLocale::remember($request);
        $locale = ReaderLocale::preferred($request);

        return $this->respond(view('docs::pages.index', [
            'tree' => [],
            'currentSlug' => null,
            'currentLocale' => $locale,
            'preferredLocale' => $locale,
            'title' => __('docs::docs.documentation', [], $locale),
            'description' => __('docs::docs.browse_documentation', [], $locale),
            'canonical' => route('docs.web.index'),
        ]));
    }

    public function show(Request $request, string $slug): Response
    {
        $page = DocPage::query()
            ->where('slug', $slug)
            ->where('status', 'published')
            ->firstOrFail();

        return $this->renderPage($request, $page, isHome: false);
    }

    private function renderPage(Request $request, DocPage $page, bool $isHome): Response
    {
        /** @var list<string> $translated */
        $translated = $page->translations()->orderBy('locale')->pluck('locale')->all();
        $pageLocales = array_values(array_unique(['en', ...$translated]));

        // ?lang=xx (remembered in the docs_lang cookie), else the cookie, else
        // English — and only ever a language this page actually has.
        $locale = ReaderLocale::resolve($request, $pageLocales);
        ReaderLocale::remember($request);

        $translation = $locale !== 'en'
            ? $page->translations()->where('locale', $locale)->first()
            : null;

        $displayTitle = filled($translation?->title) ? (string) $translation->title : (string) $page->title;
        $displayContent = $translation !== null ? (string) $translation->content : (string) $page->content;
        // A translated page shows its own summary or none — never the English one.
        $displayExcerpt = $translation !== null ? $translation->excerpt : $page->excerpt;
        $displayExcerpt = filled($displayExcerpt) ? (string) $displayExcerpt : null;

        $updatedAt = $translation->updated_at ?? $page->updated_at ?? now();
        $stamp = $updatedAt->timestamp;
        $cacheKey = "docs:html:{$page->id}:{$locale}:{$stamp}";

        $html = Cache::remember(
            $cacheKey,
            now()->addDays(30),
            fn () => MarkdownRenderer::toHtml($displayContent),
        );

        $toc = TocGenerator::generate($html);
        $breadcrumb = $page->breadcrumb($locale);

        // Prev/next follow the sidebar tree order, so the sequence matches what
        // the reader sees in the navigation panel — titles in their language.
        $tree = DocTree::build($locale);
        $orderedPages = DocTree::flatPages($tree);
        $idx = array_search($page->slug, array_column($orderedPages, 'slug'), true);
        $prev = ($idx !== false && $idx > 0) ? $orderedPages[$idx - 1] : null;
        $next = ($idx !== false && $idx < count($orderedPages) - 1) ? $orderedPages[$idx + 1] : null;

        $meta = $translation ?? $page;
        $metaTitle = filled($meta->meta_title)
            ? (string) $meta->meta_title
            : __('docs::docs.page_title', ['title' => $displayTitle], $locale);
        $metaDescription = filled($meta->meta_description)
            ? (string) $meta->meta_description
            : ($displayExcerpt ?? str($displayContent)->stripTags()->limit(160)->toString());

        // Home renders at /docs and keeps that as its address.
        $baseUrl = $isHome ? route('docs.web.index') : route('docs.web.show', $page->slug);
        $canonical = self::localeUrl($baseUrl, $locale);

        // The featured image is the share image — unless the editor hid it from
        // the page, in which case it is not published anywhere and the
        // docs-wide share image (settings) stands in.
        $ogImage = ($page->show_featured_image ?? true) ? PublicMedia::url($page->featured_image) : null;

        return $this->respond(view('docs::pages.show', [
            'page' => $page,
            'displayTitle' => $displayTitle,
            'displayExcerpt' => $displayExcerpt,
            'pageLocales' => $pageLocales,
            'currentLocale' => $locale,
            'preferredLocale' => ReaderLocale::preferred($request),
            'alternates' => self::alternates($baseUrl, $pageLocales),
            'html' => $html,
            'toc' => $toc,
            'tree' => $tree,
            'currentSlug' => $page->slug,
            'breadcrumb' => $breadcrumb,
            'prev' => $prev,
            'next' => $next,
            'isHome' => $isHome,
            'readingMinutes' => $page->readingTimeMinutes($displayContent),
            'updatedAt' => $updatedAt,
            'helpfulCount' => (int) Cache::get("docs:feedback:{$page->slug}:yes", 0),
            'title' => $metaTitle,
            'description' => $metaDescription,
            'canonical' => $canonical,
            'ogImage' => $ogImage,
            'ogImageAlt' => $displayTitle,
            'jsonLd' => [
                json_encode(array_filter([
                    '@context' => 'https://schema.org',
                    '@type' => 'TechArticle',
                    'headline' => $displayTitle,
                    'description' => $metaDescription,
                    'inLanguage' => $locale,
                    'image' => $ogImage,
                    'dateModified' => $updatedAt->toAtomString(),
                    'mainEntityOfPage' => $canonical,
                ], fn (mixed $value): bool => $value !== null && $value !== ''), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG),
            ],
        ]));
    }

    /** The address of a page in one language: plain for English, `?lang=xx` otherwise. */
    private static function localeUrl(string $baseUrl, string $locale): string
    {
        return $locale === 'en' ? $baseUrl : $baseUrl.'?lang='.rawurlencode($locale);
    }

    /**
     * `<link rel="alternate" hreflang>` targets: one per language the page has,
     * plus x-default (English, the page itself). None for an English-only page.
     *
     * @param  list<string>  $pageLocales
     * @return list<array{hreflang: string, href: string}>
     */
    private static function alternates(string $baseUrl, array $pageLocales): array
    {
        if (count($pageLocales) <= 1) {
            return [];
        }

        $links = array_map(
            fn (string $locale): array => ['hreflang' => $locale, 'href' => self::localeUrl($baseUrl, $locale)],
            $pageLocales,
        );
        $links[] = ['hreflang' => 'x-default', 'href' => $baseUrl];

        return $links;
    }

    /**
     * Wrap a docs HTML view. Which language a page renders in depends on the
     * docs_lang cookie, so the response says so. Nothing in Magna caches these
     * pages (they leave Laravel as `private, no-cache`), but a CDN or proxy put
     * in front of a docs site must not hand one reader's language to another.
     */
    private function respond(View $view): Response
    {
        return response($view)->header('Vary', 'Cookie');
    }

    public function search(Request $request): JsonResponse
    {
        $q = trim((string) $request->input('q', ''));

        if (mb_strlen($q) < 2) {
            return response()->json([]);
        }

        // The reader's language: the layout passes it as ?lang and the cookie
        // covers anything else. Search reads it but never sets it.
        $locale = ReaderLocale::preferred($request);
        $translated = $locale !== 'en';
        $like = "%{$q}%";

        $query = DocPage::query()
            ->where('status', 'published')
            ->where(function (Builder $query) use ($like, $locale, $translated): void {
                $query->where('title', 'like', $like)
                    ->orWhere('excerpt', 'like', $like)
                    ->orWhere('content', 'like', $like);

                if ($translated) {
                    $query->orWhereHas('translations', fn (Builder $translation) => $translation
                        ->where('locale', $locale)
                        ->where(fn (Builder $match) => $match
                            ->where('title', 'like', $like)
                            ->orWhere('excerpt', 'like', $like)
                            ->orWhere('content', 'like', $like)));
                }
            });

        if ($translated) {
            $query->with(['translations' => fn (Builder $translation) => $translation
                ->where('locale', $locale)
                ->select(['id', 'doc_page_id', 'locale', 'title', 'excerpt'])])
                // A title hit in the reader's language ranks first.
                ->orderByDesc(DocPageTranslation::query()
                    ->selectRaw('COUNT(*)')
                    ->whereColumn('doc_page_id', 'docs_pages.id')
                    ->where('locale', $locale)
                    ->where('title', 'like', $like));
        }

        $query->orderByRaw('CASE WHEN title LIKE ? THEN 0 ELSE 1 END', [$like]);

        $results = $query->limit(8)->get(['id', 'title', 'slug', 'excerpt']);

        return response()->json($results->map(function (DocPage $page) use ($translated): array {
            /** @var DocPageTranslation|null $translation */
            $translation = $translated ? $page->translations->first() : null;
            $title = filled($translation?->title) ? $translation->title : $page->title;
            // A translated page offers its own snippet or none, like the page itself.
            $excerpt = $translation !== null ? $translation->excerpt : $page->excerpt;

            return [
                'title' => $title,
                'slug' => $page->slug,
                'excerpt' => filled($excerpt) ? Str::limit((string) $excerpt, 100) : null,
                'url' => route('docs.web.show', $page->slug),
            ];
        }));
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
