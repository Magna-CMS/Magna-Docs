<?php

declare(strict_types=1);

/**
 * Archive format, 1.3.0 additions (still format magna-docs-export, version 1):
 *
 *   pages.ndjson        translations[] entries gain optional excerpt,
 *                       meta_title and meta_description
 *   collections.ndjson  lines gain optional translations: [{locale, title,
 *                       description?}] (`name` accepted for `title`)
 *
 * Archives written before 1.3.0 carry none of these and must still import.
 */

use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Magna\Docs\Models\DocCollection;
use Magna\Docs\Models\DocCollectionTranslation;
use Magna\Docs\Models\DocPage;
use Magna\Docs\Models\DocPageTranslation;
use Magna\Docs\Transfer\ConflictStrategy;
use Magna\Docs\Transfer\DocsArchive;
use Magna\Docs\Transfer\DocsExporter;
use Magna\Docs\Transfer\DocsImporter;
use Magna\Docs\Transfer\ExportOptions;
use Magna\Docs\Transfer\ImportOptions;
use Magna\Testing\PluginTestCase;

uses(PluginTestCase::class);

beforeEach(function (): void {
    $this->enablePlugin('magna-cms/docs');
    app('router')->getRoutes()->refreshNameLookups();

    Storage::fake('local');
    Storage::fake('public');
    Queue::fake();
});

function docsXlateSeed(): void
{
    $collection = DocCollection::create([
        'title' => 'Guides', 'slug' => 'guides', 'description' => 'How to.', 'order' => 0, 'is_public' => true,
    ]);
    $collection->translations()->create(['locale' => 'ml', 'title' => 'ഗൈഡുകൾ', 'description' => 'എങ്ങനെ.']);
    $collection->translations()->create(['locale' => 'hi', 'title' => 'गाइड']);

    $page = DocPage::create([
        'collection_id' => $collection->id, 'title' => 'Introduction', 'slug' => 'intro',
        'content' => 'Welcome.', 'status' => 'published', 'order' => 0,
    ]);
    $page->translations()->create([
        'locale' => 'ml',
        'title' => 'ആമുഖം',
        'excerpt' => 'ഇവിടെ തുടങ്ങൂ.',
        'meta_title' => 'ആമുഖം | ഡോക്സ്',
        'meta_description' => 'മലയാളം വിവരണം.',
        'content' => 'സ്വാഗതം.',
    ]);
}

function docsXlateExport(): string
{
    $result = app(DocsExporter::class)->export(new ExportOptions);

    return Storage::disk($result->disk)->path($result->path);
}

function docsXlateWipe(): void
{
    DocCollectionTranslation::query()->delete();
    DocPageTranslation::query()->delete();
    DocPage::query()->update(['parent_id' => null]);
    DocPage::query()->delete();
    DocCollection::query()->delete();
}

/** @return list<array<string, mixed>> */
function docsXlateLines(string $archive, string $entry): array
{
    $zip = new ZipArchive;
    $zip->open($archive);
    $raw = (string) $zip->getFromName($entry);
    $zip->close();

    return array_values(array_map(
        fn (string $line): array => (array) json_decode($line, true),
        array_filter(explode("\n", $raw), fn (string $line): bool => trim($line) !== ''),
    ));
}

/**
 * A hand-built archive, the way another tool (or an older export) writes one.
 *
 * @param  list<array<string, mixed>>  $collections
 * @param  list<array<string, mixed>>  $pages
 */
function docsXlateCraft(array $collections, array $pages): string
{
    $path = Storage::disk('local')->path('crafted-'.uniqid().'.zip');
    $ndjson = fn (array $rows): string => implode('', array_map(
        fn (array $row): string => json_encode($row, JSON_UNESCAPED_UNICODE)."\n",
        $rows,
    ));

    $zip = new ZipArchive;
    $zip->open($path, ZipArchive::CREATE | ZipArchive::OVERWRITE);
    $zip->addFromString(DocsArchive::MANIFEST, (string) json_encode(['format' => DocsArchive::FORMAT, 'version' => 1]));
    $zip->addFromString(DocsArchive::COLLECTIONS, $ndjson($collections));
    $zip->addFromString(DocsArchive::PAGES, $ndjson($pages));
    $zip->addFromString(DocsArchive::MEDIA_INDEX, '');
    $zip->close();

    return $path;
}

it('writes translation excerpt/meta and collection translations into the archive', function (): void {
    docsXlateSeed();
    $archive = docsXlateExport();

    $collection = docsXlateLines($archive, DocsArchive::COLLECTIONS)[0];
    expect($collection['translations'])->toBe([
        ['locale' => 'hi', 'title' => 'गाइड', 'description' => null],
        ['locale' => 'ml', 'title' => 'ഗൈഡുകൾ', 'description' => 'എങ്ങനെ.'],
    ]);

    $page = docsXlateLines($archive, DocsArchive::PAGES)[0];
    expect($page['translations'][0])->toBe([
        'locale' => 'ml',
        'title' => 'ആമുഖം',
        'excerpt' => 'ഇവിടെ തുടങ്ങൂ.',
        'meta_title' => 'ആമുഖം | ഡോക്സ്',
        'meta_description' => 'മലയാളം വിവരണം.',
        'content' => 'സ്വാഗതം.',
    ]);

    $zip = new ZipArchive;
    $zip->open($archive);
    $manifest = (array) json_decode((string) $zip->getFromName(DocsArchive::MANIFEST), true);
    $zip->close();

    expect($manifest['version'])->toBe(1)
        ->and($manifest['counts']['collection_translations'])->toBe(2);
});

it('round-trips the new fields into an empty install', function (): void {
    docsXlateSeed();
    $archive = docsXlateExport();
    docsXlateWipe();

    $report = app(DocsImporter::class)->importArchive($archive, new ImportOptions);

    expect($report->errors())->toBe([])
        ->and($report->count('collection_translations_imported'))->toBe(2)
        ->and($report->count('translations_imported'))->toBe(1);

    $collection = DocCollection::query()->where('slug', 'guides')->firstOrFail();
    expect($collection->translations()->where('locale', 'ml')->first()?->only(['title', 'description']))
        ->toBe(['title' => 'ഗൈഡുകൾ', 'description' => 'എങ്ങനെ.']);

    $translation = DocPage::query()->where('slug', 'intro')->firstOrFail()->translations()->first();
    expect($translation?->only(['locale', 'excerpt', 'meta_title', 'meta_description']))->toBe([
        'locale' => 'ml',
        'excerpt' => 'ഇവിടെ തുടങ്ങൂ.',
        'meta_title' => 'ആമുഖം | ഡോക്സ്',
        'meta_description' => 'മലയാളം വിവരണം.',
    ]);

    // ...and the imported titles reach the public sidebar straight away.
    $this->get('/docs/intro?lang=ml')->assertOk()->assertSee('ഗൈഡുകൾ');
});

it('upserts collection translations on (collection, locale) when re-importing', function (): void {
    docsXlateSeed();
    $archive = docsXlateExport();

    DocCollectionTranslation::query()->where('locale', 'ml')->update(['title' => 'changed locally']);

    app(DocsImporter::class)->importArchive($archive, new ImportOptions(conflict: ConflictStrategy::Update));

    expect(DocCollectionTranslation::query()->count())->toBe(2)
        ->and(DocCollectionTranslation::query()->where('locale', 'ml')->value('title'))->toBe('ഗൈഡുകൾ');
});

it('leaves a skipped collection\'s translations alone', function (): void {
    docsXlateSeed();
    $archive = docsXlateExport();

    DocCollectionTranslation::query()->where('locale', 'ml')->update(['title' => 'kept']);

    app(DocsImporter::class)->importArchive($archive, new ImportOptions(conflict: ConflictStrategy::Skip));

    expect(DocCollectionTranslation::query()->where('locale', 'ml')->value('title'))->toBe('kept');
});

it('gives a duplicated collection its own copy of the translations', function (): void {
    docsXlateSeed();
    $archive = docsXlateExport();

    app(DocsImporter::class)->importArchive($archive, new ImportOptions(conflict: ConflictStrategy::Duplicate));

    $copy = DocCollection::query()->where('slug', 'guides-2')->firstOrFail();
    expect($copy->translations()->pluck('title', 'locale')->all())->toBe(['hi' => 'गाइड', 'ml' => 'ഗൈഡുകൾ']);
});

it('counts collection translations on a dry run without writing them', function (): void {
    $archive = docsXlateCraft(
        [['slug' => 'guides', 'title' => 'Guides', 'translations' => [['locale' => 'ml', 'title' => 'ഗൈഡുകൾ']]]],
        [],
    );

    $report = app(DocsImporter::class)->importArchive($archive, new ImportOptions(dryRun: true));

    expect($report->count('collection_translations_imported'))->toBe(1)
        ->and(DocCollectionTranslation::query()->count())->toBe(0);
});

it('still imports an archive written before 1.3.0', function (): void {
    $archive = docsXlateCraft(
        [['slug' => 'guides', 'title' => 'Guides', 'order' => 0, 'is_public' => true]],
        [[
            'slug' => 'intro',
            'title' => 'Introduction',
            'collection' => 'guides',
            'status' => 'published',
            'content' => 'Welcome.',
            'translations' => [['locale' => 'ml', 'title' => 'ആമുഖം', 'content' => 'സ്വാഗതം.']],
        ]],
    );

    $report = app(DocsImporter::class)->importArchive($archive, new ImportOptions);

    expect($report->errors())->toBe([])
        ->and($report->count('pages_created'))->toBe(1)
        ->and($report->count('collection_translations_imported'))->toBe(0)
        ->and(DocCollectionTranslation::query()->count())->toBe(0);

    $translation = DocPageTranslation::query()->firstOrFail();
    expect($translation->title)->toBe('ആമുഖം')
        ->and($translation->content)->toBe('സ്വാഗതം.')
        ->and($translation->excerpt)->toBeNull()
        ->and($translation->meta_title)->toBeNull()
        ->and($translation->meta_description)->toBeNull();
});

it('accepts name for a collection translation title, and skips an unusable locale', function (): void {
    $archive = docsXlateCraft(
        [[
            'slug' => 'guides',
            'title' => 'Guides',
            'translations' => [
                ['locale' => 'ta', 'name' => 'வழிகாட்டிகள்', 'description' => 'எப்படி.'],
                ['locale' => 'FIL', 'title' => 'Mga Gabay'],
                ['locale' => 'not a locale', 'title' => 'x'],
            ],
        ]],
        [],
    );

    $report = app(DocsImporter::class)->importArchive($archive, new ImportOptions);

    expect(DocCollectionTranslation::query()->orderBy('locale')->pluck('title', 'locale')->all())
        ->toBe(['fil' => 'Mga Gabay', 'ta' => 'வழிகாட்டிகள்'])
        ->and(DocCollectionTranslation::query()->where('locale', 'ta')->value('description'))->toBe('எப்படி.')
        ->and($report->errors())->toHaveCount(1);
});

it('keeps the last entry when an archive lists the same locale twice', function (): void {
    $archive = docsXlateCraft(
        [['slug' => 'guides', 'title' => 'Guides', 'translations' => [
            ['locale' => 'ml', 'title' => 'first'],
            ['locale' => 'ml', 'title' => 'second'],
        ]]],
        [['slug' => 'intro', 'title' => 'Introduction', 'status' => 'published', 'content' => 'x', 'translations' => [
            ['locale' => 'ml', 'title' => 'one'],
            ['locale' => 'ml', 'title' => 'two'],
        ]]],
    );

    app(DocsImporter::class)->importArchive($archive, new ImportOptions);

    expect(DocCollectionTranslation::query()->pluck('title')->all())->toBe(['second'])
        ->and(DocPageTranslation::query()->pluck('title')->all())->toBe(['two']);
});

it('imports a three-letter locale like fil on page translations too', function (): void {
    $archive = docsXlateCraft([], [[
        'slug' => 'intro',
        'title' => 'Introduction',
        'status' => 'published',
        'content' => 'Welcome.',
        'translations' => [['locale' => 'fil', 'title' => 'Panimula', 'excerpt' => 'Simulan dito.']],
    ]]);

    $report = app(DocsImporter::class)->importArchive($archive, new ImportOptions);

    expect($report->errors())->toBe([])
        ->and(DocPageTranslation::query()->where('locale', 'fil')->value('excerpt'))->toBe('Simulan dito.');
});
