<?php

namespace App\Filament\Resources\Posts\Schemas;

use App\Enums\PostStatus;
use App\Models\Post;
use Filament\Infolists\Components\IconEntry;
use Filament\Infolists\Components\TextEntry;
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
                Section::make('Post Information')
                    ->schema([
                        TextEntry::make('title')
                            ->weight(FontWeight::Bold)
                            ->size('lg'),

                        TextEntry::make('slug')
                            ->icon(Heroicon::OutlinedLink)
                            ->color('gray'),

                        TextEntry::make('content')
                            ->markdown()
                            ->columnSpanFull(),
                    ]),

                Section::make('Metadata')
                    ->columns(3)
                    ->schema([
                        TextEntry::make('user.name')
                            ->label('Author')
                            ->icon(Heroicon::OutlinedUser),

                        TextEntry::make('category.name')
                            ->label('Category')
                            ->icon(Heroicon::OutlinedFolder),

                        TextEntry::make('tags.name')
                            ->label('Tags')
                            ->badge()
                            ->color('primary')
                            ->listWithLineBreaks(),
                    ]),

                Section::make('Analytics')
                    ->columns(2)
                    ->schema([
                        TextEntry::make('visitor_count')
                            ->label('Total Visitors')
                            ->icon(Heroicon::OutlinedEye)
                            ->state(fn (Post $record): int => $record->visitors()->count())
                            ->badge()
                            ->color('success'),

                        TextEntry::make('unique_visitor_count')
                            ->label('Unique Visitors')
                            ->icon(Heroicon::OutlinedUsers)
                            ->state(fn (Post $record): int => $record->visitors()->distinct('session_id')->count('session_id'))
                            ->badge()
                            ->color('info'),
                    ]),

                Section::make('Publication Status')
                    ->columns(3)
                    ->schema([
                        TextEntry::make('status')
                            ->badge()
                            ->icon(fn (PostStatus $state): string => $state->getIcon())
                            ->color(fn (PostStatus $state): string => $state->getColor()),

                        TextEntry::make('published_at')
                            ->label('Published At')
                            ->dateTime()
                            ->placeholder('Not published yet')
                            ->icon(Heroicon::OutlinedCalendar),

                        IconEntry::make('isPublished')
                            ->label('Is Live')
                            ->boolean()
                            ->state(fn (Post $record): bool => $record->isPublished()),
                    ]),

                Section::make('Timestamps')
                    ->columns(3)
                    ->schema([
                        TextEntry::make('created_at')
                            ->dateTime()
                            ->icon(Heroicon::OutlinedClock),

                        TextEntry::make('updated_at')
                            ->dateTime()
                            ->icon(Heroicon::OutlinedArrowPath),

                        TextEntry::make('deleted_at')
                            ->dateTime()
                            ->placeholder('Not deleted')
                            ->visible(fn (Post $record): bool => $record->trashed()),
                    ])
                    ->collapsible()
                    ->collapsed(),
            ]);
    }
}
