<?php

declare(strict_types=1);

namespace Magna\Docs\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Magna\Docs\Support\DocTree;

class DocPage extends Model
{
    protected $table = 'docs_pages';

    protected $fillable = [
        'collection_id',
        'parent_id',
        'title',
        'slug',
        'excerpt',
        'featured_image',
        'meta_title',
        'meta_description',
        'content',
        'status',
        'order',
        'is_published',
        'published_at',
        'show_featured_image',
    ];

    protected $casts = [
        'is_published' => 'boolean',
        'published_at' => 'datetime',
        'show_featured_image' => 'boolean',
    ];

    protected static function booted(): void
    {
        static::saved(fn () => DocTree::flush());
        static::deleted(fn () => DocTree::flush());
    }

    /** @return BelongsTo<DocCollection, $this> */
    public function collection(): BelongsTo
    {
        return $this->belongsTo(DocCollection::class, 'collection_id');
    }

    /** @return BelongsTo<DocPage, $this> */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    /** @return HasMany<DocPage, $this> */
    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id')->orderBy('order');
    }

    /** @return HasMany<DocPageTranslation, $this> */
    public function translations(): HasMany
    {
        return $this->hasMany(DocPageTranslation::class, 'doc_page_id');
    }

    /**
     * Ancestors-first trail ending at this page. With a locale, each title is
     * that page's translation where it has one (English otherwise).
     *
     * @return list<array{title: string, slug: string}>
     */
    public function breadcrumb(?string $locale = null): array
    {
        /** @var list<DocPage> $nodes */
        $nodes = [];
        $node = $this;
        // Guard against a cyclic parent_id (e.g. A→B→A), which would otherwise
        // loop forever and hang the request. Stop if we revisit a page id.
        $seen = [];

        while ($node && ! isset($seen[$node->id])) {
            $seen[$node->id] = true;
            array_unshift($nodes, $node);
            $node = $node->parent;
        }

        /** @var array<int, string> $titles */
        $titles = ($locale === null || $locale === 'en') ? [] : DocPageTranslation::query()
            ->whereIn('doc_page_id', array_map(fn (DocPage $page): int => (int) $page->id, $nodes))
            ->where('locale', $locale)
            ->where('title', '!=', '')
            ->pluck('title', 'doc_page_id')
            ->all();

        return array_map(fn (DocPage $page): array => [
            'title' => $titles[(int) $page->id] ?? (string) $page->title,
            'slug' => (string) $page->slug,
        ], $nodes);
    }

    /**
     * Minutes to read the page, or the given (translated) Markdown. Words are
     * counted as runs of letters in any script: str_word_count() only knows
     * ASCII letters and reads a Malayalam or Hindi page as zero words.
     */
    public function readingTimeMinutes(?string $content = null): int
    {
        $words = preg_match_all("/[\p{L}\p{M}\p{N}]+(?:['’-][\p{L}\p{M}\p{N}]+)*/u", strip_tags($content ?? (string) $this->content));

        return (int) max(1, round(((int) $words) / 200));
    }
}
