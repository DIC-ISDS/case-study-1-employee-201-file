<?php

namespace App\Filament\Widgets;

use App\Enums\RequestStatus;
use App\Models\ProfileChangeRequest;
use Filament\Facades\Filament;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

/** The employee's view of the dashboard: only their own requests. */
class MyRequestsOverview extends StatsOverviewWidget
{
    protected ?string $heading = 'My profile change requests';

    protected static ?int $sort = 1;

    public static function canView(): bool
    {
        $user = Filament::auth()->user();

        return $user !== null && ! $user->isHr();
    }

    protected function getStats(): array
    {
        $user = Filament::auth()->user();

        $mine = fn () => ProfileChangeRequest::query()
            ->where(fn ($query) => $query
                ->where('submitted_by', $user->id)
                ->orWhereHas('personnel', fn ($query) => $query->where('user_id', $user->id)));

        $returned = $mine()->where('status', RequestStatus::Returned)->count();

        return [
            Stat::make('Drafts', $mine()->where('status', RequestStatus::Draft)->count())
                ->description('Not yet submitted')
                ->descriptionIcon('heroicon-m-pencil-square')
                ->color('gray'),

            Stat::make('Awaiting HR', $mine()->where('status', RequestStatus::Pending)->count())
                ->description('Under review')
                ->descriptionIcon('heroicon-m-clock')
                ->color('warning'),

            Stat::make('Returned to me', $returned)
                ->description($returned > 0 ? 'Needs your correction' : 'Nothing to correct')
                ->descriptionIcon('heroicon-m-arrow-uturn-left')
                ->color($returned > 0 ? 'danger' : 'gray'),

            Stat::make('Approved', $mine()->where('status', RequestStatus::Approved)->count())
                ->description('Applied to your record')
                ->descriptionIcon('heroicon-m-check-circle')
                ->color('success'),
        ];
    }
}
