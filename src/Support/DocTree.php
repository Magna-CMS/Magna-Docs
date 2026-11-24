<?php

declare(strict_types=1);

namespace Magna\Docs\Support;

use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Magna\Docs\Models\DocCollection;
use Magna\Docs\Models\DocCollectionTranslation;
use Magna\Docs\Models\DocPage;
use Magna\Docs\Models\DocPageTranslation;

class DocTree
{
    /** Bumped by flush(); part of every cached tree's key. */
    private const VERSION_KEY = 'docs:tree:version';

    /** A stale entry is never served (its key changes); this only bounds how long one lingers. */
    private const TTL_HOURS = 24;

    /**
     * Build a sidebar tree grouped by collection, then by parent_id within each group.
     *
     * Titles are in $locale where a page or collection has a translation for
     * it, English otherwise. The result is cached per locale.
     *
     * Returns:
     *   [
     *     ['title' => 'Collection Name', 'icon' => 'book-open', 'color' => '#6366f1', 'children' => [...pages...]],
     *     ...
     *     ['title' => 'Uncategorised', 'slug' => '...', 'children' => []],  // top-level only
     *   ]
     *
     * @return list<array<string, mixed>>
     */
    public static function build(string $locale = 'en'): array
    {
        /** @var list<array<string, mixed>> $tree */
        $tree = Cache::remember(
            'docs:tree:'.$locale.':'.self::fingerprint(),
            now()->addHours(self::TTL_HOURS),
            fn (): array => self::compute($locale),
        );

        return $tree;
    }

    /**
     * Invalidate every cached tree. Called by the models on save/delete and by
     * the importer, which writes through the query builder.
     */
    public static function flush(): void
    {
        Cache::forever(self::VERSION_KEY, Str::random(12));
    }

    /**
     * Flat list of slugs in the same order as the sidebar tree.
     * Used by the controller to compute correct Prev / Next links.
     *
     * @return list<string>
     */
    public static function flatPageSlugs(string $locale = 'en'): array
    {
        return array_column(self::flatPages(self::build($locale)), 'slug');
    }

    /**
     * Every page in a built tree, in sidebar order, with its (translated) title.
     *
     * @param  array<int, array<string, mixed>>  $nodes
     * @return list<array{slug: string, title: string}>
     */
    public static function flatPages(array $nodes): array
    {
        $pages = [];

        foreach ($nodes as $node) {
            if (! empty($node['slug'])) {
                $pages[] = ['slug' => (string) $node['slug'], 'title' => (string) ($node['title'] ?? '')];
            }

            if (! empty($node['children']) && is_array($node['children'])) {
                array_push($pages, ...self::flatPages($node['children']));
            }
        }

        return $pages;
    }

    /**
     * The slug of the first page in sidebar order — used as the docs "home"
     * page (VitePress-style), where the first article is the landing page.
     */
    public static function firstSlug(): ?string
    {
        return self::flatPages(self::build())[0]['slug'] ?? null;
    }

    /** @return list<array<string, mixed>> */
    private static function compute(string $locale): array
    {
        $pages = DocPage::query()
            ->where('status', 'published')
            ->orderBy('order')
            ->get(['id', 'parent_id', 'collection_id', 'title', 'slug', 'order']);

        $collections = DocCollection::query()
            ->where('is_public', true)
            ->orderBy('order')
            ->get(['id', 'title', 'icon', 'color']);

        $pageTitles = [];
        $collectionTitles = [];

        if ($locale !== 'en') {
            /** @var array<int, string> $pageTitles */
            $pageTitles = DocPageTranslation::query()
                ->where('locale', $locale)
                ->where('title', '!=', '')
                ->pluck('title', 'doc_page_id')
                ->all();

            /** @var array<int, string> $collectionTitles */
            $collectionTitles = DocCollectionTranslation::query()
                ->where('locale', $locale)
                ->where('title', '!=', '')
                ->pluck('title', 'doc_collection_id')
                ->all();
        }

        // Grouped once up front: filtering the whole page list for every node
        // made the build quadratic in the number of pages.
        $byParent = $pages->groupBy(fn (DocPage $page): string => (string) $page->parent_id);

        $nodes = [];

        foreach ($collections as $collection) {
            $children = self::nest($byParent, null, (int) $collection->id, $pageTitles);
            if ($children !== []) {
                $nodes[] = [
                    'title' => $collectionTitles[$collection->id] ?? $collection->title,
                    'icon' => $collection->icon,
                    'color' => $collection->color,
                    'children' => $children,
                ];
            }
        }

        // Pages with no collection
        foreach (self::nest($byParent, null, null, $pageTitles) as $page) {
            $nodes[] = $page;
        }

        return $nodes;
    }

    /**
     * Children of $parentId that belong to $collectionId (null = uncategorised).
     *
     * @param  Collection<array-key, EloquentCollection<int, DocPage>>  $byParent  pages keyed by (string) parent_id
     * @param  array<int, string>  $titles  page id => translated title
     * @return list<array<string, mixed>>
     */
    private static function nest(Collection $byParent, ?int $parentId, ?int $collectionId, array $titles): array
    {
        $nodes = [];

        foreach ($byParent->get((string) $parentId, collect()) as $page) {
            $pageCollection = $page->collection_id === null ? null : (int) $page->collection_id;

            if ($pageCollection !== $collectionId) {
                continue;
            }

            $nodes[] = [
                'title' => $titles[$page->id] ?? $page->title,
                'slug' => $page->slug,
                'children' => self::nest($byParent, (int) $page->id, $collectionId, $titles),
            ];
        }

        return $nodes;
    }

    /**
     * Cheap summary of everything the tree is built from. Edits through
     * Eloquent bump the version (see flush()); writes that bypass model events
     * — the admin table's drag-to-reorder, the importer's batched inserts —
     * still change a count or an updated_at, so they invalidate too.
     */
    private static function fingerprint(): string
    {
        $parts = [(string) Cache::get(self::VERSION_KEY, '0')];

        foreach (['docs_pages', 'doc_collections', 'docs_page_translations', 'docs_collection_translations'] as $table) {
            $row = DB::table($table)->selectRaw('COUNT(*) AS c, MAX(updated_at) AS u')->first();
            $parts[] = $row === null ? '-' : ((string) ($row->c ?? '0')).'@'.((string) ($row->u ?? ''));
        }

        return md5(implode('|', $parts));
    }
}
