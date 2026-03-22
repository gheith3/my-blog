<?php

namespace App\Filament\Resources\Posts\Tables;

use App\Enums\PostStatus;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ForceDeleteBulkAction;
use Filament\Actions\RestoreBulkAction;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;

class PostsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('title')
                    ->label(__('filament.resources.post.fields.title'))
                    ->searchable()
                    ->sortable()
                    ->weight('font-bold')
                    ->limit(40)
                    ->tooltip(fn ($state): ?string => strlen($state) > 40 ? $state : null),

                TextColumn::make('category.name')
                    ->label(__('filament.resources.post.fields.category'))
                    ->searchable()
                    ->sortable()
                    ->badge()
                    ->color('info'),

                TextColumn::make('status')
                    ->label(__('filament.resources.post.fields.status'))
                    ->badge()
                    ->icon(fn (PostStatus $state): string => $state->getIcon())
                    ->color(fn (PostStatus $state): string => $state->getColor())
                    ->formatStateUsing(fn (PostStatus $state): string => $state->getLabel())
                    ->searchable()
                    ->sortable(),

                TextColumn::make('visitors_count')
                    ->label(__('filament.resources.post.fields.visitors_count'))
                    ->counts('visitors')
                    ->sortable()
                    ->badge()
                    ->color('success')
                    ->toggleable(),

                TextColumn::make('comments_count')
                    ->label(__('filament.resources.post.fields.comments_count'))
                    ->counts('comments')
                    ->sortable()
                    ->badge()
                    ->color('warning')
                    ->toggleable(),

                TextColumn::make('created_at')
                    ->label(__('filament.resources.post.fields.created_at'))
                    ->dateTime('M j, Y')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('updated_at')
                    ->label(__('filament.resources.post.fields.updated_at'))
                    ->dateTime('M j, Y')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('deleted_at')
                    ->label(__('filament.resources.post.fields.deleted_at'))
                    ->dateTime('M j, Y')
                    ->sortable()
                    ->placeholder('-')
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->options(PostStatus::class)
                    ->label(__('filament.filters.status'))
                    ->native(false),

                SelectFilter::make('category')
                    ->relationship('category', 'name')
                    ->label(__('filament.filters.category'))
                    ->searchable()
                    ->preload(),

                SelectFilter::make('user')
                    ->relationship('user', 'name')
                    ->label(__('filament.filters.author'))
                    ->searchable()
                    ->preload(),

                TrashedFilter::make(),
            ])
            ->recordActions([
                ViewAction::make(),
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                    ForceDeleteBulkAction::make(),
                    RestoreBulkAction::make(),
                ]),
            ])
            ->defaultSort('created_at', 'desc');
    }
}
