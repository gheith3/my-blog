<?php

namespace App\Filament\Resources\Posts\Pages;

use App\Filament\Resources\Posts\PostResource;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

class ViewPost extends ViewRecord
{
    protected static string $resource = PostResource::class;

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make(),
            Action::make('view_on_site')
                ->label(__('filament.resources.post.actions.view_on_site'))
                ->url(route('posts.show', $this->record->slug))
                ->openUrlInNewTab(),
        ];
    }
}
