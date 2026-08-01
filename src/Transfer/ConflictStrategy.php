<?php

declare(strict_types=1);

namespace Magna\Docs\Transfer;

/**
 * What the importer does when an incoming slug already exists locally.
 * Slug is the natural key for both collections and pages, so this is the only
 * conflict an archive can produce.
 */
enum ConflictStrategy: string
{
    /** Leave the local record untouched (WordPress's default behaviour). */
    case Skip = 'skip';

    /** Overwrite the local record's content and metadata, keeping its id and slug. */
    case Update = 'update';

    /** Keep both: import under a suffixed slug (`intro`, `intro-2`, `intro-3`, …). */
    case Duplicate = 'duplicate';

    /** @return array<string, string> value => human label, for a Filament select. */
    public static function options(): array
    {
        return [
            self::Update->value => 'Update existing (match by slug, overwrite content)',
            self::Skip->value => 'Skip existing (only import what is missing)',
            self::Duplicate->value => 'Keep both (import conflicting pages under a new slug)',
        ];
    }
}
