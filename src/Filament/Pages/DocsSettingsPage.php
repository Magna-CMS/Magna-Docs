<?php

declare(strict_types=1);

namespace Magna\Docs\Filament\Pages;

use Filament\Actions\Action;
use Filament\Actions\Action as FormAction;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Actions as FormActions;
use Filament\Schemas\Components\Group;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Livewire\Attributes\On;
use Magna\Auth\Role;
use Magna\Docs\Settings\DocsSettings;
use Magna\Docs\Support\IngestsMedia;

class DocsSettingsPage extends Page implements HasForms
{
    use IngestsMedia;
    use InteractsWithForms;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-cog-6-tooth';

    protected static string|\UnitEnum|null $navigationGroup = 'Magna Docs';

    protected static ?string $navigationLabel = 'Settings';

    protected static ?int $navigationSort = 99;

    protected static ?string $title = 'Docs Settings';

    protected static ?string $slug = 'docs-settings';

    protected string $view = 'docs::filament.docs-settings';

    /** @var array<string, mixed> */
    public array $data = [];

    /**
     * A page without this is reachable by URL to anyone who can open the panel,
     * whatever the navigation shows. These are system settings, so they take
     * the same permission the core settings screens do.
     */
    public static function canAccess(): bool
    {
        return auth()->user()?->can('settings.manage') ?? false;
    }

    public function mount(): void
    {
        $settings = DocsSettings::get();

        $this->form->fill([
            'custom_domain' => $settings->custom_domain,
            'site_name' => $settings->site_name,
            'logo_path' => $settings->logo_path ? [$settings->logo_path] : [],
            'favicon_path' => $settings->favicon_path ? [$settings->favicon_path] : [],
            'copyright_text' => $settings->copyright_text,
            'editor_roles' => $settings->editor_roles,
        ]);
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->statePath('data')
            ->components([
                Section::make('Domain')
                    ->description('Serve the docs on a custom domain instead of the default /docs path.')
                    ->schema([
                        TextInput::make('custom_domain')
                            ->label('Custom domain')
                            ->placeholder('docs.yoursite.com')
                            ->helperText('Leave empty to serve docs at /docs. Do not include https://')
                            ->nullable(),
                    ]),

                Section::make('Branding')
                    ->description('Override the CMS defaults for the docs frontend. Leave blank to inherit.')
                    ->columns(2)
                    ->schema([
                        TextInput::make('site_name')
                            ->label('Docs site name')
                            ->placeholder('e.g. Acme Docs')
                            ->helperText('Shown in the header and browser tab. Falls back to CMS site name.')
                            ->maxLength(100)
                            ->columnSpanFull(),

                        // Logo — file upload grouped with its library picker button
                        Group::make([
                            FileUpload::make('logo_path')
                                ->label('Logo')
                                ->image()
                                ->disk('public')
                                ->directory('docs-branding')
                                ->imageEditor()
                                ->imageEditorAspectRatios([null, '16:9', '4:3', '1:1', '3:1'])
                                ->panelLayout('integrated')
                                ->helperText('Upload and crop a logo, or pick one from the media library. Use the ✕ on the preview to remove it and fall back to the CMS logo.')
                                ->nullable(),

                            FormActions::make([
                                FormAction::make('browseLogoLibrary')
                                    ->label('Browse media library')
                                    ->icon('heroicon-m-photo')
                                    ->link()
                                    ->size('sm')
                                    ->extraAttributes(['class' => 'ds-browse-link'])
                                    ->action(fn () => $this->dispatch('magna:open-media-picker', target: 'logo')),
                                FormAction::make('removeLogo')
                                    ->label('Remove current logo')
                                    ->icon('heroicon-m-trash')
                                    ->link()
                                    ->color('danger')
                                    ->size('sm')
                                    ->visible(fn (): bool => filled(array_filter((array) ($this->data['logo_path'] ?? []))))
                                    ->action(fn () => $this->removeLogo()),
                            ]),
                        ]),

                        // Favicon — file upload grouped with its library picker button
                        Group::make([
                            FileUpload::make('favicon_path')
                                ->label('Favicon')
                                ->image()
                                ->disk('public')
                                ->directory('docs-branding')
                                ->imageResizeTargetWidth('64')
                                ->imageResizeTargetHeight('64')
                                ->panelLayout('integrated')
                                ->helperText('32 × 32 or 64 × 64 px ICO/PNG/SVG. Falls back to the CMS favicon when empty.')
                                ->nullable(),

                            FormActions::make([
                                FormAction::make('browseFaviconLibrary')
                                    ->label('Browse media library')
                                    ->icon('heroicon-m-photo')
                                    ->link()
                                    ->size('sm')
                                    ->extraAttributes(['class' => 'ds-browse-link'])
                                    ->action(fn () => $this->dispatch('magna:open-media-picker', target: 'favicon')),
                                FormAction::make('removeFavicon')
                                    ->label('Remove current favicon')
                                    ->icon('heroicon-m-trash')
                                    ->link()
                                    ->color('danger')
                                    ->size('sm')
                                    ->visible(fn (): bool => filled(array_filter((array) ($this->data['favicon_path'] ?? []))))
                                    ->action(fn () => $this->removeFavicon()),
                            ]),
                        ]),
                    ]),

                Section::make('Footer')
                    ->description('Shown at the bottom of every docs page. The right side always shows a "Made with Magna Docs" link.')
                    ->schema([
                        TextInput::make('copyright_text')
                            ->label('Copyright text')
                            ->placeholder('© '.date('Y').' Your Company. All rights reserved.')
                            ->helperText('Displayed on the left of the docs footer. Leave blank to hide it.')
                            ->maxLength(200)
                            ->nullable(),
                    ]),

                Section::make('Permissions')
                    ->description('Control which admin roles can create and edit docs pages. Leave empty to allow all admin roles.')
                    ->schema([
                        Select::make('editor_roles')
                            ->label('Who can create / edit pages')
                            ->multiple()
                            ->options(fn (): array => Role::query()->orderBy('name')->pluck('name', 'handle')->all())
                            ->placeholder('— all roles (no restriction) —')
                            ->helperText('Select one or more roles. Users with any of these roles will have editor access to Docs pages.'),
                    ]),
            ]);
    }

    // ── Global media picker result ────────────────────────────────────────────

    /**
     * Receives the result from the global <livewire:magna-media-picker />.
     * The 'target' string identifies which field requested the pick.
     */
    #[On('magna:media-selected')]
    public function onMediaSelected(string $path, string $url, string $disk, string $target): void
    {
        match ($target) {
            'logo' => $this->data['logo_path'] = [$path],
            'favicon' => $this->data['favicon_path'] = [$path],
            default => null,
        };

        // Re-hydrate the form so the FileUpload previews the selected image.
        $this->form->fill($this->data);
    }

    // ── Remove helpers ────────────────────────────────────────────────────────

    public function removeLogo(): void
    {
        $this->data['logo_path'] = [];
        $this->form->fill($this->data);

        $settings = DocsSettings::get();
        $settings->logo_path = '';
        $settings->save();

        Notification::make()->title('Logo removed — the CMS logo will be used.')->success()->send();
    }

    public function removeFavicon(): void
    {
        $this->data['favicon_path'] = [];
        $this->form->fill($this->data);

        $settings = DocsSettings::get();
        $settings->favicon_path = '';
        $settings->save();

        Notification::make()->title('Favicon removed — the CMS favicon will be used.')->success()->send();
    }

    // ── Save ──────────────────────────────────────────────────────────────────

    public function save(): void
    {
        /** @var array<string, mixed> $data */
        $data = $this->form->getState();

        $logo = is_array($data['logo_path'] ?? null)
            ? (string) (array_values($data['logo_path'])[0] ?? '')
            : (string) ($data['logo_path'] ?? '');

        $favicon = is_array($data['favicon_path'] ?? null)
            ? (string) (array_values($data['favicon_path'])[0] ?? '')
            : (string) ($data['favicon_path'] ?? '');

        // Register freshly-uploaded branding files into the media library.
        if ($logo !== '') {
            $logo = $this->ingestToMedia($logo) ?? $logo;
        }
        if ($favicon !== '') {
            $favicon = $this->ingestToMedia($favicon) ?? $favicon;
        }

        $settings = DocsSettings::get();
        $settings->custom_domain = trim($data['custom_domain'] ?? '');
        $settings->site_name = trim($data['site_name'] ?? '');
        $settings->logo_path = $logo;
        $settings->favicon_path = $favicon;
        $settings->copyright_text = trim($data['copyright_text'] ?? '');
        $settings->editor_roles = (array) ($data['editor_roles'] ?? []);
        $settings->save();

        Notification::make()
            ->title('Docs settings saved.')
            ->success()
            ->send();
    }

    /** @return array<int, Action> */
    protected function getHeaderActions(): array
    {
        return [
            Action::make('save')
                ->label('Save settings')
                ->icon('heroicon-o-check')
                ->action(fn () => $this->save()),
        ];
    }
}
