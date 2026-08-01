<?php

declare(strict_types=1);

namespace Magna\Docs\Transfer;

use Illuminate\Support\Facades\DB;

/**
 * Last import pass: re-attach the page tree.
 *
 * Pages are written parent-less first, because an archive can list a child
 * before its parent and a streaming importer cannot look ahead. Once every
 * page has an id, the recorded parent slugs are resolved — against pages from
 * this archive first, then against pages already on the site, so importing a
 * sub-tree into an existing manual can still hang it off the right parent.
 *
 * Cycles are rejected here rather than at render time: a hostile or corrupt
 * archive claiming A→B→A would otherwise be caught only by the loop guard in
 * DocPage::breadcrumb(), on every request, forever.
 */
final class ParentLinker
{
    public function __construct(private readonly ImportReport $report) {}

    /**
     * @param  array<string, string>  $links  child archive slug => parent archive slug
     * @param  array<string, int>  $imported  archive slug => local page id
     * @param  array<string, int>  $existing  local slug => local page id
     */
    public function link(array $links, array $imported, array $existing, bool $dryRun): void
    {
        if ($links === [] || $dryRun) {
            return;
        }

        /** @var array<int, int> child id => parent id */
        $edges = [];

        foreach ($links as $childSlug => $parentSlug) {
            $childId = $imported[$childSlug] ?? null;
            $parentId = $imported[$parentSlug] ?? $existing[$parentSlug] ?? null;

            if ($childId === null || $parentId === null || $childId === $parentId) {
                if ($childId !== null && $parentId === null) {
                    $this->report->error("Page '{$childSlug}' refers to a parent ('{$parentSlug}') that is not in the archive or on this site — imported at the top level.");
                }

                continue;
            }

            $edges[$childId] = $parentId;
        }

        $edges = $this->withoutCycles($edges);

        // Group children by parent so a 1000-page tree costs one UPDATE per
        // distinct parent, not one per page.
        $byParent = [];

        foreach ($edges as $childId => $parentId) {
            $byParent[$parentId][] = $childId;
        }

        foreach ($byParent as $parentId => $childIds) {
            foreach (array_chunk($childIds, DocsArchive::BATCH) as $chunk) {
                DB::table('docs_pages')->whereIn('id', $chunk)->update(['parent_id' => $parentId]);
            }
        }
    }

    /**
     * Drop any edge that would close a loop, keeping the rest of the tree.
     *
     * @param  array<int, int>  $edges
     * @return array<int, int>
     */
    private function withoutCycles(array $edges): array
    {
        foreach (array_keys($edges) as $childId) {
            $seen = [$childId => true];
            $cursor = $edges[$childId] ?? null;

            while ($cursor !== null) {
                if (isset($seen[$cursor])) {
                    $this->report->error("A parent/child loop in the archive was broken at page id {$childId}; it was imported at the top level.");
                    unset($edges[$childId]);

                    break;
                }

                $seen[$cursor] = true;
                $cursor = $edges[$cursor] ?? null;
            }
        }

        return $edges;
    }
}
