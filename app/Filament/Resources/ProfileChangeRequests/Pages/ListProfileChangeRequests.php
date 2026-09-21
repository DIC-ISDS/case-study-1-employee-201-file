<?php

namespace App\Filament\Resources\ProfileChangeRequests\Pages;

use App\Filament\Resources\ProfileChangeRequests\ProfileChangeRequestResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListProfileChangeRequests extends ListRecords
{
    protected static string $resource = ProfileChangeRequestResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
