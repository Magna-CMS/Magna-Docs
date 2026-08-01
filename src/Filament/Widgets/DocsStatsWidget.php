<?php

declare(strict_types=1);

namespace Magna\Docs\Filament\Widgets;

use Filament\Widgets\Widget;
use Illuminate\Support\Facades\Schema;
use Magna\Docs\Filament\Resources\DocCollectionResource;
use Magna\Docs\Filament\Resources\DocPageResource;
use Magna\Docs\Models\DocCollection;
use Magna\Docs\Models\DocPage;

class DocsStatsWidget extends Widget
{
    protected string $view = 'docs::filament.widgets.docs-stats';

    // Sort to the top of the dashboard.
    protected static ?int $sort = 0;

    /** Only visible when the docs plugin is active and the user has access. */
    public static function canView(): bool
    {
        if (! Schema::hasTable('docs_pages')) {
            return false;
        }

        // Guard: if the plugin is disabled its view namespace is never registered,
        // which would throw when Filament tries to render the widget.
        try {
            view('docs::filament.widgets.docs-stats');
        } catch (\InvalidArgumentException) {
            return false;
        }

        return DocPageResource::canViewAny();
    }

    /** @return array<string, mixed> */
    protected function getViewData(): array
    {
        $pagesUrl = DocPageResource::getUrl('index');

        return [
            'collections' => DocCollection::query()->count(),
            'published' => DocPage::query()->where('status', 'published')->count(),
            'drafts' => DocPage::query()->where('status', 'draft')->count(),
            'collectionsUrl' => DocCollectionResource::getUrl('index'),
            'publishedUrl' => $pagesUrl.'?tableFilters[status][value]=published',
            'draftsUrl' => $pagesUrl.'?tableFilters[status][value]=draft',
        ];
    }
}
