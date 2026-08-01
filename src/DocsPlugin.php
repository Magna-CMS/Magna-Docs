<?php

declare(strict_types=1);

namespace Magna\Docs;

use Illuminate\Console\Command;
use Magna\Contracts\RegistersAdminResources;
use Magna\Contracts\RegistersCommands;
use Magna\Contracts\RegistersDashboardWidgets;
use Magna\Contracts\RegistersSettingsPages;
use Magna\Docs\Commands\DocsExportCommand;
use Magna\Docs\Commands\DocsImportCommand;
use Magna\Docs\Commands\DocsSyncCommand;
use Magna\Docs\Filament\Pages\DocsSettingsPage;
use Magna\Docs\Filament\Pages\DocsTransferPage;
use Magna\Docs\Filament\Resources\DocCollectionResource;
use Magna\Docs\Filament\Resources\DocPageResource;
use Magna\Docs\Filament\Widgets\DocsStatsWidget;
use Magna\Plugins\Plugin;

class DocsPlugin extends Plugin implements RegistersAdminResources, RegistersCommands, RegistersDashboardWidgets, RegistersSettingsPages
{
    /** @return list<class-string> */
    public function dashboardWidgets(): array
    {
        return [DocsStatsWidget::class];
    }

    /** @return list<class-string<Command>> */
    public function commands(): array
    {
        return [
            DocsSyncCommand::class,
            DocsExportCommand::class,
            DocsImportCommand::class,
        ];
    }

    public function boot(): void
    {
        $this->loadViewsFrom('resources/views', 'docs');
        $this->loadRoutesFrom('routes/web.php', 'web');
    }

    public function adminResources(): array
    {
        return [
            DocCollectionResource::class,
            DocPageResource::class,
        ];
    }

    /**
     * Returns the Filament page classes that should be registered in the admin
     * panel. AdminPanelProvider calls this via resolvePluginPages().
     * The Settings button in the Installed Plugins page links to settingsPages()[0],
     * so DocsSettingsPage must stay first.
     *
     * @return list<class-string>
     */
    public function settingsPages(): array
    {
        return [
            DocsSettingsPage::class,
            DocsTransferPage::class,
        ];
    }
}
