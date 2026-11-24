<?php

declare(strict_types=1);

namespace Magna\Docs\Filament\Resources\DocPageResource\Pages;

use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\MarkdownEditor;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\HtmlString;
use Illuminate\Support\Str;
use Livewire\Attributes\On;
use Magna\Docs\Filament\Resources\DocPageResource;
use Magna\Docs\Models\DocCollection;
use Magna\Docs\Models\DocPage;
use Magna\Docs\Support\DocLocales;
use Magna\Docs\Support\IngestsMedia;

class EditDocPage extends EditRecord
{
    use IngestsMedia;

    protected static string $resource = DocPageResource::class;

    protected string $view = 'docs::filament.doc-page-editor';

    public bool $isEditMode = true;

    public string $editorStatus = '';

    public ?string $libraryImagePath = null;

    public ?string $libraryImageUrl = null;

    /** Locale currently being edited in the canvas ('en' = the page itself). */
    public string $editingLocale = 'en';

    /** @var list<string> Locales this page has content for (always includes 'en'). */
    public array $availableLocales = ['en'];

    protected function getHeaderActions(): array
    {
        return [];
    }

    protected function getSavedNotification(): ?Notification
    {
        return null;
    }

    public function mount(int|string $record): void
    {
        parent::mount($record);
        $this->refreshAvailableLocales();
    }

    private function refreshAvailableLocales(): void
    {
        $this->availableLocales = array_merge(
            ['en'],
            $this->record->translations()->orderBy('locale')->pluck('locale')->all(),
        );
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->statePath('data')
            ->columns(1)
            ->components([
                TextInput::make('title')
                    ->hiddenLabel()
                    ->required()
                    ->placeholder('Add Title')
                    ->live(onBlur: true)
                    // Re-keyed per locale so switching language rebuilds the field.
                    ->key('doc-title-'.$this->editingLocale)
                    ->extraInputAttributes([
                        'class' => 'doc-editor-title-input',
                        'autocomplete' => 'off',
                    ])
                    ->afterStateUpdated(function (?string $state, callable $set, ?string $old, Get $get): void {
                        // Slug only auto-syncs while editing the default (English) page.
                        if ($this->editingLocale !== 'en') {
                            return;
                        }
                        $currentSlug = $get('slug');
                        if (blank($currentSlug) || $currentSlug === Str::slug($old ?? '')) {
                            $set('slug', Str::slug($state ?? ''));
                        }
                    }),

                MarkdownEditor::make('content')
                    ->hiddenLabel()
                    ->key('doc-content-'.$this->editingLocale)
                    ->toolbarButtons([
                        'bold', 'italic', 'strike', 'link',
                        'heading', 'bulletList', 'orderedList',
                        'blockquote', 'codeBlock', 'table',
                        'attachFiles', 'undo', 'redo',
                    ])
                    ->fileAttachmentsDirectory('docs-attachments')
                    ->extraAttributes(['class' => 'doc-editor-content']),
            ]);
    }

    public function sidebarForm(Schema $schema): Schema
    {
        return $schema
            ->statePath('data')
            ->components([
                Section::make('Summary')
                    ->schema([
                        Select::make('status')
                            ->label('Status')
                            ->options([
                                'draft' => 'Draft',
                                'published' => 'Published',
                                'archived' => 'Archived (unpublished)',
                            ])
                            ->default('draft')
                            ->native(false)
                            ->selectablePlaceholder(false)
                            ->live()
                            ->visible(fn (): bool => $this->editingLocale === 'en')
                            ->afterStateUpdated(function (string $state): void {
                                if ($this->record === null) {
                                    return;
                                }
                                $attributes = ['status' => $state];
                                if ($state === 'published' && blank($this->record->published_at)) {
                                    $attributes['published_at'] = now();
                                }
                                $this->record->update($attributes);
                                $this->editorStatus = $state === 'published' ? 'published' : 'draft_saved';
                                Notification::make()->title('Status changed to '.ucfirst($state))->success()->send();
                            }),

                        DateTimePicker::make('published_at')
                            ->label('Publish date')
                            ->visible(fn (Get $get): bool => $get('status') === 'published')
                            ->default(now()),

                        Placeholder::make('_visibility')
                            ->label('Visibility')
                            ->content('Public'),

                        Placeholder::make('_author')
                            ->label('Author')
                            ->content(fn (): string => auth()->user()?->name ?? 'Admin'),
                    ]),

                Section::make('Permalink')
                    ->collapsible()
                    ->visible(fn (): bool => $this->editingLocale === 'en')
                    ->schema([
                        TextInput::make('slug')
                            ->hiddenLabel()
                            ->required()
                            ->unique(
                                table: 'docs_pages',
                                column: 'slug',
                                ignorable: fn (): ?DocPage => $this->record,
                            )
                            ->placeholder('page-slug')
                            ->prefix('/docs/')
                            ->helperText('Changing the slug will break existing links.'),
                    ]),

                Section::make('Featured Image')
                    ->collapsible()
                    ->visible(fn (): bool => $this->editingLocale === 'en')
                    ->schema([
                        FileUpload::make('featured_image')
                            ->hiddenLabel()
                            ->image()
                            ->disk('public')
                            ->directory('docs-featured')
                            ->imageResizeMode('cover')
                            ->imageCropAspectRatio('16:9')
                            ->imageResizeTargetWidth('1200')
                            ->imageResizeTargetHeight('675')
                            ->panelAspectRatio('16:9')
                            ->panelLayout('integrated')
                            ->visible(fn (): bool => blank($this->libraryImageUrl)),

                        // When an image is chosen from the media library we render
                        // our own preview here (FilePond can't display a file it
                        // didn't upload), shown in place of the hidden dropzone.
                        Placeholder::make('_library_preview')
                            ->hiddenLabel()
                            ->visible(fn (): bool => filled($this->libraryImageUrl))
                            ->content(fn (): HtmlString => new HtmlString(
                                '<div class="de-featured-preview">'
                                .'<img src="'.e((string) $this->libraryImageUrl).'" alt="Featured image">'
                                .'<button type="button" class="de-featured-preview-remove" wire:click="clearLibraryImage" title="Remove image">&times;</button>'
                                .'<span class="de-featured-preview-tag">From media library</span>'
                                .'</div>'
                            )),

                        Placeholder::make('_browse_media')
                            ->hiddenLabel()
                            ->content(new HtmlString(
                                '<button type="button" class="de-browse-link" onclick="Livewire.dispatch(\'magna:open-media-picker\', { target: \'featured-image\' })">'
                                .'<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="18" height="18" rx="2"/><circle cx="8.5" cy="8.5" r="1.5"/><polyline points="21 15 16 10 5 21"/></svg>'
                                .'Browse media library</button>'
                            )),

                        Toggle::make('show_featured_image')
                            ->label('Show featured image on the page')
                            ->helperText('When on, the image appears below the title on the published page.')
                            ->default(true),
                    ]),

                Section::make('Organisation')
                    ->collapsible()
                    ->visible(fn (): bool => $this->editingLocale === 'en')
                    ->schema([
                        Select::make('collection_id')
                            ->label('Collection')
                            ->options(fn (): array => DocCollection::query()->orderBy('title')->pluck('title', 'id')->all())
                            ->searchable()
                            ->createOptionForm([
                                TextInput::make('title')
                                    ->required()
                                    ->live(onBlur: true)
                                    ->afterStateUpdated(fn (?string $state, callable $set) => $set('slug', Str::slug($state ?? ''))),
                                TextInput::make('slug')
                                    ->required()
                                    ->unique('doc_collections', 'slug'),
                                Textarea::make('description')->rows(2),
                                Toggle::make('is_public')->default(true)->label('Publicly visible'),
                            ])
                            ->createOptionUsing(fn (array $data): int => DocCollection::create($data)->id)
                            ->placeholder('— uncategorised —'),

                        Select::make('parent_id')
                            ->label('Parent page')
                            ->options(fn (): array => DocPage::query()
                                ->when($this->record?->id, fn ($q) => $q->whereKeyNot($this->record->id))
                                ->orderBy('title')
                                ->pluck('title', 'id')
                                ->all()
                            )
                            ->searchable()
                            ->placeholder('— top level —'),

                        TextInput::make('order')
                            ->label('Order')
                            ->numeric()
                            ->default(0)
                            ->helperText('Lower numbers appear first in the sidebar.'),
                    ]),

                // The translation's own summary and search-result text. Shown
                // only while a translation is open; the English page keeps its
                // SEO section below. Saved by saveTranslation(), never by the
                // page save (see mutateFormDataBeforeSave()).
                Section::make('Translation summary & SEO')
                    ->collapsible()
                    ->visible(fn (): bool => $this->editingLocale !== 'en')
                    ->schema([
                        Textarea::make('translation_excerpt')
                            ->label('Excerpt')
                            ->rows(2)
                            ->maxLength(255)
                            ->placeholder('Short summary shown under the title…')
                            ->helperText('Shown under the title and in search results. Leave blank for none.'),

                        TextInput::make('translation_meta_title')
                            ->label('Meta title')
                            ->maxLength(70)
                            ->placeholder('Overrides the translated title in search results')
                            ->helperText('Recommended: 50–70 characters.'),

                        Textarea::make('translation_meta_description')
                            ->label('Meta description')
                            ->rows(3)
                            ->maxLength(160)
                            ->placeholder('160-character summary shown in search results…')
                            ->helperText('Falls back to the excerpt, then the start of the content.'),
                    ]),

                Section::make('SEO')
                    ->collapsible()
                    ->collapsed()
                    ->visible(fn (): bool => $this->editingLocale === 'en')
                    ->schema([
                        TextInput::make('meta_title')
                            ->label('Meta title')
                            ->maxLength(70)
                            ->placeholder('Overrides page title in search results')
                            ->helperText('Recommended: 50–70 characters.'),

                        Textarea::make('meta_description')
                            ->label('Meta description')
                            ->rows(3)
                            ->maxLength(160)
                            ->placeholder('160-character summary shown in search results…')
                            ->helperText('Recommended: 120–160 characters.'),
                    ]),
            ]);
    }

    #[On('magna:media-selected')]
    public function onMediaSelected(string $path, string $url, string $disk, string $target): void
    {
        if ($target === 'featured-image') {
            $this->selectMediaFile($path, $disk);
        }
    }

    /** Select an image from the Magna media library. */
    public function selectMediaFile(string $path, string $disk = 'public'): void
    {
        $this->libraryImagePath = $path;
        $this->libraryImageUrl = Storage::disk($disk)->url($path);
    }

    /** Clear a media-library selection and restore the upload dropzone. */
    public function clearLibraryImage(): void
    {
        $this->libraryImagePath = null;
        $this->libraryImageUrl = null;
    }

    // ── Translations ──────────────────────────────────────────────────────

    public function updatedEditingLocale(string $value): void
    {
        if (! in_array($value, $this->availableLocales, true)) {
            $value = 'en';
            $this->editingLocale = 'en';
        }

        $this->loadLocaleContent($value);
    }

    private function loadLocaleContent(string $locale): void
    {
        if ($locale === 'en') {
            $this->data['title'] = $this->record->title;
            $this->data['content'] = $this->record->content;
            unset($this->data['translation_excerpt'], $this->data['translation_meta_title'], $this->data['translation_meta_description']);

            return;
        }

        $translation = $this->record->translations()->firstWhere('locale', $locale);
        $this->data['title'] = $translation?->title ?? '';
        $this->data['content'] = $translation?->content ?? '';
        $this->data['translation_excerpt'] = $translation->excerpt ?? '';
        $this->data['translation_meta_title'] = $translation->meta_title ?? '';
        $this->data['translation_meta_description'] = $translation->meta_description ?? '';
    }

    public function addLanguage(string $locale): void
    {
        if ($locale === 'en' || ! DocLocales::isValid($locale) || in_array($locale, $this->availableLocales, true)) {
            return;
        }

        // Seed the new translation with the English title as a starting point.
        $this->record->translations()->create([
            'locale' => $locale,
            'title' => $this->record->title,
            'content' => '',
        ]);

        $this->refreshAvailableLocales();
        $this->editingLocale = $locale;
        $this->loadLocaleContent($locale);

        Notification::make()->title(DocLocales::englishName($locale).' translation added')->success()->send();
    }

    public function deleteLanguage(): void
    {
        if ($this->editingLocale === 'en') {
            return;
        }

        $locale = $this->editingLocale;
        $this->record->translations()->where('locale', $locale)->delete();

        $this->editingLocale = 'en';
        $this->refreshAvailableLocales();
        $this->loadLocaleContent('en');

        Notification::make()->title(DocLocales::englishName($locale).' translation removed')->success()->send();
    }

    private function saveTranslation(): void
    {
        $state = $this->form->getState();

        $this->record->translations()->updateOrCreate(
            ['locale' => $this->editingLocale],
            [
                'title' => (string) ($state['title'] ?? ''),
                'excerpt' => $this->translationField('translation_excerpt', 255),
                'meta_title' => $this->translationField('translation_meta_title', 255),
                'meta_description' => $this->translationField('translation_meta_description', 500),
                'content' => $state['content'] ?? null,
            ],
        );

        $this->refreshAvailableLocales();
        $this->editorStatus = 'draft_saved';
        Notification::make()->title(DocLocales::englishName($this->editingLocale).' translation saved')->success()->send();
    }

    /** A translation-only sidebar value, trimmed and capped to its column; null when blank. */
    private function translationField(string $key, int $limit): ?string
    {
        $value = $this->data[$key] ?? null;
        $value = is_scalar($value) ? trim((string) $value) : '';

        return $value === '' ? null : Str::limit($value, $limit, '');
    }

    public function saveDraft(): void
    {
        if ($this->editingLocale !== 'en') {
            $this->saveTranslation();

            return;
        }

        $this->data['status'] = 'draft';
        $this->save(shouldRedirect: false);
        $this->editorStatus = 'draft_saved';
        Notification::make()->title('Draft saved')->success()->send();
    }

    public function publish(): void
    {
        if ($this->editingLocale !== 'en') {
            $this->saveTranslation();

            return;
        }

        $wasPublished = $this->record->status === 'published';
        $this->data['status'] = 'published';
        $this->data['published_at'] ??= now()->toDateTimeString();
        $this->save(shouldRedirect: false);
        $this->editorStatus = 'published';

        $message = $wasPublished ? 'Page updated' : 'Page published';
        // Drives the self-dismissing toast in the editor view.
        $this->dispatch('doc-published', message: $message);
        Notification::make()->title($message)->success()->send();
    }

    public function deletePage(): void
    {
        $this->record->delete();
        Notification::make()->title('Page deleted')->success()->send();
        $this->redirect(DocPageResource::getUrl('index'));
    }

    protected function mutateFormDataBeforeFill(array $data): array
    {
        // FileUpload expects an array; DB stores a plain string path.
        if (isset($data['featured_image']) && is_string($data['featured_image']) && filled($data['featured_image'])) {
            $data['featured_image'] = [$data['featured_image']];
        }

        return $data;
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        $merged = array_merge($data, $this->sidebarForm->getState());
        $merged['order'] = (int) ($merged['order'] ?? 0);
        unset(
            $merged['_visibility'], $merged['_author'], $merged['_status_display'],
            // Translation-only fields: never written onto the English page.
            $merged['translation_excerpt'], $merged['translation_meta_title'], $merged['translation_meta_description'],
        );

        // FileUpload returns an array; DB expects a plain string path.
        if (is_array($merged['featured_image'] ?? null)) {
            $merged['featured_image'] = (string) (array_values(array_filter($merged['featured_image']))[0] ?? '');
        }

        // Override with library selection if no FileUpload file was chosen.
        if (empty($merged['featured_image']) && $this->libraryImagePath) {
            $merged['featured_image'] = $this->libraryImagePath;
        }

        // Register a freshly-uploaded featured image into the media library.
        if (! empty($merged['featured_image'])) {
            $merged['featured_image'] = $this->ingestToMedia((string) $merged['featured_image']) ?? $merged['featured_image'];
        }

        return $merged;
    }

    protected function getRedirectUrl(): string
    {
        return DocPageResource::getUrl('index');
    }
}
