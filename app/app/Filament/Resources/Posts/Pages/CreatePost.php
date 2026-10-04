<?php

namespace App\Filament\Resources\Posts\Pages;

use App\Enums\RevisionSource;
use App\Filament\Resources\Posts\PostResource;
use App\Services\PostService;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;

class CreatePost extends CreateRecord
{
    protected static string $resource = PostResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['user_id'] = auth()->id();

        return $data;
    }

    protected function handleRecordCreation(array $data): Model
    {
        return app(PostService::class)->createPost($data, RevisionSource::Dashboard, 'dashboard');
    }
}
