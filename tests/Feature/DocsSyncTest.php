<?php

declare(strict_types=1);

use Illuminate\Contracts\Console\Kernel;
use Magna\Docs\Commands\DocsSyncCommand;
use Magna\Docs\Models\DocCollection;
use Magna\Docs\Models\DocPage;
use Magna\Testing\PluginTestCase;

uses(PluginTestCase::class);

beforeEach(function (): void {
    $this->enablePlugin('magna-cms/docs');

    // The plugin is enabled mid-test, after PluginsServiceProvider::boot()
    // already registered plugin commands, so register it here. In a real CLI
    // the plugin is enabled before boot and RegistersCommands wires it
    // automatically (verified via PluginToolingTest-style provider path).
    $this->app->make(Kernel::class)->registerCommand(new DocsSyncCommand);

    $this->docsSource = sys_get_temp_dir().'/magna_docs_sync_'.uniqid();
    mkdir($this->docsSource, 0777, true);
    file_put_contents($this->docsSource.'/getting-started.md', "# Getting Started\n\nHello world.");
});

afterEach(function (): void {
    @unlink($this->docsSource.'/getting-started.md');
    @rmdir($this->docsSource);
});

it('imports markdown into a docs page, then is idempotent', function (): void {
    $this->artisan('magna:docs:sync', ['--source' => $this->docsSource, '--collection' => 'sdk'])
        ->assertSuccessful();

    $page = DocPage::query()->where('slug', 'getting-started')->first();
    expect($page)->not->toBeNull()
        ->and($page->title)->toBe('Getting Started')
        ->and($page->is_published)->toBeTrue()
        ->and($page->content)->toContain('Hello world.');

    // Re-run: no change, no duplicate.
    $this->artisan('magna:docs:sync', ['--source' => $this->docsSource, '--collection' => 'sdk'])
        ->assertSuccessful();

    expect(DocPage::query()->where('slug', 'getting-started')->count())->toBe(1);
});

it('strips a numeric filename prefix into a clean slug and page order', function (): void {
    file_put_contents($this->docsSource.'/25-widgets.md', "# Widgets\n\nBody.");

    try {
        $this->artisan('magna:docs:sync', ['--source' => $this->docsSource, '--collection' => 'sdk'])
            ->assertSuccessful();

        $page = DocPage::query()->where('slug', 'widgets')->firstOrFail();
        expect($page->title)->toBe('Widgets')
            ->and($page->order)->toBe(25)
            ->and(DocPage::query()->where('slug', '25-widgets')->exists())->toBeFalse();
    } finally {
        @unlink($this->docsSource.'/25-widgets.md');
    }
});

it('creates a collection per subdirectory with clean slug and order', function (): void {
    $dir = $this->docsSource.'/02-building-plugins';
    mkdir($dir, 0777, true);
    file_put_contents($dir.'/10-widgets.md', "# Widgets\n\nBody.");

    try {
        $this->artisan('magna:docs:sync', ['--source' => $this->docsSource])->assertSuccessful();

        $collection = DocCollection::query()->where('slug', 'building-plugins')->firstOrFail();
        expect($collection->title)->toBe('Building Plugins')
            ->and($collection->order)->toBe(1);

        $page = DocPage::query()->where('slug', 'widgets')->firstOrFail();
        expect($page->collection_id)->toBe($collection->id)
            ->and($page->order)->toBe(10);
    } finally {
        @unlink($dir.'/10-widgets.md');
        @rmdir($dir);
    }
});

it('updates an existing page when the markdown changes', function (): void {
    $this->artisan('magna:docs:sync', ['--source' => $this->docsSource, '--collection' => 'sdk'])
        ->assertSuccessful();

    file_put_contents($this->docsSource.'/getting-started.md', "# Getting Started\n\nUpdated body.");

    $this->artisan('magna:docs:sync', ['--source' => $this->docsSource, '--collection' => 'sdk'])
        ->assertSuccessful();

    $page = DocPage::query()->where('slug', 'getting-started')->firstOrFail();
    expect($page->content)->toContain('Updated body.')
        ->and(DocPage::query()->where('slug', 'getting-started')->count())->toBe(1);
});
