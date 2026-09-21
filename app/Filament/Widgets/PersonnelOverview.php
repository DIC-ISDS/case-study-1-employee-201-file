<?php

namespace App\Filament\Widgets;

use App\Enums\RequestStatus;
use App\Models\Personnel;
use App\Models\ProfileChangeRequest;
use Filament\Facades\Filament;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class PersonnelOverview extends StatsOverviewWidget
{
    protected ?string $heading = 'At a glance';

    protected static ?int $sort = 1;

    public static function canView(): bool
    {
        return Filament::auth()->user()?->isHr() ?? false;
    }

    protected function getStats(): array
    {
        $pending = ProfileChangeRequest::pending()->count();
        $awaitingVerification = ProfileChangeRequest::pending()->whereNull('reviewed_at')->count();
        $returned = ProfileChangeRequest::returned()->count();
        $unassigned = Personnel::active()->whereDoesntHave('activePrimaryAppointment')->count();

        return [
            Stat::make('Active personnel', Personnel::active()->count())
                ->description($unassigned > 0 ? "{$unassigned} without an active appointment" : 'All have an active appointment')
                ->descriptionIcon($unassigned > 0 ? 'heroicon-m-exclamation-triangle' : 'heroicon-m-check-circle')
                ->color($unassigned > 0 ? 'warning' : 'success'),

            Stat::make('Pending profile changes', $pending)
                ->description("{$awaitingVerification} awaiting HR verification")
                ->descriptionIcon('heroicon-m-clock')
                ->color($pending > 0 ? 'warning' : 'gray'),

            Stat::make('Returned requests', $returned)
                ->description('Sent back to the employee')
                ->descriptionIcon('heroicon-m-arrow-uturn-left')
                ->color($returned > 0 ? 'info' : 'gray'),

            Stat::make('Approved this month', ProfileChangeRequest::where('status', RequestStatus::Approved)
                ->where('decided_at', '>=', now()->startOfMonth())
                ->count())
                ->description('Applied to personnel records')
                ->descriptionIcon('heroicon-m-check-circle')
                ->color('success'),
        ];
    }
}
