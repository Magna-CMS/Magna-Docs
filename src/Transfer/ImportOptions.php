<?php

declare(strict_types=1);

namespace Magna\Docs\Transfer;

final class ImportOptions
{
    public function __construct(
        public readonly ConflictStrategy $conflict = ConflictStrategy::Update,
        public readonly bool $dryRun = false,
        public readonly bool $importMedia = true,
        public readonly bool $importSettings = false,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            conflict: ConflictStrategy::tryFrom((string) ($data['conflict'] ?? '')) ?? ConflictStrategy::Update,
            dryRun: (bool) ($data['dry_run'] ?? false),
            importMedia: (bool) ($data['import_media'] ?? true),
            importSettings: (bool) ($data['import_settings'] ?? false),
        );
    }
}
