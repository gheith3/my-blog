<?php

namespace App\Filament\Resources\Posts\RelationManagers;

use App\Enums\RevisionState;
use App\Models\PostRevision;
use App\Services\PostService;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Support\Enums\Width;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Model;

class RevisionsRelationManager extends RelationManager
{
    protected static string $relationship = 'revisions';

    public static function getTitle(Model $ownerRecord, string $pageClass): string
    {
        return __('filament.resources.post.revisions.title');
    }

    public function table(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('state')
                    ->label(__('filament.resources.post.revisions.fields.state'))
                    ->badge()
                    ->sortable(),

                TextColumn::make('source')
                    ->label(__('filament.resources.post.revisions.fields.source'))
                    ->badge(),

                TextColumn::make('client_name')
                    ->label(__('filament.resources.post.revisions.fields.client_name'))
                    ->placeholder('-'),

                TextColumn::make('note')
                    ->label(__('filament.resources.post.revisions.fields.note'))
                    ->limit(40)
                    ->placeholder('-'),

                IconColumn::make('unguarded')
                    ->label(__('filament.resources.post.revisions.fields.unguarded'))
                    ->boolean(),

                TextColumn::make('version')
                    ->label(__('filament.resources.post.revisions.fields.version'))
                    ->placeholder('-')
                    ->sortable(),

                TextColumn::make('base_version')
                    ->label(__('filament.resources.post.revisions.fields.base_version'))
                    ->placeholder('-')
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('decided_at')
                    ->label(__('filament.resources.post.revisions.fields.decided_at'))
                    ->dateTime()
                    ->placeholder('-')
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('created_at')
                    ->label(__('filament.resources.post.revisions.fields.created_at'))
                    ->dateTime()
                    ->sortable(),
            ])
            ->recordActions([
                Action::make('viewDetails')
                    ->label(__('filament.resources.post.revisions.actions.view_details'))
                    ->icon(Heroicon::OutlinedEye)
                    ->color('gray')
                    ->slideOver()
                    ->modalWidth(Width::FiveExtraLarge)
                    ->modalHeading(fn (PostRevision $record): string => __('filament.resources.post.revisions.details.heading', ['id' => $record->id]))
                    ->modalSubmitAction(false)
                    ->modalCancelActionLabel(__('filament.resources.post.revisions.details.close'))
                    ->modalContent(fn (PostRevision $record): View => view('filament.posts.revision-details', [
                        'revision' => $record,
                        'post' => $this->getOwnerRecord(),
                    ])),

                Action::make('approve')
                    ->label(__('filament.resources.post.revisions.actions.approve'))
                    ->icon(Heroicon::OutlinedCheck)
                    ->color('success')
                    ->requiresConfirmation()
                    ->modalDescription(__('filament.resources.post.revisions.actions.approve_confirm'))
                    ->visible(fn (PostRevision $record): bool => $record->isPending())
                    ->action(function (PostRevision $record): void {
                        $record = app(PostService::class)->approveRevision($record, auth()->user());

                        if ($record->state === RevisionState::Conflict) {
                            Notification::make()
                                ->warning()
                                ->title(__('filament.resources.post.revisions.notifications.conflict'))
                                ->send();

                            return;
                        }

                        Notification::make()
                            ->success()
                            ->title(__('filament.resources.post.revisions.notifications.approved', ['version' => $record->version]))
                            ->send();
                    }),

                Action::make('reject')
                    ->label(__('filament.resources.post.revisions.actions.reject'))
                    ->icon(Heroicon::OutlinedXMark)
                    ->color('danger')
                    ->requiresConfirmation()
                    ->modalDescription(__('filament.resources.post.revisions.actions.reject_confirm'))
                    ->visible(fn (PostRevision $record): bool => $record->isPending())
                    ->action(function (PostRevision $record): void {
                        app(PostService::class)->rejectRevision($record, auth()->user());

                        Notification::make()
                            ->success()
                            ->title(__('filament.resources.post.revisions.notifications.rejected'))
                            ->send();
                    }),

                Action::make('restore')
                    ->label(__('filament.resources.post.revisions.actions.restore'))
                    ->icon(Heroicon::OutlinedArrowUturnLeft)
                    ->color('gray')
                    ->requiresConfirmation()
                    ->modalDescription(__('filament.resources.post.revisions.actions.restore_confirm'))
                    ->visible(fn (PostRevision $record): bool => ! $record->isPending())
                    ->action(function (PostRevision $record): void {
                        $post = app(PostService::class)->restoreRevision(
                            $this->getOwnerRecord(),
                            $record,
                            ifVersion: $this->getOwnerRecord()->version,
                        );

                        Notification::make()
                            ->success()
                            ->title(__('filament.resources.post.revisions.notifications.restored', ['version' => $post->version]))
                            ->send();
                    }),
            ]);
    }
}
