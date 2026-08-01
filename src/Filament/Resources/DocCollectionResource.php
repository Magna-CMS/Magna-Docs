<?php

declare(strict_types=1);

namespace Magna\Docs\Filament\Resources;

use BladeUI\Heroicons\BladeHeroiconsServiceProvider;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms;
use Filament\Navigation\NavigationItem;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use Magna\Docs\Filament\Resources\DocCollectionResource\Pages;
use Magna\Docs\Models\DocCollection;

class DocCollectionResource extends Resource
{
    protected static ?string $model = DocCollection::class;

    protected static string|\UnitEnum|null $navigationGroup = 'Magna Docs';

    protected static ?string $navigationLabel = 'Collections';

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-rectangle-stack';

    protected static ?int $navigationSort = 1;

    protected static ?string $recordTitleAttribute = 'title';

    // Stage 10: this resource had zero authorization at all — any
    // authenticated panel user could manage doc collections. Reuses the
    // docs.pages.* permissions the plugin already declares in magna.json.
    public static function canViewAny(): bool
    {
        return auth()->user()?->can('docs.pages.view') ?? false;
    }

    public static function canCreate(): bool
    {
        return auth()->user()?->can('docs.pages.manage') ?? false;
    }

    public static function canEdit(Model $record): bool
    {
        return auth()->user()?->can('docs.pages.manage') ?? false;
    }

    public static function canDelete(Model $record): bool
    {
        return auth()->user()?->can('docs.pages.manage') ?? false;
    }

    public static function canDeleteAny(): bool
    {
        return auth()->user()?->can('docs.pages.manage') ?? false;
    }

    /** @return array<NavigationItem> */
    public static function getNavigationItems(): array
    {
        return [
            NavigationItem::make('Collections')
                ->group('Magna Docs')
                ->icon('heroicon-o-rectangle-stack')
                ->sort(1)
                ->url('') // blank → always expand children
                ->childItems([
                    NavigationItem::make('All Collections')
                        ->url(static::getUrl('index'))
                        ->icon('heroicon-m-list-bullet')
                        ->isActiveWhen(fn (): bool => request()->routeIs(static::getRouteBaseName().'.index')),

                    NavigationItem::make('New collection')
                        ->url(static::getUrl('create'))
                        ->icon('heroicon-m-plus-circle')
                        ->isActiveWhen(fn (): bool => request()->routeIs(static::getRouteBaseName().'.create')
                            || request()->routeIs(static::getRouteBaseName().'.edit')),
                ]),
        ];
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->schema([
            Section::make('Collection')
                ->columns(2)
                ->schema([
                    Forms\Components\TextInput::make('title')
                        ->required()
                        ->maxLength(255)
                        ->live(onBlur: true)
                        ->afterStateUpdated(function (?string $state, callable $set, ?string $old): void {
                            if (blank($old) || Str::slug($old ?? '') === '') {
                                $set('slug', Str::slug($state ?? ''));
                            }
                        })
                        ->columnSpan(1),

                    Forms\Components\TextInput::make('slug')
                        ->required()
                        ->unique(ignoreRecord: true)
                        ->helperText('Used in the URL and API.')
                        ->columnSpan(1),

                    Forms\Components\Textarea::make('description')
                        ->rows(3)
                        ->columnSpanFull(),
                ]),

            Section::make('Display')
                ->columns(3)
                ->schema([
                    Forms\Components\TextInput::make('icon')
                        ->default('book-open')
                        ->helperText('Heroicon name (e.g. book-open, code-bracket).')
                        ->datalist(fn () => static::outlineIconNames())
                        ->rule(fn () => function (string $attribute, $value, \Closure $fail) {
                            if (! empty($value) && ! in_array($value, static::outlineIconNames(), true)) {
                                $fail('Unknown Heroicon name. It must match an outline icon, e.g. book-open, code-bracket.');
                            }
                        })
                        ->columnSpan(1),

                    Forms\Components\ColorPicker::make('color')
                        ->default('#6366f1')
                        ->columnSpan(1),

                    Forms\Components\TextInput::make('order')
                        ->numeric()
                        ->default(0)
                        ->helperText('Lower numbers appear first.')
                        ->columnSpan(1),

                    Forms\Components\Toggle::make('is_public')
                        ->label('Publicly visible')
                        ->default(true)
                        ->helperText('Hidden collections are excluded from the frontend.')
                        ->columnSpanFull(),
                ]),
        ]);
    }

    /**
     * Bare (unprefixed) names of every available `heroicon-o-*` outline icon,
     * e.g. "book-open", "code-bracket" — used for the icon field's datalist
     * and existence validation so admins can't save a name that 404s on the frontend.
     *
     * @return array<int, string>
     */
    protected static function outlineIconNames(): array
    {
        return once(function () {
            $providerPath = (new \ReflectionClass(BladeHeroiconsServiceProvider::class))->getFileName();
            $svgPath = dirname($providerPath).'/../resources/svg';

            if (! is_dir($svgPath)) {
                return [];
            }

            return collect(glob($svgPath.'/o-*.svg'))
                ->map(fn (string $file) => Str::of(basename($file, '.svg'))->after('o-')->toString())
                ->sort()
                ->values()
                ->all();
        });
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('title')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('slug')
                    ->badge()
                    ->color('gray'),

                Tables\Columns\TextColumn::make('pages_count')
                    ->label('Pages')
                    ->counts('pages')
                    ->sortable(),

                Tables\Columns\IconColumn::make('is_public')
                    ->label('Public')
                    ->boolean(),

                Tables\Columns\TextColumn::make('order')
                    ->sortable(),
            ])
            ->defaultSort('order')
            ->reorderable('order')
            ->actions([
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->bulkActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListDocCollections::route('/'),
            'create' => Pages\CreateDocCollection::route('/create'),
            'edit' => Pages\EditDocCollection::route('/{record}/edit'),
        ];
    }
}
