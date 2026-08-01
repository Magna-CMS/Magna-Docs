<?php

declare(strict_types=1);

namespace Magna\Docs\Transfer;

/**
 * What goes into an export. Defaults are "everything", so a one-click export
 * from the admin panel needs no choices at all.
 */
final class ExportOptions
{
    /**
     * @param  list<string>  $collections  Collection slugs to export; empty = every collection (and uncollected pages).
     */
    public function __construct(
        public readonly array $collections = [],
        public readonly bool $includeDrafts = true,
        public readonly bool $includeMedia = true,
        public readonly bool $includeSettings = false,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        /** @var list<string> $collections */
        $collections = array_values(array_filter(
            array_map(static fn (mixed $slug): string => (string) $slug, (array) ($data['collections'] ?? [])),
            static fn (string $slug): bool => $slug !== '',
        ));

        return new self(
            collections: $collections,
            includeDrafts: (bool) ($data['include_drafts'] ?? true),
            includeMedia: (bool) ($data['include_media'] ?? true),
            includeSettings: (bool) ($data['include_settings'] ?? false),
        );
    }

    public function isScoped(): bool
    {
        return $this->collections !== [];
    }
}
