<?php

namespace App\Filament\Resources\ProfileChangeRequests\Pages;

use App\Filament\Resources\ProfileChangeRequests\ProfileChangeRequestResource;
use Filament\Resources\Pages\CreateRecord;

class CreateProfileChangeRequest extends CreateRecord
{
    protected static string $resource = ProfileChangeRequestResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        // A request always starts as a Draft owned by whoever raised it; the
        // status only moves through ProfileChangeWorkflow afterwards.
        $data['submitted_by'] = auth()->id();

        // An employee may only file against their own record, whatever the
        // form payload says.
        if (! auth()->user()->isHr()) {
            $data['personnel_id'] = auth()->user()->personnel()->value('id');
        }

        return $data;
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('view', ['record' => $this->getRecord()]);
    }
}
