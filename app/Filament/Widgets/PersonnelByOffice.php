<?php

namespace App\Filament\Widgets;

use App\Models\Office;
use Filament\Facades\Filament;
use Filament\Widgets\ChartWidget;
use Illuminate\Contracts\Support\Htmlable;

class PersonnelByOffice extends ChartWidget
{
    protected static ?int $sort = 2;

    protected ?string $maxHeight = '280px';

    public static function canView(): bool
    {
        return Filament::auth()->user()?->isHr() ?? false;
    }

    public function getHeading(): string|Htmlable|null
    {
        return 'Personnel by office';
    }

    public function getDescription(): string|Htmlable|null
    {
        return 'Counted from each employee’s active primary appointment.';
    }

    protected function getType(): string
    {
        return 'bar';
    }

    protected function getData(): array
    {
        $offices = Office::query()
            ->withCount([
                'appointments as personnel_count' => fn ($query) => $query->activePrimary(),
            ])
            ->orderByDesc('personnel_count')
            ->get();

        return [
            'datasets' => [
                [
                    'label' => 'Personnel',
                    'data' => $offices->pluck('personnel_count')->all(),
                    'backgroundColor' => '#3b82f6',
                    'borderRadius' => 4,
                ],
            ],
            'labels' => $offices->pluck('code')->all(),
        ];
    }

    protected function getOptions(): array
    {
        return [
            'plugins' => ['legend' => ['display' => false]],
            'scales' => [
                'y' => [
                    'beginAtZero' => true,
                    'ticks' => ['precision' => 0],
                ],
            ],
        ];
    }
}
