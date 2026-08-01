<?php

declare(strict_types=1);

namespace Magna\Docs\Filament\Resources;

use Filament\Actions\Action as TableAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Navigation\NavigationItem;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Magna\Docs\Filament\Resources\DocPageResource\Pages;
use Magna\Docs\Models\DocPage;

class DocPageResource extends Resource
{
    protected static ?string $model = DocPage::class;

    protected static string|\UnitEnum|null $navigationGroup = 'Magna Docs';

    protected static ?string $navigationLabel = 'Pages';

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-document-text';

    protected static ?int $navigationSort = 2;

    protected static ?string $recordTitleAttribute = 'title';

    // Stage 10: same gap as DocCollectionResource — no authorization at all.
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

    /** Stage 11 (S11-04): table() renders 'collection'/'parent' relation columns without this — 1 extra query per row. */
    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->with(['collection', 'parent']);
    }

    /** @return array<NavigationItem> */
    public static function getNavigationItems(): array
    {
        $draftCount = DocPage::where('status', 'draft')->count();

        return [
            NavigationItem::make('Pages')
                ->group('Magna Docs')
                ->icon('heroicon-o-document-text')
                ->sort(2)
                ->url('') // blank → always expand children
                ->childItems([
                    NavigationItem::make('All Pages')
                        ->url(static::getUrl('index'))
                        ->icon('heroicon-m-list-bullet')
                        ->isActiveWhen(fn (): bool => request()->routeIs(static::getRouteBaseName().'.index')),

                    NavigationItem::make('Create page')
                        ->url(static::getUrl('create'))
                        ->icon('heroicon-m-plus-circle')
                        ->isActiveWhen(fn (): bool => request()->routeIs(static::getRouteBaseName().'.create')
                            || request()->routeIs(static::getRouteBaseName().'.edit')),

                    NavigationItem::make('Drafts')
                        ->url(static::getUrl('index').'?tableFilters[status][value]=draft')
                        ->icon('heroicon-m-pencil-square')
                        ->badge($draftCount > 0 ? (string) $draftCount : null, color: 'warning')
                        ->isActiveWhen(fn (): bool => false),
                ]),
        ];
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->schema([]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('title')
                    ->searchable()
                    ->sortable()
                    ->description(fn (DocPage $record): string => $record->collection?->title ?? ''),

                Tables\Columns\TextColumn::make('parent.title')
                    ->label('Parent')
                    ->default('—')
                    ->toggleable(),

                Tables\Columns\TextColumn::make('slug')
                    ->badge()
                    ->color('gray'),

                Tables\Columns\TextColumn::make('status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'published' => 'success',
                        'draft' => 'warning',
                        'archived' => 'danger',
                        default => 'gray',
                    }),

                Tables\Columns\TextColumn::make('order')
                    ->sortable(),

                Tables\Columns\TextColumn::make('updated_at')
                    ->since()
                    ->sortable(),
            ])
            ->defaultSort('order')
            ->reorderable('order')
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->options([
                        'draft' => 'Draft',
                        'published' => 'Published',
                        'archived' => 'Archived',
                    ]),
                Tables\Filters\SelectFilter::make('collection_id')
                    ->label('Collection')
                    ->relationship('collection', 'title'),
            ])
            ->actions([
                TableAction::make('preview')
                    ->icon('heroicon-o-arrow-top-right-on-square')
                    ->url(fn (DocPage $record): string => route('docs.web.show', $record->slug))
                    ->openUrlInNewTab()
                    ->visible(fn (DocPage $record): bool => $record->status === 'published'),
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
            'index' => Pages\ListDocPages::route('/'),
            'create' => Pages\CreateDocPage::route('/create'),
            'edit' => Pages\EditDocPage::route('/{record}/edit'),
        ];
    }
}
