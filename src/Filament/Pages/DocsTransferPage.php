<?php

declare(strict_types=1);

namespace Magna\Docs\Filament\Pages;

use Filament\Actions\Action;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\HtmlString;
use Magna\Docs\Models\DocCollection;
use Magna\Docs\Models\DocPage;
use Magna\Docs\Models\DocPageTranslation;
use Magna\Docs\Transfer\ConflictStrategy;
use Magna\Docs\Transfer\DocsExporter;
use Magna\Docs\Transfer\DocsImporter;
use Magna\Docs\Transfer\ExportOptions;
use Magna\Docs\Transfer\ImportOptions;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Throwable;

/**
 * One screen, two buttons: export the whole docs site to a single .zip, and
 * import one back — on this install or another one.
 *
 * Both run inline rather than on the queue, deliberately: a plugin cannot
 * assume the host runs a queue worker, and a click that silently does nothing
 * because no worker is listening is worse than one that takes a few seconds.
 * The heavy lifting is streamed (see DocsExporter/DocsImporter), and the
 * matching artisan commands exist for installs large enough that a web request
 * is the wrong place to do it.
 */
class DocsTransferPage extends Page
{
    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-arrows-right-left';

    protected static string|\UnitEnum|null $navigationGroup = 'Magna Docs';

    protected static ?string $navigationLabel = 'Import / Export';

    protected static ?int $navigationSort = 98;

    protected static ?string $title = 'Import / Export docs';

    protected static ?string $slug = 'docs-transfer';

    protected string $view = 'docs::filament.docs-transfer';

    /**
     * Result of the last import run in this browser session, rendered by the view.
     *
     * @var array{dry_run: bool, counts: array<string, int>, errors: list<string>}|null
     */
    public ?array $lastReport = null;

    /** Moving content in or out is a content-management action, not a read. */
    public static function canAccess(): bool
    {
        return auth()->user()?->can('docs.pages.manage') ?? false;
    }

    /** @return array<string, int> */
    public function siteCounts(): array
    {
        return [
            'collections' => DocCollection::query()->count(),
            'pages' => DocPage::query()->count(),
            'translations' => DocPageTranslation::query()->count(),
        ];
    }

    /** @return array<int, Action> */
    protected function getHeaderActions(): array
    {
        return [
            $this->exportAction(),
            $this->importAction(),
        ];
    }

    // ── Export ───────────────────────────────────────────────────────────────

    private function exportAction(): Action
    {
        return Action::make('export')
            ->label('Export docs')
            ->icon('heroicon-o-arrow-down-tray')
            ->modalHeading('Export documentation')
            ->modalDescription('Builds one .zip containing every collection, page, translation and referenced image. Download starts when it finishes.')
            ->modalSubmitActionLabel('Build & download')
            ->schema([
                Select::make('collections')
                    ->label('Collections')
                    ->multiple()
                    ->options(fn (): array => DocCollection::query()->orderBy('order')->pluck('title', 'slug')->all())
                    ->placeholder('— everything —')
                    ->helperText('Leave empty to export the whole site.'),

                Toggle::make('include_drafts')
                    ->label('Include drafts and archived pages')
                    ->default(true),

                Toggle::make('include_media')
                    ->label('Include media files')
                    ->helperText('Featured images and any image referenced by a page body.')
                    ->default(true),

                Toggle::make('include_settings')
                    ->label('Include docs branding (site name, logo, favicon, footer)')
                    ->default(false),
            ])
            ->action(function (array $data): ?BinaryFileResponse {
                // A large site legitimately takes longer than the default
                // request budget; the work itself is streamed, not buffered.
                @set_time_limit(0);

                try {
                    $result = app(DocsExporter::class)->export(ExportOptions::fromArray($data));
                } catch (Throwable $e) {
                    Notification::make()
                        ->title('Export failed')
                        ->body($e->getMessage())
                        ->danger()
                        ->send();

                    return null;
                }

                Notification::make()
                    ->title('Export ready')
                    ->body(sprintf(
                        '%d collections, %d pages, %d translations, %d media files (%s).',
                        $result->counts['collections'] ?? 0,
                        $result->counts['pages'] ?? 0,
                        $result->counts['translations'] ?? 0,
                        $result->counts['media'] ?? 0,
                        $result->humanSize(),
                    ))
                    ->success()
                    ->send();

                return response()->download(
                    Storage::disk($result->disk)->path($result->path),
                    $result->filename,
                );
            });
    }

    // ── Import ───────────────────────────────────────────────────────────────

    private function importAction(): Action
    {
        return Action::make('import')
            ->label('Import docs')
            ->icon('heroicon-o-arrow-up-tray')
            ->color('gray')
            ->modalHeading('Import a docs archive')
            ->modalDescription(new HtmlString(
                'Upload a <strong>.zip</strong> produced by this tool on any Magna install. '
                .'Collections and pages are matched by slug — run a preview first if this site already has content.',
            ))
            ->modalSubmitActionLabel('Run import')
            ->schema([
                FileUpload::make('archive')
                    ->label('Docs archive (.zip)')
                    ->disk('local')
                    ->directory('magna-docs-imports')
                    ->visibility('private')
                    ->acceptedFileTypes(['application/zip', 'application/x-zip-compressed'])
                    ->maxSize(2_097_152) // 2 GB, in KB
                    ->required(),

                Select::make('conflict')
                    ->label('When a slug already exists')
                    ->options(ConflictStrategy::options())
                    ->default(ConflictStrategy::Update->value)
                    ->required(),

                Toggle::make('import_media')
                    ->label('Restore media files')
                    ->helperText('Images are re-checked and re-processed through the media library on the way in.')
                    ->default(true),

                Toggle::make('import_settings')
                    ->label('Apply the archive\'s docs branding')
                    ->helperText('Site name, logo, favicon and footer text. The custom domain is never imported.')
                    ->default(false),

                Toggle::make('dry_run')
                    ->label('Preview only — do not write anything')
                    ->helperText('Reports exactly what would be created, updated and skipped.')
                    ->default(false),
            ])
            ->action(fn (array $data) => $this->runImport($data));
    }

    /**
     * The import action's body, as a page method rather than an inline closure
     * so it can be exercised directly without simulating a file upload.
     *
     * @param  array<string, mixed>  $data
     */
    public function runImport(array $data): void
    {
        @set_time_limit(0);

        $path = (string) ($data['archive'] ?? '');
        $options = ImportOptions::fromArray($data);

        try {
            $report = app(DocsImporter::class)->import('local', $path, $options);
        } catch (Throwable $e) {
            Notification::make()
                ->title('Import failed')
                ->body($e->getMessage())
                ->danger()
                ->send();

            return;
        } finally {
            // The upload is a copy of the archive; keep nothing private lying
            // around on disk once it has been read.
            Storage::disk('local')->delete($path);
        }

        $this->lastReport = $report->toArray();

        $notification = Notification::make()
            ->title($options->dryRun ? 'Preview complete — nothing was written' : 'Import complete')
            ->body($report->summary());

        if ($report->hasErrors()) {
            $notification->warning();
        } else {
            $notification->success();
        }

        $notification->send();
    }
}
