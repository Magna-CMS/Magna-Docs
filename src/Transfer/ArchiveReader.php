<?php

declare(strict_types=1);

namespace Magna\Docs\Transfer;

use Generator;
use ZipArchive;

/**
 * Read side of a docs archive. Everything an untrusted upload can influence is
 * checked here, once, before the importer touches the database:
 *
 *   - the zip opens, and manifest.json declares this format at a readable version
 *   - no entry escapes the archive root (`..`, absolute paths, backslashes)
 *   - no entry outside the names this format defines
 *   - entry count and total uncompressed size stay under DocsArchive's ceilings
 *
 * NDJSON entries are read through a zip stream one line at a time, so importing
 * a 1000-page archive costs one line of memory, not the whole file.
 */
final class ArchiveReader
{
    private function __construct(
        private readonly ZipArchive $zip,
        /** @var array<string, mixed> */
        private readonly array $manifest,
    ) {}

    public static function open(string $absolutePath): self
    {
        $zip = new ZipArchive;

        if ($zip->open($absolutePath, ZipArchive::RDONLY) !== true) {
            throw InvalidArchiveException::unreadable();
        }

        self::guardContents($zip);

        $raw = $zip->getFromName(DocsArchive::MANIFEST);

        if ($raw === false) {
            $zip->close();
            throw InvalidArchiveException::notADocsArchive();
        }

        /** @var mixed $manifest */
        $manifest = json_decode($raw, true);

        if (! is_array($manifest) || ($manifest['format'] ?? null) !== DocsArchive::FORMAT) {
            $zip->close();
            throw InvalidArchiveException::notADocsArchive();
        }

        $version = (int) ($manifest['version'] ?? 0);

        if ($version < 1 || $version > DocsArchive::VERSION) {
            $zip->close();
            throw InvalidArchiveException::unsupportedVersion($version);
        }

        /** @var array<string, mixed> $manifest */
        return new self($zip, $manifest);
    }

    private static function guardContents(ZipArchive $zip): void
    {
        if ($zip->numFiles > DocsArchive::MAX_ENTRIES) {
            $zip->close();
            throw InvalidArchiveException::tooLarge();
        }

        $total = 0;

        for ($i = 0; $i < $zip->numFiles; $i++) {
            $stat = $zip->statIndex($i);

            if ($stat === false) {
                $zip->close();
                throw InvalidArchiveException::unreadable();
            }

            $name = (string) $stat['name'];

            // Directory entries carry no content and are harmless once the name
            // itself has been checked for traversal.
            if (! str_ends_with($name, '/') && ! DocsArchive::isSafeEntry($name)) {
                $zip->close();
                throw InvalidArchiveException::unsafeEntry($name);
            }

            $total += (int) $stat['size'];

            if ($total > DocsArchive::MAX_UNCOMPRESSED_BYTES) {
                $zip->close();
                throw InvalidArchiveException::tooLarge();
            }
        }
    }

    /** @return array<string, mixed> */
    public function manifest(): array
    {
        return $this->manifest;
    }

    /** @return array<string, int> Declared counts from the manifest (collections/pages/…). */
    public function declaredCounts(): array
    {
        $counts = $this->manifest['counts'] ?? [];
        $clean = [];

        if (is_array($counts)) {
            foreach ($counts as $key => $value) {
                $clean[(string) $key] = (int) (is_numeric($value) ? $value : 0);
            }
        }

        return $clean;
    }

    /**
     * The public-disk URL/path prefixes media had on the source install.
     *
     * @return array{url: string, path: string}
     */
    public function sourcePrefixes(): array
    {
        $source = $this->manifest['source'] ?? [];
        $source = is_array($source) ? $source : [];

        return [
            'url' => (string) ($source['storage_url_prefix'] ?? ''),
            'path' => (string) ($source['storage_path_prefix'] ?? '/storage/'),
        ];
    }

    public function has(string $entry): bool
    {
        return $this->zip->locateName($entry) !== false;
    }

    /**
     * Stream one NDJSON entry, decoding a record per line. A malformed line
     * yields null so the caller can report it and carry on — one bad line must
     * not abort an otherwise good import.
     *
     * @return Generator<int, array<string, mixed>|null>
     */
    public function lines(string $entry): Generator
    {
        $stream = $this->zip->getStream($entry);

        if ($stream === false) {
            return;
        }

        try {
            $number = 0;

            while (($line = fgets($stream)) !== false) {
                $number++;
                $line = trim($line);

                if ($line === '') {
                    continue;
                }

                /** @var mixed $decoded */
                $decoded = json_decode($line, true);

                yield $number => is_array($decoded) ? $decoded : null;
            }
        } finally {
            fclose($stream);
        }
    }

    /**
     * Read a whole (small) JSON entry, e.g. settings.json. Null when absent or malformed.
     *
     * @return array<string, mixed>|null
     */
    public function json(string $entry): ?array
    {
        $raw = $this->zip->getFromName($entry);

        if ($raw === false) {
            return null;
        }

        /** @var mixed $decoded */
        $decoded = json_decode($raw, true);

        return is_array($decoded) ? $decoded : null;
    }

    /** Copy one entry out to an absolute path on the local filesystem. */
    public function extractTo(string $entry, string $absoluteTarget): bool
    {
        $contents = $this->zip->getFromName($entry);

        if ($contents === false) {
            return false;
        }

        return file_put_contents($absoluteTarget, $contents) !== false;
    }

    public function close(): void
    {
        $this->zip->close();
    }
}
