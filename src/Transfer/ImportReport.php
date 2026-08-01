<?php

declare(strict_types=1);

namespace Magna\Docs\Transfer;

/**
 * Running tally of an import — the same object is produced by a dry run
 * (where it means "would have") and by a real run.
 */
final class ImportReport
{
    /** @var array<string, int> */
    private array $counts = [
        'collections_created' => 0,
        'collections_updated' => 0,
        'collections_skipped' => 0,
        'pages_created' => 0,
        'pages_updated' => 0,
        'pages_skipped' => 0,
        'translations_imported' => 0,
        'media_imported' => 0,
        'media_failed' => 0,
    ];

    /** @var list<string> */
    private array $errors = [];

    /** Errors past this are counted but not stored, so a broken archive can't exhaust memory. */
    private const MAX_ERRORS = 100;

    private int $errorOverflow = 0;

    public function __construct(public readonly bool $dryRun = false) {}

    public function add(string $bucket, int $n = 1): void
    {
        $this->counts[$bucket] = ($this->counts[$bucket] ?? 0) + $n;
    }

    public function error(string $message): void
    {
        if (count($this->errors) >= self::MAX_ERRORS) {
            $this->errorOverflow++;

            return;
        }

        $this->errors[] = $message;
    }

    public function count(string $bucket): int
    {
        return $this->counts[$bucket] ?? 0;
    }

    /** @return array<string, int> */
    public function counts(): array
    {
        return $this->counts;
    }

    /** @return list<string> */
    public function errors(): array
    {
        return $this->errorOverflow > 0
            ? [...$this->errors, "… and {$this->errorOverflow} more problem(s) not listed."]
            : $this->errors;
    }

    public function hasErrors(): bool
    {
        return $this->errors !== [];
    }

    /** @return array{dry_run: bool, counts: array<string, int>, errors: list<string>} */
    public function toArray(): array
    {
        return [
            'dry_run' => $this->dryRun,
            'counts' => $this->counts,
            'errors' => $this->errors(),
        ];
    }

    /** One-line summary for a notification body or CLI output. */
    public function summary(): string
    {
        return sprintf(
            '%d collections and %d pages created, %d updated, %d skipped; %d translations, %d media files.',
            $this->count('collections_created'),
            $this->count('pages_created'),
            $this->count('collections_updated') + $this->count('pages_updated'),
            $this->count('collections_skipped') + $this->count('pages_skipped'),
            $this->count('translations_imported'),
            $this->count('media_imported'),
        );
    }
}
