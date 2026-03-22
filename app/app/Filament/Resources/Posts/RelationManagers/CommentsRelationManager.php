<?php

namespace App\Filament\Resources\Posts\RelationManagers;

use App\Models\Comment;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\RestoreAction;
use Filament\Actions\RestoreBulkAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Infolists\Components\IconEntry;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Enums\FontWeight;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

class CommentsRelationManager extends RelationManager
{
    protected static string $relationship = 'comments';

    protected static ?string $title = 'Comments';

    public static function getTitle(Model $ownerRecord, string $pageClass): string
    {
        return __('filament.resources.comment.plural');
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->columns(1)
            ->components([
                Section::make(__('filament.resources.comment.sections.comment_info'))
                    ->schema([
                        TextInput::make('name')
                            ->label(__('filament.resources.comment.fields.name'))
                            ->required()
                            ->maxLength(100),

                        Textarea::make('content')
                            ->label(__('filament.resources.comment.fields.content'))
                            ->required()
                            ->maxLength(5000)
                            ->rows(4),

                        Toggle::make('is_approved')
                            ->label(__('filament.resources.comment.fields.is_approved'))
                            ->default(true),
                    ]),

                Section::make(__('filament.resources.comment.sections.technical_info'))
                    ->description(__('filament.resources.comment.sections.technical_info_description'))
                    ->collapsible()
                    ->collapsed()
                    ->schema([
                        TextInput::make('ip_address')
                            ->label(__('filament.resources.comment.fields.ip_address'))
                            ->disabled(),

                        TextInput::make('user_agent')
                            ->label(__('filament.resources.comment.fields.user_agent'))
                            ->disabled()
                            ->columnSpanFull(),
                    ])
                    ->columns(2),
            ]);
    }

    public function infolist(Schema $schema): Schema
    {
        return $schema
            ->columns(1)
            ->components([
                Section::make(__('filament.resources.comment.sections.comment_info'))
                    ->columns(3)
                    ->schema([
                        TextEntry::make('name')
                            ->label(__('filament.resources.comment.fields.name'))
                            ->weight(FontWeight::Bold)
                            ->size('lg'),

                        TextEntry::make('content')
                            ->label(__('filament.resources.comment.fields.content'))
                            ->columnSpanFull(),

                        IconEntry::make('is_approved')
                            ->label(__('filament.resources.comment.fields.is_approved'))
                            ->boolean(),

                        TextEntry::make('parent.name')
                            ->label(__('filament.resources.comment.fields.reply_to'))
                            ->placeholder('-'),

                        TextEntry::make('created_at')
                            ->label(__('filament.resources.comment.fields.created_at'))
                            ->dateTime(),
                    ]),

                Section::make(__('filament.resources.comment.sections.technical_info'))
                    ->description(__('filament.resources.comment.sections.technical_info_description'))
                    ->collapsible()
                    ->collapsed()
                    ->schema([
                        TextEntry::make('ip_address')
                            ->label(__('filament.resources.comment.fields.ip_address')),

                        TextEntry::make('user_agent')
                            ->label(__('filament.resources.comment.fields.user_agent'))
                            ->columnSpanFull(),
                    ])
                    ->columns(2),

                Section::make(__('filament.resources.comment.sections.replies'))
                    ->description(__('filament.resources.comment.sections.replies_description'))
                    ->visible(fn (Comment $record): bool => $record->replies()->count() > 0)
                    ->schema([
                        RepeatableEntry::make('replies')
                            ->hiddenLabel()
                            ->schema([
                                TextEntry::make('name')
                                    ->label(__('filament.resources.comment.fields.name'))
                                    ->weight(FontWeight::Medium),

                                TextEntry::make('content')
                                    ->label(__('filament.resources.comment.fields.content')),

                                IconEntry::make('is_approved')
                                    ->label(__('filament.resources.comment.fields.is_approved'))
                                    ->boolean(),

                                TextEntry::make('created_at')
                                    ->label(__('filament.resources.comment.fields.created_at'))
                                    ->dateTime(),
                            ])
                            ->columns(1),
                    ]),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('content')
            ->columns([
                TextColumn::make('name')
                    ->label(__('filament.resources.comment.fields.name'))
                    ->searchable()
                    ->sortable(),

                TextColumn::make('content')
                    ->label(__('filament.resources.comment.fields.content'))
                    ->limit(100)
                    ->tooltip(fn (Comment $record): string => $record->content)
                    ->searchable(),

                IconColumn::make('is_approved')
                    ->label(__('filament.resources.comment.fields.is_approved'))
                    ->boolean()
                    ->sortable(),

                TextColumn::make('parent.name')
                    ->label(__('filament.resources.comment.fields.reply_to'))
                    ->placeholder('-')
                    ->sortable(),

                TextColumn::make('replies_count')
                    ->label(__('filament.resources.comment.fields.replies_count'))
                    ->counts('replies')
                    ->sortable(),

                TextColumn::make('ip_address')
                    ->label(__('filament.resources.comment.fields.ip_address'))
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('created_at')
                    ->label(__('filament.resources.comment.fields.created_at'))
                    ->dateTime()
                    ->sortable()
                    ->toggleable(),
            ])
            ->filters([
                TrashedFilter::make(),
            ])
            ->recordActions([
                ViewAction::make(),
                EditAction::make(),
                Action::make('reply')
                    ->label(__('filament.actions.reply'))
                    ->icon('heroicon-o-chat-bubble-left-ellipsis')
                    ->color('success')
                    ->modalHeading(__('filament.resources.comment.actions.reply_heading'))
                    ->modalSubmitActionLabel(__('filament.actions.submit_reply'))
                    ->form([
                        TextInput::make('name')
                            ->label(__('filament.resources.comment.fields.name'))
                            ->required()
                            ->maxLength(100),

                        Textarea::make('content')
                            ->label(__('filament.resources.comment.fields.content'))
                            ->required()
                            ->maxLength(5000)
                            ->rows(4),

                        Toggle::make('is_approved')
                            ->label(__('filament.resources.comment.fields.is_approved'))
                            ->default(true),
                    ])
                    ->action(function (Comment $record, array $data): void {
                        $record->replies()->create([
                            'name' => $data['name'],
                            'content' => $data['content'],
                            'is_approved' => $data['is_approved'],
                            'commentable_type' => $record->commentable_type,
                            'commentable_id' => $record->commentable_id,
                            'ip_address' => request()->ip(),
                            'user_agent' => request()->userAgent(),
                        ]);
                    }),
                DeleteAction::make(),
                RestoreAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                    RestoreBulkAction::make(),
                ]),
            ])
            ->defaultSort('created_at', 'desc');
    }
}
