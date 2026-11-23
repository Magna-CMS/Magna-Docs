<?php

declare(strict_types=1);

/**
 * The "Made with Magna Docs" footer credit is a setting (show_credit).
 *
 * It stays on by default, so a site that never opens the setting looks the
 * same after the update. A site that must not name the software it runs on
 * turns it off, and the public footer then keeps only the site's own text.
 */

use Filament\Facades\Filament;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Magna\Auth\Role;
use Magna\Docs\Filament\Pages\DocsSettingsPage;
use Magna\Docs\Models\DocPage;
use Magna\Docs\Settings\DocsSettings;
use Magna\Docs\Transfer\DocsExporter;
use Magna\Docs\Transfer\DocsImporter;
use Magna\Docs\Transfer\ExportOptions;
use Magna\Docs\Transfer\ImportOptions;
use Magna\Testing\PluginTestCase;
use Magna\Users\User;

uses(PluginTestCase::class);

beforeEach(function (): void {
    $this->enablePlugin('magna-cms/docs');
    // Plugin routes register after boot in tests; refresh the name lookups.
    app('router')->getRoutes()->refreshNameLookups();

    DocPage::create([
        'title' => 'Getting Started',
        'slug' => 'getting-started',
        'content' => "# Hi\n\nWelcome to the docs.",
        'status' => 'published',
        'order' => 0,
    ]);

    $settings = DocsSettings::get();
    $settings->copyright_text = '(c) 2026 Test Co';
    $settings->save();
});

it('shows the credit by default', function (): void {
    expect(DocsSettings::get()->show_credit)->toBeTrue();

    $this->get('/docs')
        ->assertOk()
        ->assertSee('Made with Magna Docs')
        ->assertSee('/Magna-Docs')
        ->assertSee('(c) 2026 Test Co');
});

it('leaves the credit out when show_credit is off, and keeps the site\'s own footer text', function (): void {
    $settings = DocsSettings::get();
    $settings->show_credit = false;
    $settings->save();

    $this->get('/docs')
        ->assertOk()
        ->assertSee('Getting Started')
        ->assertSee('(c) 2026 Test Co')
        ->assertDontSee('Made with Magna Docs')
        ->assertDontSee('/Magna-Docs');

    $this->get('/docs/getting-started')
        ->assertOk()
        ->assertDontSee('Made with Magna Docs');
});

it('saves the credit switch from the Docs settings screen', function (): void {
    $user = User::factory()->create();
    $role = Role::factory()->create();
    $role->grant('panel.access', 'settings.manage');
    $user->assignRole($role);
    $this->actingAs($user);
    Filament::setCurrentPanel(Filament::getPanel('admin'));

    Livewire::test(DocsSettingsPage::class)
        ->assertSet('data.show_credit', true)
        ->set('data.show_credit', false)
        ->call('save')
        ->assertHasNoErrors();

    expect(DocsSettings::get()->show_credit)->toBeFalse();

    Livewire::test(DocsSettingsPage::class)->assertSet('data.show_credit', false);
});

it('carries the credit switch through a settings export and import', function (): void {
    Storage::fake('local');
    Storage::fake('public');
    Queue::fake();

    $settings = DocsSettings::get();
    $settings->show_credit = false;
    $settings->save();

    $result = app(DocsExporter::class)->export(new ExportOptions(includeSettings: true));
    $archive = Storage::disk($result->disk)->path($result->path);

    $settings->show_credit = true;
    $settings->save();

    app(DocsImporter::class)->importArchive($archive, new ImportOptions(importSettings: true));

    expect(DocsSettings::get()->show_credit)->toBeFalse();
});
