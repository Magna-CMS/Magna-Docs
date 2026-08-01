<?php

declare(strict_types=1);

namespace Magna\Docs\Transfer;

use RuntimeException;

/**
 * The uploaded file is not a usable Magna Docs archive. Thrown before anything
 * is read out of it, so a malformed or hostile upload never reaches the
 * importer's write path.
 */
final class InvalidArchiveException extends RuntimeException
{
    public static function unreadable(): self
    {
        return new self('The file could not be opened as a .zip archive.');
    }

    public static function notADocsArchive(): self
    {
        return new self('This zip is not a Magna Docs export — its manifest.json is missing or unreadable.');
    }

    public static function unsupportedVersion(int $version): self
    {
        return new self(
            "This archive was written in Magna Docs export format v{$version}, "
            .'but this plugin can only read up to v'.DocsArchive::VERSION.'. Update the plugin and try again.',
        );
    }

    public static function unsafeEntry(string $entry): self
    {
        return new self("The archive contains an unexpected or unsafe entry ({$entry}) and was rejected.");
    }

    public static function tooLarge(): self
    {
        return new self('The archive is larger than this importer will accept (more than 4 GB uncompressed, or too many files).');
    }
}
