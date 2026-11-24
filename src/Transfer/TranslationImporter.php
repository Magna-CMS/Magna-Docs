<?php

declare(strict_types=1);

namespace Magna\Docs\Transfer;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Magna\Docs\Support\DocLocales;

/**
 * Buffered writer for page translations. Rows arrive as their parent page's id
 * becomes known and are flushed in batches with an upsert keyed on
 * (doc_page_id, locale) — the table's own unique index — so re-importing an
 * archive updates translations instead of duplicating them.
 *
 * `excerpt`, `meta_title` and `meta_description` are optional (archives from
 * before 1.3.0 have none); a missing one is stored as null, like the page's
 * own optional fields.
 */
final class TranslationImporter
{
    /**
     * Keyed "page:locale" so a locale listed twice collapses to its last entry —
     * one upsert batch must not touch the same row twice (Postgres refuses).
     *
     * @var array<string, array<string, mixed>>
     */
    private array $buffer = [];

    public function __construct(
        private readonly ImportOptions $options,
        private readonly ImportReport $report,
        private readonly MediaMap $media,
    ) {}

    /**
     * @param  list<array<string, mixed>>  $translations  Raw `translations` array from a page record.
     */
    public function queue(int $pageId, array $translations): void
    {
        foreach ($translations as $translation) {
            $locale = strtolower(trim(is_scalar($translation['locale'] ?? null) ? (string) $translation['locale'] : ''));

            if (! DocLocales::isWellFormed($locale)) {
                $this->report->error("Translation with an unusable locale ('{$locale}') was skipped.");

                continue;
            }

            $this->report->add('translations_imported');

            if ($this->options->dryRun) {
                continue;
            }

            $this->buffer[$pageId.':'.$locale] = [
                'doc_page_id' => $pageId,
                'locale' => $locale,
                'title' => Str::limit(trim((string) ($translation['title'] ?? '')), 255, ''),
                'excerpt' => self::nullableString($translation['excerpt'] ?? null, 255),
                'meta_title' => self::nullableString($translation['meta_title'] ?? null, 255),
                'meta_description' => self::nullableString($translation['meta_description'] ?? null, 500),
                'content' => $this->media->rewrite((string) ($translation['content'] ?? '')),
                'created_at' => now(),
                'updated_at' => now(),
            ];

            if (count($this->buffer) >= DocsArchive::BATCH) {
                $this->flush();
            }
        }
    }

    public function flush(): void
    {
        if ($this->buffer === [] || $this->options->dryRun) {
            $this->buffer = [];

            return;
        }

        DB::table('docs_page_translations')->upsert(
            array_values($this->buffer),
            ['doc_page_id', 'locale'],
            ['title', 'excerpt', 'meta_title', 'meta_description', 'content', 'updated_at'],
        );

        $this->buffer = [];
    }

    private static function nullableString(mixed $value, int $limit): ?string
    {
        $value = is_scalar($value) ? trim((string) $value) : '';

        return $value === '' ? null : Str::limit($value, $limit, '');
    }
}
