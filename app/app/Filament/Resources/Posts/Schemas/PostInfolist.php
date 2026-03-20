<?php

namespace App\Filament\Resources\Posts\Schemas;

use App\Enums\PostStatus;
use App\Models\Post;
use Filament\Infolists\Components\IconEntry;
use Filament\Infolists\Components\ImageEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Enums\FontWeight;
use Filament\Support\Icons\Heroicon;

class PostInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(1)
            ->components([
                Section::make(__('filament.resources.post.sections.post_info'))
                    ->schema([
                        Grid::make(3)
                            ->schema([
                                TextEntry::make('title')
                                    ->label(__('filament.resources.post.fields.title'))
                                    ->weight(FontWeight::Bold)
                                    ->size('lg'),

                                TextEntry::make('slug')
                                    ->label(__('filament.resources.post.fields.slug'))
                                    ->icon(Heroicon::OutlinedLink)
                                    ->color('gray'),
                            ]),

                        ImageEntry::make('thumbnail')
                            ->disk('public')
                            ->label(__('filament.resources.post.fields.thumbnail')),

                        TextEntry::make('content')
                            ->label(__('filament.resources.post.fields.content'))
                            ->markdown()
                            ->columnSpanFull(),
                    ]),

                Section::make(__('filament.resources.post.sections.metadata'))
                    ->columns(3)
                    ->schema([
                        TextEntry::make('user.name')
                            ->label(__('filament.resources.post.fields.author'))
                            ->icon(Heroicon::OutlinedUser),

                        TextEntry::make('category.name')
                            ->label(__('filament.resources.post.fields.category'))
                            ->icon(Heroicon::OutlinedFolder),

                        TextEntry::make('tags.name')
                            ->label(__('filament.resources.post.fields.tags'))
                            ->badge()
                            ->color('primary')
                            ->listWithLineBreaks(),
                    ]),

                Section::make(__('filament.resources.post.sections.analytics'))
                    ->columns(2)
                    ->schema([
                        TextEntry::make('visitor_count')
                            ->label(__('filament.resources.post.fields.total_visitors'))
                            ->icon(Heroicon::OutlinedEye)
                            ->state(fn (Post $record): int => $record->visitors()->count())
                            ->badge()
                            ->color('success'),

                        TextEntry::make('unique_visitor_count')
                            ->label(__('filament.resources.post.fields.unique_visitors'))
                            ->icon(Heroicon::OutlinedUsers)
                            ->state(fn (Post $record): int => $record->visitors()->distinct('session_id')->count('session_id'))
                            ->badge()
                            ->color('info'),
                    ]),

                Section::make(__('filament.resources.post.sections.publication_status'))
                    ->columns(3)
                    ->schema([
                        TextEntry::make('status')
                            ->label(__('filament.resources.post.fields.status'))
                            ->badge()
                            ->icon(fn (PostStatus $state): string => $state->getIcon())
                            ->color(fn (PostStatus $state): string => $state->getColor()),

                        TextEntry::make('published_at')
                            ->label(__('filament.resources.post.fields.published_at'))
                            ->dateTime()
                            ->placeholder(__('filament.messages.not_published'))
                            ->icon(Heroicon::OutlinedCalendar),

                        IconEntry::make('isPublished')
                            ->label(__('filament.resources.post.fields.is_live'))
                            ->boolean()
                            ->state(fn (Post $record): bool => $record->isPublished()),
                    ]),

                Section::make(__('filament.resources.post.sections.timestamps'))
                    ->columns(3)
                    ->schema([
                        TextEntry::make('created_at')
                            ->label(__('filament.resources.post.fields.created_at'))
                            ->dateTime()
                            ->icon(Heroicon::OutlinedClock),

                        TextEntry::make('updated_at')
                            ->label(__('filament.resources.post.fields.updated_at'))
                            ->dateTime()
                            ->icon(Heroicon::OutlinedArrowPath),

                        TextEntry::make('deleted_at')
                            ->label(__('filament.resources.post.fields.deleted_at'))
                            ->dateTime()
                            ->placeholder(__('filament.messages.not_deleted'))
                            ->visible(fn (Post $record): bool => $record->trashed()),
                    ])
                    ->collapsible()
                    ->collapsed(),
            ]);
    }
}
