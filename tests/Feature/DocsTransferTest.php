<?php

declare(strict_types=1);

use Filament\Facades\Filament;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Magna\Auth\Role;
use Magna\Docs\Filament\Pages\DocsTransferPage;
use Magna\Docs\Models\DocCollection;
use Magna\Docs\Models\DocPage;
use Magna\Docs\Models\DocPageTranslation;
use Magna\Docs\Transfer\ConflictStrategy;
use Magna\Docs\Transfer\DocsArchive;
use Magna\Docs\Transfer\DocsExporter;
use Magna\Docs\Transfer\DocsImporter;
use Magna\Docs\Transfer\ExportOptions;
use Magna\Docs\Transfer\ImportOptions;
use Magna\Docs\Transfer\InvalidArchiveException;
use Magna\Testing\PluginTestCase;
use Magna\Users\User;

uses(PluginTestCase::class);

beforeEach(function (): void {
    $this->enablePlugin('magna-cms/docs');

    Storage::fake('local');
    Storage::fake('public');
    Queue::fake();
});

// ── Helpers ───────────────────────────────────────────────────────────────────

/** A collection with a two-level page tree and one translation. */
function docsTransferSeed(): DocCollection
{
    $collection = DocCollection::create([
        'title' => 'Guides',
        'slug' => 'guides',
        'description' => 'How to do things.',
        'icon' => 'book-open',
        'color' => '#8b5cf6',
        'order' => 0,
        'is_public' => true,
    ]);

    $intro = DocPage::create([
        'collection_id' => $collection->id,
        'title' => 'Introduction',
        'slug' => 'intro',
        'excerpt' => 'Start here.',
        'content' => "# Introduction\n\nWelcome.",
        'status' => 'published',
        'order' => 0,
        'is_published' => true,
        'published_at' => now(),
    ]);

    DocPage::create([
        'collection_id' => $collection->id,
        'parent_id' => $intro->id,
        'title' => 'Nested page',
        'slug' => 'nested',
        'content' => 'Child body.',
        'status' => 'draft',
        'order' => 1,
        'is_published' => false,
    ]);

    DocPageTranslation::create([
        'doc_page_id' => $intro->id,
        'locale' => 'fr',
        'title' => 'Introduction FR',
        'content' => 'Bienvenue.',
    ]);

    return $collection;
}

function docsTransferExport(?ExportOptions $options = null): string
{
    $result = app(DocsExporter::class)->export($options ?? new ExportOptions);

    return Storage::disk($result->disk)->path($result->path);
}

function docsTransferWipe(): void
{
    DocPageTranslation::query()->delete();
    DocPage::query()->update(['parent_id' => null]);
    DocPage::query()->delete();
    DocCollection::query()->delete();
}

/** A minimal 10×10 JPEG, so media can be exercised without a fixture file. */
function docsTransferJpeg(): string
{
    $gd = imagecreatetruecolor(10, 10);
    if ($gd === false) {
        throw new RuntimeException('GD not available.');
    }
    ob_start();
    imagejpeg($gd, null, 90);
    $data = ob_get_clean() ?: '';
    imagedestroy($gd);

    return $data;
}

// ── Round trip ────────────────────────────────────────────────────────────────

it('exports the whole site and imports it back into an empty install', function (): void {
    docsTransferSeed();
    $archive = docsTransferExport();

    expect(is_file($archive))->toBeTrue();

    docsTransferWipe();
    expect(DocPage::query()->count())->toBe(0);

    $report = app(DocsImporter::class)->importArchive($archive, new ImportOptions);

    expect($report->count('collections_created'))->toBe(1)
        ->and($report->count('pages_created'))->toBe(2)
        ->and($report->count('translations_imported'))->toBe(1)
        ->and($report->errors())->toBe([]);

    $intro = DocPage::query()->where('slug', 'intro')->firstOrFail();
    $nested = DocPage::query()->where('slug', 'nested')->firstOrFail();

    expect($intro->collection?->slug)->toBe('guides')
        ->and($intro->status)->toBe('published')
        ->and($intro->content)->toContain('Welcome.')
        ->and($nested->parent_id)->toBe($intro->id)
        ->and($nested->status)->toBe('draft')
        ->and($nested->order)->toBe(1)
        ->and($intro->translations()->where('locale', 'fr')->value('title'))->toBe('Introduction FR');
});

it('re-importing the same archive updates in place instead of duplicating', function (): void {
    docsTransferSeed();
    $archive = docsTransferExport();

    DocPage::query()->where('slug', 'intro')->update(['content' => 'Local edit.']);

    $report = app(DocsImporter::class)->importArchive($archive, new ImportOptions(
        conflict: ConflictStrategy::Update,
    ));

    expect(DocPage::query()->count())->toBe(2)
        ->and($report->count('pages_updated'))->toBe(2)
        ->and($report->count('pages_created'))->toBe(0)
        ->and(DocPage::query()->where('slug', 'intro')->value('content'))->toContain('Welcome.')
        ->and(DocPageTranslation::query()->count())->toBe(1);
});

it('leaves existing pages untouched with the skip strategy', function (): void {
    docsTransferSeed();
    $archive = docsTransferExport();

    DocPage::query()->where('slug', 'intro')->update(['content' => 'Local edit.']);

    $report = app(DocsImporter::class)->importArchive($archive, new ImportOptions(
        conflict: ConflictStrategy::Skip,
    ));

    expect($report->count('pages_skipped'))->toBe(2)
        ->and(DocPage::query()->where('slug', 'intro')->value('content'))->toBe('Local edit.');
});

it('imports conflicting pages under a new slug with the duplicate strategy', function (): void {
    docsTransferSeed();
    $archive = docsTransferExport();

    $report = app(DocsImporter::class)->importArchive($archive, new ImportOptions(
        conflict: ConflictStrategy::Duplicate,
    ));

    expect($report->count('pages_created'))->toBe(2)
        ->and(DocPage::query()->count())->toBe(4)
        ->and(DocPage::query()->where('slug', 'intro-2')->exists())->toBeTrue()
        ->and(DocCollection::query()->where('slug', 'guides-2')->exists())->toBeTrue();

    // The duplicated child hangs off the duplicated parent, not the original.
    $duplicateParent = DocPage::query()->where('slug', 'intro-2')->firstOrFail();
    expect(DocPage::query()->where('slug', 'nested-2')->value('parent_id'))->toBe($duplicateParent->id);
});

it('reports what a dry run would do without writing anything', function (): void {
    docsTransferSeed();
    $archive = docsTransferExport();
    docsTransferWipe();

    $report = app(DocsImporter::class)->importArchive($archive, new ImportOptions(dryRun: true));

    expect($report->count('pages_created'))->toBe(2)
        ->and($report->count('collections_created'))->toBe(1)
        ->and(DocPage::query()->count())->toBe(0)
        ->and(DocCollection::query()->count())->toBe(0);
});

it('honours an export scoped to one collection', function (): void {
    docsTransferSeed();

    DocCollection::create([
        'title' => 'Reference',
        'slug' => 'reference',
        'order' => 1,
        'is_public' => true,
    ]);
    DocPage::create([
        'collection_id' => DocCollection::query()->where('slug', 'reference')->value('id'),
        'title' => 'API',
        'slug' => 'api',
        'content' => 'Endpoints.',
        'status' => 'published',
        'order' => 0,
        'is_published' => true,
    ]);

    $archive = docsTransferExport(new ExportOptions(collections: ['reference']));
    docsTransferWipe();

    app(DocsImporter::class)->importArchive($archive, new ImportOptions);

    expect(DocPage::query()->pluck('slug')->all())->toBe(['api'])
        ->and(DocCollection::query()->pluck('slug')->all())->toBe(['reference']);
});

it('excludes drafts when asked to', function (): void {
    docsTransferSeed();

    $archive = docsTransferExport(new ExportOptions(includeDrafts: false));
    docsTransferWipe();

    app(DocsImporter::class)->importArchive($archive, new ImportOptions);

    expect(DocPage::query()->pluck('slug')->all())->toBe(['intro']);
});

// ── Media ─────────────────────────────────────────────────────────────────────

it('carries media across and repoints page references at the imported files', function (): void {
    Storage::disk('public')->put('legacy/diagram.jpg', docsTransferJpeg());

    $collection = docsTransferSeed();
    DocPage::create([
        'collection_id' => $collection->id,
        'title' => 'Illustrated',
        'slug' => 'illustrated',
        'featured_image' => 'legacy/diagram.jpg',
        'content' => 'See ![diagram](/storage/legacy/diagram.jpg) above.',
        'status' => 'published',
        'order' => 2,
        'is_published' => true,
    ]);

    $archive = docsTransferExport();
    docsTransferWipe();

    $report = app(DocsImporter::class)->importArchive($archive, new ImportOptions);

    expect($report->count('media_imported'))->toBe(1)
        ->and($report->count('media_failed'))->toBe(0);

    $page = DocPage::query()->where('slug', 'illustrated')->firstOrFail();

    expect($page->featured_image)->toStartWith('media/')
        ->and($page->content)->not->toContain('/storage/legacy/diagram.jpg')
        ->and($page->content)->toContain('/storage/'.$page->featured_image)
        ->and(Storage::disk('public')->exists((string) $page->featured_image))->toBeTrue();
});

// ── Archive validation ────────────────────────────────────────────────────────

it('refuses a zip that is not a docs archive', function (): void {
    $path = Storage::disk('local')->path('not-docs.zip');
    $zip = new ZipArchive;
    $zip->open($path, ZipArchive::CREATE | ZipArchive::OVERWRITE);
    $zip->addFromString('readme.txt', 'hello');
    $zip->close();

    app(DocsImporter::class)->importArchive($path, new ImportOptions);
})->throws(InvalidArchiveException::class);

it('refuses an archive containing a path-traversal entry', function (): void {
    $path = Storage::disk('local')->path('evil.zip');
    $zip = new ZipArchive;
    $zip->open($path, ZipArchive::CREATE | ZipArchive::OVERWRITE);
    $zip->addFromString(DocsArchive::MANIFEST, (string) json_encode([
        'format' => DocsArchive::FORMAT,
        'version' => DocsArchive::VERSION,
    ]));
    $zip->addFromString('../../evil.php', '<?php echo 1;');
    $zip->close();

    app(DocsImporter::class)->importArchive($path, new ImportOptions);
})->throws(InvalidArchiveException::class);

it('refuses an archive written by a newer export format', function (): void {
    $path = Storage::disk('local')->path('future.zip');
    $zip = new ZipArchive;
    $zip->open($path, ZipArchive::CREATE | ZipArchive::OVERWRITE);
    $zip->addFromString(DocsArchive::MANIFEST, (string) json_encode([
        'format' => DocsArchive::FORMAT,
        'version' => DocsArchive::VERSION + 1,
    ]));
    $zip->close();

    app(DocsImporter::class)->importArchive($path, new ImportOptions);
})->throws(InvalidArchiveException::class);

it('skips a malformed record instead of failing the whole import', function (): void {
    docsTransferSeed();
    $archive = docsTransferExport();
    docsTransferWipe();

    // Append a broken line to pages.ndjson inside the archive.
    $zip = new ZipArchive;
    $zip->open($archive);
    $pages = (string) $zip->getFromName(DocsArchive::PAGES);
    $zip->addFromString(DocsArchive::PAGES, $pages."{ this is not json\n");
    $zip->close();

    $report = app(DocsImporter::class)->importArchive($archive, new ImportOptions);

    expect($report->count('pages_created'))->toBe(2)
        ->and($report->errors())->not->toBe([]);
});

// ── Admin screen ──────────────────────────────────────────────────────────────

function docsTransferAdmin(): User
{
    $role = Role::factory()->create([
        'handle' => 'super_admin',
        'name' => 'Super Admin',
        'is_super_admin' => true,
    ]);

    $user = User::factory()->create(['two_factor_confirmed_at' => now()]);
    $user->assignRole($role);

    return $user;
}

it('downloads an archive from the admin Import / Export screen', function (): void {
    Filament::setCurrentPanel(Filament::getPanel('magna'));
    docsTransferSeed();

    $this->actingAs(docsTransferAdmin());

    Livewire::test(DocsTransferPage::class)
        ->callAction('export', [
            'include_drafts' => true,
            'include_media' => true,
            'include_settings' => false,
        ])
        ->assertFileDownloaded();
});

it('imports an uploaded archive from the admin screen and reports the result', function (): void {
    Filament::setCurrentPanel(Filament::getPanel('magna'));
    docsTransferSeed();

    $archive = docsTransferExport();
    docsTransferWipe();

    // Mirror what the upload field leaves behind: the archive on the local disk.
    Storage::disk('local')->put('magna-docs-imports/upload.zip', (string) file_get_contents($archive));

    $this->actingAs(docsTransferAdmin());

    $page = Livewire::test(DocsTransferPage::class)
        ->call('runImport', [
            'archive' => 'magna-docs-imports/upload.zip',
            'conflict' => 'update',
            'import_media' => true,
            'import_settings' => false,
            'dry_run' => false,
        ]);

    /** @var array{counts: array<string, int>}|null $report */
    $report = $page->get('lastReport');

    expect($report)->not->toBeNull()
        ->and($report['counts']['pages_created'])->toBe(2)
        ->and(DocPage::query()->count())->toBe(2)
        // The uploaded copy is cleaned up once it has been read.
        ->and(Storage::disk('local')->exists('magna-docs-imports/upload.zip'))->toBeFalse();
});
