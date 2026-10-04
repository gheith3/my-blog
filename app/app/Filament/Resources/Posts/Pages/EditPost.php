<?php

namespace App\Filament\Resources\Posts\Pages;

use App\Enums\RevisionSource;
use App\Enums\RevisionState;
use App\Filament\Resources\Posts\PostResource;
use App\Models\PostRevision;
use App\Services\PostService;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Actions\ForceDeleteAction;
use Filament\Actions\RestoreAction;
use Filament\Actions\ViewAction;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Filament\Schemas\Components\View;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;

class EditPost extends EditRecord
{
    protected static string $resource = PostResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('view_on_site')
                ->label(__('filament.resources.post.actions.view_on_site'))
                ->url(route('posts.show', $this->record->slug))
                ->openUrlInNewTab(),
            ViewAction::make(),
            DeleteAction::make(),
            ForceDeleteAction::make(),
            RestoreAction::make(),
        ];
    }

    public function content(Schema $schema): Schema
    {
        $components = [
            View::make('filament.posts.pending-revision-banner')
                ->viewData(fn (): array => [
                    'post' => $this->getRecord(),
                    'pendingRevisions' => $this->getPendingRevisions(),
                ])
                ->visible(fn (): bool => $this->getPendingRevisions()->isNotEmpty()),
        ];

        if ($this->hasCombinedRelationManagerTabsWithContent()) {
            $components[] = $this->getRelationManagersContentComponent();
        } else {
            $components[] = $this->getFormContentComponent();
            $components[] = $this->getRelationManagersContentComponent();
        }

        return $schema->components($components);
    }

    public function approveRevisionAction(): Action
    {
        return Action::make('approveRevision')
            ->label(__('filament.resources.post.revisions.actions.approve'))
            ->icon(Heroicon::OutlinedCheck)
            ->color('success')
            ->requiresConfirmation()
            ->modalDescription(__('filament.resources.post.revisions.actions.approve_confirm'))
            ->action(function (array $arguments): void {
                $revision = $this->findPendingRevision($arguments['revision'] ?? null);

                if ($revision === null) {
                    return;
                }

                $revision = app(PostService::class)->approveRevision($revision, auth()->user());

                $this->record->refresh();
                $this->refreshFormData(['title', 'content', 'excerpt', 'slug', 'status']);

                if ($revision->state === RevisionState::Conflict) {
                    Notification::make()
                        ->warning()
                        ->title(__('filament.resources.post.revisions.notifications.conflict'))
                        ->send();

                    return;
                }

                Notification::make()
                    ->success()
                    ->title(__('filament.resources.post.revisions.notifications.approved', ['version' => $revision->version]))
                    ->send();
            });
    }

    public function rejectRevisionAction(): Action
    {
        return Action::make('rejectRevision')
            ->label(__('filament.resources.post.revisions.actions.reject'))
            ->icon(Heroicon::OutlinedXMark)
            ->color('danger')
            ->requiresConfirmation()
            ->modalDescription(__('filament.resources.post.revisions.actions.reject_confirm'))
            ->action(function (array $arguments): void {
                $revision = $this->findPendingRevision($arguments['revision'] ?? null);

                if ($revision === null) {
                    return;
                }

                app(PostService::class)->rejectRevision($revision, auth()->user());

                Notification::make()
                    ->success()
                    ->title(__('filament.resources.post.revisions.notifications.rejected'))
                    ->send();
            });
    }

    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        return app(PostService::class)->updatePost(
            $record,
            $data,
            RevisionSource::Dashboard,
            'dashboard',
            ifVersion: $record->version,
        );
    }

    /**
     * Pending revisions staged on this post, oldest first.
     *
     * @return Collection<int, PostRevision>
     */
    protected function getPendingRevisions(): Collection
    {
        return $this->getRecord()->revisions()
            ->where('state', RevisionState::Pending)
            ->oldest('created_at')
            ->get();
    }

    private function findPendingRevision(mixed $id): ?PostRevision
    {
        $revision = $this->getRecord()->revisions()
            ->whereKey($id)
            ->where('state', RevisionState::Pending)
            ->first();

        if ($revision === null) {
            Notification::make()
                ->warning()
                ->title(__('filament.resources.post.revisions.notifications.not_pending'))
                ->send();
        }

        return $revision;
    }
}
