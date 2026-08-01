<?php

declare(strict_types=1);

namespace Magna\Docs\Transfer;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Magna\Docs\Models\DocCollection;

/**
 * First import pass: collections, matched on slug.
 *
 * Writes go through the query builder, not Eloquent, on purpose. DocCollection
 * re-sequences every sibling's `order` on save — fine for one edit in the
 * admin, quadratic for an archive with hundreds of collections. The importer
 * inserts flat and the orchestrator calls DocCollection::resequence() once at
 * the end, which produces the same gapless ordering for a fraction of the work.
 *
 * Returns archive-slug => local-id for every collection the archive mentions,
 * including ones that were skipped, so pages still attach to the right place.
 */
final class CollectionImporter
{
    /** @var array<string, int> local slug => id */
    private array $existing = [];

    /** @var array<string, int> archive slug => local id */
    private array $map = [];

    /** @var list<array<string, mixed>> */
    private array $buffer = [];

    /** @var array<string, string> slug queued for insert => the archive slug it came from */
    private array $pending = [];

    /** @var array<string, true> archive slugs already handled this run */
    private array $seen = [];

    public function __construct(
        private readonly ImportOptions $options,
        private readonly ImportReport $report,
    ) {}

    /** @return array<string, int> */
    public function import(ArchiveReader $archive): array
    {
        /** @var array<string, int> $existing */
        $existing = DocCollection::query()->pluck('id', 'slug')->all();
        $this->existing = $existing;

        foreach ($archive->lines(DocsArchive::COLLECTIONS) as $number => $line) {
            if ($line === null) {
                $this->report->error("collections.ndjson line {$number}: not valid JSON — skipped.");

                continue;
            }

            $slug = SlugRules::clean($line['slug'] ?? null);

            if ($slug === null) {
                $this->report->error("collections.ndjson line {$number}: missing or invalid slug — skipped.");

                continue;
            }

            if (isset($this->seen[$slug])) {
                $this->report->error("collections.ndjson line {$number}: '{$slug}' appears more than once in the archive — only the first was imported.");

                continue;
            }

            $this->seen[$slug] = true;
            $this->handle($slug, $line);
        }

        $this->flush();

        return $this->map;
    }

    /**
     * @param  array<string, mixed>  $line
     */
    private function handle(string $slug, array $line): void
    {
        $attributes = $this->attributes($slug, $line);

        if (! isset($this->existing[$slug])) {
            $this->queueInsert($slug, $slug, $attributes);

            return;
        }

        match ($this->options->conflict) {
            ConflictStrategy::Skip => $this->skip($slug),
            ConflictStrategy::Update => $this->update($slug, $attributes),
            ConflictStrategy::Duplicate => $this->queueInsert($slug, $this->uniqueSlug($slug), $attributes),
        };
    }

    private function skip(string $slug): void
    {
        $this->map[$slug] = $this->existing[$slug];
        $this->report->add('collections_skipped');
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function update(string $slug, array $attributes): void
    {
        $id = $this->existing[$slug];
        $this->map[$slug] = $id;
        $this->report->add('collections_updated');

        if ($this->options->dryRun) {
            return;
        }

        unset($attributes['slug'], $attributes['created_at']);
        DB::table('doc_collections')->where('id', $id)->update($attributes);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function queueInsert(string $archiveSlug, string $slug, array $attributes): void
    {
        $attributes['slug'] = $slug;
        $this->buffer[] = $attributes;
        $this->pending[$slug] = $archiveSlug;
        $this->report->add('collections_created');

        if (count($this->buffer) >= DocsArchive::BATCH) {
            $this->flush();
        }
    }

    private function flush(): void
    {
        if ($this->buffer === []) {
            return;
        }

        if ($this->options->dryRun) {
            // Nothing is written, but reserve the slugs so a dry run reports the
            // same conflicts a real run would hit.
            foreach ($this->pending as $slug => $archiveSlug) {
                $this->existing[$slug] = 0;
                $this->map[$archiveSlug] = 0;
            }

            $this->buffer = [];
            $this->pending = [];

            return;
        }

        DB::table('doc_collections')->insert($this->buffer);

        /** @var array<string, int> $ids */
        $ids = DocCollection::query()
            ->whereIn('slug', array_keys($this->pending))
            ->pluck('id', 'slug')
            ->all();

        foreach ($this->pending as $slug => $archiveSlug) {
            if (isset($ids[$slug])) {
                $this->map[$archiveSlug] = $ids[$slug];
                $this->existing[$slug] = $ids[$slug];
            }
        }

        $this->buffer = [];
        $this->pending = [];
    }

    /**
     * @param  array<string, mixed>  $line
     * @return array<string, mixed>
     */
    private function attributes(string $slug, array $line): array
    {
        $title = trim((string) ($line['title'] ?? ''));
        $color = trim((string) ($line['color'] ?? ''));

        return [
            'slug' => $slug,
            'title' => Str::limit($title !== '' ? $title : Str::headline($slug), 255, ''),
            'description' => $this->nullableString($line['description'] ?? null),
            'icon' => Str::limit(trim((string) ($line['icon'] ?? '')) ?: 'book-open', 255, ''),
            'color' => preg_match('/^#[0-9A-Fa-f]{3,8}$/', $color) === 1 ? $color : '#6366f1',
            'order' => max(0, (int) ($line['order'] ?? 0)),
            'is_public' => (bool) ($line['is_public'] ?? true),
            'created_at' => now(),
            'updated_at' => now(),
        ];
    }

    private function nullableString(mixed $value): ?string
    {
        $value = is_scalar($value) ? trim((string) $value) : '';

        return $value === '' ? null : $value;
    }

    /** `guides` taken → `guides-2`, `guides-3`, … */
    private function uniqueSlug(string $slug): string
    {
        $suffix = 2;

        while (isset($this->existing[$slug.'-'.$suffix]) || isset($this->pending[$slug.'-'.$suffix])) {
            $suffix++;
        }

        return $slug.'-'.$suffix;
    }
}
