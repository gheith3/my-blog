<?php

namespace App\Filament\Resources\Posts\Schemas;

use App\Enums\PostStatus;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class PostForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(1)
            ->components([
                Section::make(__('filament.resources.post.sections.content'))
                    ->description(__('filament.resources.post.sections.content_description'))
                    ->schema([
                        Grid::make(3)
                            ->schema([
                                TextInput::make('title')
                                    ->required()
                                    ->maxLength(255)
                                    ->columnSpan(2)
                                    ->label(__('filament.resources.post.fields.title'))
                                    ->placeholder(__('filament.resources.post.fields.title'))
                                    ->live(onBlur: true),

                                TextInput::make('slug')
                                    ->disabled()
                                    ->label(__('filament.resources.post.fields.slug'))
                                    ->placeholder('url-friendly-slug'),
                            ]),

                        FileUpload::make('thumbnail')
                            ->label(__('filament.resources.post.fields.thumbnail'))
                            ->directory('thumbnails')
                            ->disk('public')
                            ->image()
                            ->maxSize(1024 * 4)
                            ->columnSpanFull(),

                        RichEditor::make('content')
                            ->required()
                            ->label(__('filament.resources.post.fields.content'))
                            ->placeholder(__('filament.resources.post.fields.content'))
                            ->fileAttachmentsDisk('public')
                            ->fileAttachmentsDirectory('uploads')
                            ->columnSpanFull(),
                    ]),

                Section::make(__('filament.resources.post.sections.organization'))
                    ->description(__('filament.resources.post.sections.organization_description'))
                    ->schema([
                        Select::make('category_id')
                            ->relationship('category', 'name')
                            ->required()
                            ->searchable()
                            ->preload()
                            ->label(__('filament.resources.post.fields.category'))
                            ->placeholder(__('filament.resources.post.fields.category')),

                        Select::make('tags')
                            ->relationship('tags', 'name')
                            ->multiple()
                            ->searchable()
                            ->preload()
                            ->label(__('filament.resources.post.fields.tags'))
                            ->placeholder(__('filament.resources.post.fields.tags')),
                    ])
                    ->columns(2),

                Section::make(__('filament.resources.post.sections.publication'))
                    ->description(__('filament.resources.post.sections.publication_description'))
                    ->schema([
                        Select::make('user_id')
                            ->relationship('user', 'name')
                            ->required()
                            ->searchable()
                            ->preload()
                            ->label(__('filament.resources.post.fields.author')),

                        Select::make('status')
                            ->options(PostStatus::class)
                            ->default(PostStatus::Draft)
                            ->required()
                            ->label(__('filament.filters.status'))
                            ->native(false),

                        DateTimePicker::make('published_at')
                            ->label(__('filament.resources.post.fields.published_at'))
                            ->placeholder('Schedule publication')
                            ->native(false)
                            ->timezone('UTC'),
                    ])
                    ->columns(3),
            ]);
    }
}
