<?php

declare(strict_types=1);

/**
 * The admin side of translations: a page translation's excerpt and meta in
 * the page editor, and a collection's translated names in its form.
 */

use Filament\Facades\Filament;
use Illuminate\Support\Facades\Route;
use Livewire\Livewire;
use Magna\Auth\Role;
use Magna\Docs\Filament\Resources\DocCollectionResource\Pages\EditDocCollection;
use Magna\Docs\Filament\Resources\DocPageResource\Pages\EditDocPage;
use Magna\Docs\Models\DocCollection;
use Magna\Docs\Models\DocPage;
use Magna\Testing\PluginTestCase;
use Magna\Users\User;

uses(PluginTestCase::class);

beforeEach(function (): void {
    $this->enablePlugin('magna-cms/docs');
    app('router')->getRoutes()->refreshNameLookups();

    Filament::setCurrentPanel(Filament::getPanel('magna'));
    // The resources' redirect targets, which the test panel does not register.
    Route::get('/__docs-pages', fn () => '')->name('filament.magna.resources.doc-pages.index');
    Route::get('/__docs-pages/{record}/edit', fn () => '')->name('filament.magna.resources.doc-pages.edit');
    Route::get('/__docs-collections', fn () => '')->name('filament.magna.resources.doc-collections.index');
    Route::get('/__docs-collections/create', fn () => '')->name('filament.magna.resources.doc-collections.create');
    Route::get('/__docs-collections/{record}/edit', fn () => '')->name('filament.magna.resources.doc-collections.edit');

    $role = Role::factory()->create();
    $role->grant('docs.pages.view', 'docs.pages.manage');
    $user = User::factory()->create(['two_factor_confirmed_at' => now()]);
    $user->assignRole($role);
    $this->actingAs($user);
});

it('edits a translation\'s excerpt and meta without touching the English page', function (): void {
    $page = DocPage::create([
        'title' => 'Base', 'slug' => 'base-tr', 'content' => 'en body', 'status' => 'published', 'order' => 0,
        'excerpt' => 'English excerpt.', 'meta_title' => 'English meta', 'meta_description' => 'English description.',
    ]);

    $component = Livewire::test(EditDocPage::class, ['record' => $page->getKey()])
        ->call('addLanguage', 'ml')
        ->assertSet('editingLocale', 'ml')
        ->assertSee('Translation summary & SEO')
        ->set('data.title', 'അടിസ്ഥാനം')
        ->set('data.content', 'മലയാളം')
        ->set('data.translation_excerpt', 'മലയാളം സംഗ്രഹം.')
        ->set('data.translation_meta_title', 'മെറ്റാ ശീർഷകം')
        ->set('data.translation_meta_description', 'മെറ്റാ വിവരണം.')
        ->call('saveDraft');

    $translation = $page->translations()->where('locale', 'ml')->firstOrFail();
    expect($translation->only(['title', 'excerpt', 'meta_title', 'meta_description']))->toBe([
        'title' => 'അടിസ്ഥാനം',
        'excerpt' => 'മലയാളം സംഗ്രഹം.',
        'meta_title' => 'മെറ്റാ ശീർഷകം',
        'meta_description' => 'മെറ്റാ വിവരണം.',
    ]);

    // Switching away and back reloads the saved values.
    $component->set('editingLocale', 'en')
        ->assertSet('data.meta_title', 'English meta')
        ->set('editingLocale', 'ml')
        ->assertSet('data.translation_meta_title', 'മെറ്റാ ശീർഷകം');

    // Saving the English page afterwards keeps both sides apart.
    $component->set('editingLocale', 'en')->call('saveDraft');

    expect($page->fresh()?->only(['excerpt', 'meta_title', 'meta_description']))->toBe([
        'excerpt' => 'English excerpt.',
        'meta_title' => 'English meta',
        'meta_description' => 'English description.',
    ]);
});

it('stores blank translation meta as null', function (): void {
    $page = DocPage::create(['title' => 'Base', 'slug' => 'base-blank', 'content' => 'x', 'status' => 'published', 'order' => 0]);

    Livewire::test(EditDocPage::class, ['record' => $page->getKey()])
        ->call('addLanguage', 'hi')
        ->set('data.translation_excerpt', '   ')
        ->call('saveDraft');

    expect($page->translations()->where('locale', 'hi')->value('excerpt'))->toBeNull();
});

it('saves translated collection names from the collection form', function (): void {
    $collection = DocCollection::create(['title' => 'Guides', 'slug' => 'guides-ed', 'order' => 0, 'is_public' => true]);

    Livewire::test(EditDocCollection::class, ['record' => $collection->getKey()])
        ->assertSee('Translations')
        ->set('data.translations', [
            'item-1' => ['locale' => 'ml', 'title' => 'ഗൈഡുകൾ', 'description' => null],
            'item-2' => ['locale' => 'ta', 'title' => 'வழிகாட்டிகள்', 'description' => 'எப்படி.'],
        ])
        ->call('save')
        ->assertHasNoErrors();

    expect($collection->translations()->orderBy('locale')->pluck('title', 'locale')->all())
        ->toBe(['ml' => 'ഗൈഡുകൾ', 'ta' => 'வழிகாட்டிகள்']);
});

it('refuses the same language twice on one collection', function (): void {
    $collection = DocCollection::create(['title' => 'Guides', 'slug' => 'guides-dup', 'order' => 0, 'is_public' => true]);

    Livewire::test(EditDocCollection::class, ['record' => $collection->getKey()])
        ->set('data.translations', [
            'item-1' => ['locale' => 'ml', 'title' => 'ഒന്ന്', 'description' => null],
            'item-2' => ['locale' => 'ml', 'title' => 'രണ്ട്', 'description' => null],
        ])
        ->call('save')
        ->assertHasErrors();

    expect($collection->translations()->count())->toBe(0);
});
