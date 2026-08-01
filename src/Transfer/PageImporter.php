<?php

declare(strict_types=1);

namespace Magna\Docs\Transfer;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Magna\Docs\Models\DocPage;
use Throwable;

/**
 * Second import pass: the pages themselves, streamed a line at a time and
 * written in batches.
 *
 * Everything here is shaped by the "thousands of pages" requirement:
 * inserts are buffered into DocsArchive::BATCH-sized statements, ids are
 * resolved per batch (not per row), translations are handed to a buffered
 * writer, and parent links are deferred to ParentLinker so the stream never
 * needs to look ahead for a page it has not read yet.
 */
final class PageImporter
{
    /** @var array<string, int> local slug => id */
    private array $existing = [];

    /** @var array<string, int> archive slug => local page id */
    private array $map = [];

    /** @var array<string, string> child archive slug => parent archive slug */
    private array $links = [];

    /** @var list<array<string, mixed>> */
    private array $buffer = [];

    /** @var array<string, string> slug queued for insert => archive slug */
    private array $pending = [];

    /** @var array<string, list<array<string, mixed>>> archive slug => its translation records */
    private array $pendingTranslations = [];

    /** @var array<string, true> archive slugs already handled this run */
    private array $seen = [];

    /**
     * @param  array<string, int>  $collections  archive collection slug => local collection id
     */
    public function __construct(
        private readonly ImportOptions $options,
        private readonly ImportReport $report,
        private readonly MediaMap $media,
        private readonly TranslationImporter $translations,
        private readonly array $collections,
    ) {}

    public function import(ArchiveReader $archive): void
    {
        /** @var array<string, int> $existing */
        $existing = DocPage::query()->pluck('id', 'slug')->all();
        $this->existing = $existing;

        foreach ($archive->lines(DocsArchive::PAGES) as $number => $line) {
            if ($line === null) {
                $this->report->error("pages.ndjson line {$number}: not valid JSON — skipped.");

                continue;
            }

            $slug = SlugRules::clean($line['slug'] ?? null);

            if ($slug === null) {
                $this->report->error("pages.ndjson line {$number}: missing or invalid slug — skipped.");

                continue;
            }

            if (isset($this->seen[$slug])) {
                $this->report->error("pages.ndjson line {$number}: '{$slug}' appears more than once in the archive — only the first was imported.");

                continue;
            }

            $this->seen[$slug] = true;
            $this->handle($slug, $line);
        }

        $this->flush();
        $this->translations->flush();
    }

    /** @return array<string, int> archive slug => local page id */
    public function map(): array
    {
        return $this->map;
    }

    /** @return array<string, string> child archive slug => parent archive slug */
    public function links(): array
    {
        return $this->links;
    }

    /** @return array<string, int> local slug => id, as it was before the import */
    public function existing(): array
    {
        return $this->existing;
    }

    // ── Per-record decisions ─────────────────────────────────────────────────

    /**
     * @param  array<string, mixed>  $line
     */
    private function handle(string $slug, array $line): void
    {
        $parent = SlugRules::clean($line['parent'] ?? null);

        if ($parent !== null && $parent !== $slug) {
            $this->links[$slug] = $parent;
        }

        $attributes = $this->attributes($slug, $line);
        /** @var list<array<string, mixed>> $translations */
        $translations = array_values(array_filter(
            (array) ($line['translations'] ?? []),
            static fn (mixed $row): bool => is_array($row),
        ));

        if (! isset($this->existing[$slug])) {
            $this->queueInsert($slug, $slug, $attributes, $translations);

            return;
        }

        match ($this->options->conflict) {
            ConflictStrategy::Skip => $this->skip($slug),
            ConflictStrategy::Update => $this->update($slug, $attributes, $translations),
            ConflictStrategy::Duplicate => $this->queueInsert($slug, $this->uniqueSlug($slug), $attributes, $translations),
        };
    }

    private function skip(string $slug): void
    {
        // Still mapped: a skipped page is a perfectly good parent for the
        // children that follow it.
        $this->map[$slug] = $this->existing[$slug];
        $this->report->add('pages_skipped');
        unset($this->links[$slug]);
    }

    /**
     * @param  array<string, mixed>  $attributes
     * @param  list<array<string, mixed>>  $translations
     */
    private function update(string $slug, array $attributes, array $translations): void
    {
        $id = $this->existing[$slug];
        $this->map[$slug] = $id;
        $this->report->add('pages_updated');

        if (! $this->options->dryRun) {
            unset($attributes['slug'], $attributes['created_at']);
            DB::table('docs_pages')->where('id', $id)->update($attributes);
        }

        $this->translations->queue($id, $translations);
    }

    /**
     * @param  array<string, mixed>  $attributes
     * @param  list<array<string, mixed>>  $translations
     */
    private function queueInsert(string $archiveSlug, string $slug, array $attributes, array $translations): void
    {
        $attributes['slug'] = $slug;
        $this->buffer[] = $attributes;
        $this->pending[$slug] = $archiveSlug;
        $this->pendingTranslations[$archiveSlug] = $translations;
        $this->report->add('pages_created');

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
            foreach ($this->pending as $slug => $archiveSlug) {
                $this->existing[$slug] = 0;
                $this->map[$archiveSlug] = 0;
                $this->translations->queue(0, $this->pendingTranslations[$archiveSlug] ?? []);
            }

            $this->reset();

            return;
        }

        DB::table('docs_pages')->insert($this->buffer);

        /** @var array<string, int> $ids */
        $ids = DocPage::query()
            ->whereIn('slug', array_keys($this->pending))
            ->pluck('id', 'slug')
            ->all();

        foreach ($this->pending as $slug => $archiveSlug) {
            if (! isset($ids[$slug])) {
                continue;
            }

            $this->map[$archiveSlug] = $ids[$slug];
            $this->existing[$slug] = $ids[$slug];
            $this->translations->queue($ids[$slug], $this->pendingTranslations[$archiveSlug] ?? []);
        }

        $this->reset();
    }

    private function reset(): void
    {
        $this->buffer = [];
        $this->pending = [];
        $this->pendingTranslations = [];
    }

    // ── Field mapping ────────────────────────────────────────────────────────

    /**
     * @param  array<string, mixed>  $line
     * @return array<string, mixed>
     */
    private function attributes(string $slug, array $line): array
    {
        $collectionSlug = SlugRules::clean($line['collection'] ?? null);
        $collectionId = $collectionSlug !== null ? ($this->collections[$collectionSlug] ?? null) : null;
        $collectionId = ($collectionId === 0) ? null : $collectionId;

        $status = (string) ($line['status'] ?? 'draft');
        $status = in_array($status, ['draft', 'published', 'archived'], true) ? $status : 'draft';

        $title = trim((string) ($line['title'] ?? ''));

        return [
            'slug' => $slug,
            'collection_id' => $collectionId,
            'parent_id' => null, // resolved by ParentLinker once every page has an id
            'title' => Str::limit($title !== '' ? $title : Str::headline($slug), 255, ''),
            'excerpt' => $this->nullableString($line['excerpt'] ?? null, 255),
            'featured_image' => $this->media->path($this->nullableString($line['featured_image'] ?? null, 255)),
            'show_featured_image' => (bool) ($line['show_featured_image'] ?? true),
            'meta_title' => $this->nullableString($line['meta_title'] ?? null, 255),
            'meta_description' => $this->nullableString($line['meta_description'] ?? null, 500),
            'content' => $this->media->rewrite((string) ($line['content'] ?? '')),
            'status' => $status,
            'order' => max(0, (int) ($line['order'] ?? 0)),
            'is_published' => (bool) ($line['is_published'] ?? ($status === 'published')),
            'published_at' => $this->timestamp($line['published_at'] ?? null),
            'created_at' => $this->timestamp($line['created_at'] ?? null) ?? now(),
            'updated_at' => now(),
        ];
    }

    private function nullableString(mixed $value, int $limit = 255): ?string
    {
        $value = is_scalar($value) ? trim((string) $value) : '';

        return $value === '' ? null : Str::limit($value, $limit, '');
    }

    private function timestamp(mixed $value): ?CarbonImmutable
    {
        if (! is_string($value) || trim($value) === '') {
            return null;
        }

        try {
            return CarbonImmutable::parse($value);
        } catch (Throwable) {
            return null;
        }
    }

    /** `intro` taken → `intro-2`, `intro-3`, … */
    private function uniqueSlug(string $slug): string
    {
        $suffix = 2;

        while (isset($this->existing[$slug.'-'.$suffix]) || isset($this->pending[$slug.'-'.$suffix])) {
            $suffix++;
        }

        return $slug.'-'.$suffix;
    }
}
