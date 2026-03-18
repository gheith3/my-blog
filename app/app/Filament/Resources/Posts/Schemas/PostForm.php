<?php

namespace App\Filament\Resources\Posts\Schemas;

use App\Enums\PostStatus;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class PostForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
        ->columns(1)
            ->components([
                Section::make('Post Content')
                    ->description('The main content of your post')
                    ->schema([
                        TextInput::make('title')
                            ->required()
                            ->maxLength(255)
                            ->placeholder('Enter post title')
                            ->live(onBlur: true),

                        TextInput::make('slug')
                            ->required()
                            ->maxLength(255)
                            ->unique(ignoreRecord: true)
                            ->placeholder('url-friendly-slug'),

                        RichEditor::make('content')
                            ->required()
                            ->placeholder('Write your post content here...')
                            ->fileAttachmentsDisk('public')
                            ->fileAttachmentsDirectory('uploads')
                            ->columnSpanFull(),
                    ]),

                Section::make('Organization')
                    ->description('Categorize and tag your post')
                    ->schema([
                        Select::make('category_id')
                            ->relationship('category', 'name')
                            ->required()
                            ->searchable()
                            ->preload()
                            ->placeholder('Select a category'),

                        Select::make('tags')
                            ->relationship('tags', 'name')
                            ->multiple()
                            ->searchable()
                            ->preload()
                            ->placeholder('Select tags'),
                    ])
                    ->columns(2),

                Section::make('Publication')
                    ->description('Control when and how your post is published')
                    ->schema([
                        Select::make('user_id')
                            ->relationship('user', 'name')
                            ->required()
                            ->searchable()
                            ->preload()
                            ->label('Author'),

                        Select::make('status')
                            ->options(PostStatus::class)
                            ->default(PostStatus::Draft)
                            ->required()
                            ->native(false),

                        DateTimePicker::make('published_at')
                            ->label('Publish Date')
                            ->placeholder('Schedule publication')
                            ->native(false)
                            ->timezone('UTC'),
                    ])
                    ->columns(3),
            ]);
    }
}
