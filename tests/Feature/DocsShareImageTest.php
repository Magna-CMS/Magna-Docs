<?php

declare(strict_types=1);

/**
 * A shared docs link shows an image: the page's featured image when it has
 * one (and shows it), else the docs-wide share image from settings. With
 * neither, the card stays a plain summary.
 */

use Filament\Facades\Filament;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Magna\Auth\Role;
use Magna\Docs\Filament\Pages\DocsSettingsPage;
use Magna\Docs\Models\DocPage;
use Magna\Docs\Settings\DocsSettings;
use Magna\Docs\Transfer\DocsArchive;
use Magna\Docs\Transfer\DocsExporter;
use Magna\Docs\Transfer\DocsImporter;
use Magna\Docs\Transfer\ExportOptions;
use Magna\Docs\Transfer\ImportOptions;
use Magna\Testing\PluginTestCase;
use Magna\Users\User;

uses(PluginTestCase::class);

beforeEach(function (): void {
    $this->enablePlugin('magna-cms/docs');
    app('router')->getRoutes()->refreshNameLookups();
});

function docsShareSettingsImage(string $path): void
{
    $settings = DocsSettings::get();
    $settings->site_name = 'Acme Docs';
    $settings->og_image_path = $path;
    $settings->save();
}

/** A minimal PNG, so media can be exercised without a fixture file. */
function docsSharePng(): string
{
    $gd = imagecreatetruecolor(12, 6);
    if ($gd === false) {
        throw new RuntimeException('GD not available.');
    }
    ob_start();
    imagepng($gd);
    $data = ob_get_clean() ?: '';
    imagedestroy($gd);

    return $data;
}

it('shares the page\'s featured image as an absolute URL with a large card', function (): void {
    docsShareSettingsImage('docs-branding/share.png');

    DocPage::create([
        'title' => 'Hero Page', 'slug' => 'hero', 'content' => 'Body.', 'status' => 'published', 'order' => 0,
        'featured_image' => 'docs-featured/hero.jpg', 'show_featured_image' => true,
    ]);

    $image = url(Storage::disk('public')->url('docs-featured/hero.jpg'));
    expect($image)->toStartWith('http');

    $this->get('/docs/hero')
        ->assertOk()
        ->assertSee('<meta property="og:image" content="'.$image.'">', false)
        ->assertSee('<meta property="og:image:alt" content="Hero Page">', false)
        ->assertSee('<meta name="twitter:card" content="summary_large_image">', false)
        ->assertSee('<meta name="twitter:image" content="'.$image.'">', false)
        ->assertDontSee('docs-branding/share.png');
});

it('falls back to the settings share image when the page has no featured image', function (): void {
    docsShareSettingsImage('docs-branding/share.png');

    DocPage::create(['title' => 'Plain', 'slug' => 'plain', 'content' => 'Body.', 'status' => 'published', 'order' => 0]);

    $image = url(Storage::disk('public')->url('docs-branding/share.png'));

    $this->get('/docs/plain')
        ->assertOk()
        ->assertSee('<meta property="og:image" content="'.$image.'">', false)
        ->assertSee('<meta property="og:image:alt" content="Acme Docs">', false)
        ->assertSee('<meta name="twitter:card" content="summary_large_image">', false);
});

it('does not share a featured image the page hides, and uses the settings image instead', function (): void {
    docsShareSettingsImage('docs-branding/share.png');

    DocPage::create([
        'title' => 'Hidden', 'slug' => 'hidden', 'content' => 'Body.', 'status' => 'published', 'order' => 0,
        'featured_image' => 'docs-featured/secret.jpg', 'show_featured_image' => false,
    ]);

    $this->get('/docs/hidden')
        ->assertOk()
        ->assertDontSee('docs-featured/secret.jpg')
        ->assertSee(url(Storage::disk('public')->url('docs-branding/share.png')));
});

it('keeps a summary card and no og:image when there is no image at all', function (): void {
    DocPage::create(['title' => 'Plain', 'slug' => 'plain', 'content' => 'Body.', 'status' => 'published', 'order' => 0]);

    $this->get('/docs/plain')
        ->assertOk()
        ->assertSee('<meta name="twitter:card" content="summary">', false)
        ->assertDontSee('og:image', false)
        ->assertDontSee('twitter:image', false);
});

it('passes an already-absolute image URL through untouched', function (): void {
    DocPage::create([
        'title' => 'Remote', 'slug' => 'remote', 'content' => 'Body.', 'status' => 'published', 'order' => 0,
        'featured_image' => 'https://cdn.example.com/hero.png', 'show_featured_image' => true,
    ]);

    $this->get('/docs/remote')
        ->assertOk()
        ->assertSee('<meta property="og:image" content="https://cdn.example.com/hero.png">', false);
});

it('saves and removes the share image from the Docs settings screen', function (): void {
    Storage::fake('public');
    // Already a media-library path, so saving does not re-ingest it.
    Storage::disk('public')->put('media/share.png', docsSharePng());

    $user = User::factory()->create();
    $role = Role::factory()->create();
    $role->grant('panel.access', 'settings.manage');
    $user->assignRole($role);
    $this->actingAs($user);
    Filament::setCurrentPanel(Filament::getPanel('admin'));

    Livewire::test(DocsSettingsPage::class)
        ->assertSet('data.og_image_path', [])
        ->assertSee('Share image')
        ->assertSee('1200×630 recommended')
        ->set('data.og_image_path', ['media/share.png'])
        ->call('save')
        ->assertHasNoErrors();

    expect(DocsSettings::get()->og_image_path)->toBe('media/share.png');

    $page = Livewire::test(DocsSettingsPage::class);
    // FileUpload keys its state by upload id; only the stored path matters.
    expect(array_values((array) $page->get('data.og_image_path')))->toBe(['media/share.png']);
    $page->call('removeOgImage');

    expect(DocsSettings::get()->og_image_path)->toBe('');
});

it('carries the share image through a settings export and import', function (): void {
    Storage::fake('local');
    Storage::fake('public');
    Queue::fake();

    Storage::disk('public')->put('docs-branding/share.png', docsSharePng());
    docsShareSettingsImage('docs-branding/share.png');

    $result = app(DocsExporter::class)->export(new ExportOptions(includeMedia: true, includeSettings: true));
    $archive = Storage::disk($result->disk)->path($result->path);

    docsShareSettingsImage('');

    app(DocsImporter::class)->importArchive($archive, new ImportOptions(importMedia: true, importSettings: true));

    $path = DocsSettings::get()->og_image_path;
    expect($path)->not->toBe('')
        ->and(Storage::disk('public')->exists($path))->toBeTrue();
});

it('leaves the share image alone when importing settings from an older archive', function (): void {
    Storage::fake('local');
    Storage::fake('public');
    Queue::fake();

    $result = app(DocsExporter::class)->export(new ExportOptions(includeSettings: true));
    $archive = Storage::disk($result->disk)->path($result->path);

    // Rewrite settings.json the way a pre-1.3.0 export wrote it.
    $zip = new ZipArchive;
    $zip->open($archive);
    /** @var array<string, mixed> $settings */
    $settings = json_decode((string) $zip->getFromName(DocsArchive::SETTINGS), true);
    unset($settings['og_image_path']);
    $zip->addFromString(DocsArchive::SETTINGS, (string) json_encode($settings));
    $zip->close();

    docsShareSettingsImage('media/kept.png');

    app(DocsImporter::class)->importArchive($archive, new ImportOptions(importSettings: true));

    expect(DocsSettings::get()->og_image_path)->toBe('media/kept.png');
});
