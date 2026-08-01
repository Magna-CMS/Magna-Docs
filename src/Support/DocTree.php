<?php

declare(strict_types=1);

namespace Magna\Docs\Support;

use Illuminate\Support\Collection;
use Magna\Docs\Models\DocCollection;
use Magna\Docs\Models\DocPage;

class DocTree
{
    /**
     * Build a sidebar tree grouped by collection, then by parent_id within each group.
     *
     * Returns:
     *   [
     *     ['title' => 'Collection Name', 'icon' => 'book-open', 'color' => '#6366f1', 'children' => [...pages...]],
     *     ...
     *     ['title' => 'Uncategorised', 'slug' => '...', 'children' => []],  // top-level only
     *   ]
     */
    public static function build(): array
    {
        $pages = DocPage::query()
            ->where('status', 'published')
            ->orderBy('order')
            ->get(['id', 'parent_id', 'collection_id', 'title', 'slug', 'order']);

        $collections = DocCollection::query()
            ->where('is_public', true)
            ->orderBy('order')
            ->get(['id', 'title', 'icon', 'color']);

        $nodes = [];

        foreach ($collections as $collection) {
            $collectionPages = $pages->where('collection_id', $collection->id);
            $children = self::nest($collectionPages, null);
            if ($children !== []) {
                $nodes[] = [
                    'title' => $collection->title,
                    'icon' => $collection->icon,
                    'color' => $collection->color,
                    'children' => $children,
                ];
            }
        }

        // Pages with no collection
        $uncategorised = $pages->whereNull('collection_id');
        $topLevel = self::nest($uncategorised, null);
        foreach ($topLevel as $page) {
            $nodes[] = $page;
        }

        return $nodes;
    }

    /**
     * Flat list of slugs in the same order as the sidebar tree.
     * Used by the controller to compute correct Prev / Next links.
     *
     * @return list<string>
     */
    public static function flatPageSlugs(): array
    {
        return self::slugsFromNodes(self::build());
    }

    /** @param array<int, array<string, mixed>> $nodes */
    private static function slugsFromNodes(array $nodes): array
    {
        $slugs = [];
        foreach ($nodes as $node) {
            if (! empty($node['slug'])) {
                $slugs[] = (string) $node['slug'];
            }
            if (! empty($node['children']) && is_array($node['children'])) {
                array_push($slugs, ...self::slugsFromNodes($node['children']));
            }
        }

        return $slugs;
    }

    /**
     * The slug of the first page in sidebar order — used as the docs "home"
     * page (VitePress-style), where the first article is the landing page.
     */
    public static function firstSlug(): ?string
    {
        return self::firstSlugIn(self::build());
    }

    /** @param array<int, array<string, mixed>> $nodes */
    private static function firstSlugIn(array $nodes): ?string
    {
        foreach ($nodes as $node) {
            if (! empty($node['slug'])) {
                return (string) $node['slug'];
            }

            if (! empty($node['children']) && is_array($node['children'])) {
                $slug = self::firstSlugIn($node['children']);
                if ($slug !== null) {
                    return $slug;
                }
            }
        }

        return null;
    }

    /** @param Collection<int,DocPage> $pages */
    private static function nest(Collection $pages, ?int $parentId): array
    {
        return $pages
            ->where('parent_id', $parentId)
            ->map(fn (DocPage $page) => [
                'title' => $page->title,
                'slug' => $page->slug,
                'children' => self::nest($pages, $page->id),
            ])
            ->values()
            ->all();
    }
}
