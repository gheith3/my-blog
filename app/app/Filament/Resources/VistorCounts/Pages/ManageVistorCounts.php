<?php

namespace App\Filament\Resources\VistorCounts\Pages;

use App\Filament\Resources\VistorCounts\VistorCountResource;
use Filament\Resources\Pages\ManageRecords;

class ManageVistorCounts extends ManageRecords
{
    protected static string $resource = VistorCountResource::class;

    protected function getHeaderActions(): array
    {
        return [];
    }
}
