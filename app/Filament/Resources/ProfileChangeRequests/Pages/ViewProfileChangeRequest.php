<?php

namespace App\Filament\Resources\ProfileChangeRequests\Pages;

use App\Filament\Resources\ProfileChangeRequests\ProfileChangeRequestResource;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

class ViewProfileChangeRequest extends ViewRecord
{
    protected static string $resource = ProfileChangeRequestResource::class;

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make(),
        ];
    }
}
