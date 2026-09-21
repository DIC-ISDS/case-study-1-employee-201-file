<?php

namespace App\Filament\Resources\Personnel\Pages;

use App\Filament\Resources\Personnel\PersonnelResource;
use App\Models\Personnel;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListPersonnels extends ListRecords
{
    protected static string $resource = PersonnelResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('printMasterList')
                ->label('Print master list')
                ->icon('heroicon-o-printer')
                ->color('gray')
                ->url(route('reports.personnel-master-list'), shouldOpenInNewTab: true)
                ->visible(fn () => auth()->user()?->can('viewAny', Personnel::class)
                    && auth()->user()?->isHr()),
            CreateAction::make(),
        ];
    }
}
