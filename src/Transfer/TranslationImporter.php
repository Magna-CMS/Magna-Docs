<?php

declare(strict_types=1);

namespace Magna\Docs\Transfer;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Buffered writer for page translations. Rows arrive as their parent page's id
 * becomes known and are flushed in batches with an upsert keyed on
 * (doc_page_id, locale) — the table's own unique index — so re-importing an
 * archive updates translations instead of duplicating them.
 */
final class TranslationImporter
{
    /** @var list<array<string, mixed>> */
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
            $locale = strtolower(trim((string) ($translation['locale'] ?? '')));

            if (preg_match('/^[a-z]{2}(?:[-_][a-z0-9]{2,8})?$/', $locale) !== 1) {
                $this->report->error("Translation with an unusable locale ('{$locale}') was skipped.");

                continue;
            }

            $this->report->add('translations_imported');

            if ($this->options->dryRun) {
                continue;
            }

            $this->buffer[] = [
                'doc_page_id' => $pageId,
                'locale' => $locale,
                'title' => Str::limit(trim((string) ($translation['title'] ?? '')), 255, ''),
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
            $this->buffer,
            ['doc_page_id', 'locale'],
            ['title', 'content', 'updated_at'],
        );

        $this->buffer = [];
    }
}
