<?php

namespace App\Filament\Widgets;

use App\Models\Prospect;
use Filament\Widgets\ChartWidget;

class LifecycleChart extends ChartWidget
{
    protected static ?string $heading = 'Distribution par État';
    protected static ?int $sort = 2;
    protected int|string|array $columnSpan = 'full';
    protected static ?string $maxHeight = '300px';

    protected function getData(): array
    {
        $states = ['lead', 'nurtured', 'hot', 'converted', 'churned', 'unsubscribed'];
        $counts = [];
        foreach ($states as $state) {
            $counts[] = Prospect::where('lifecycle_state', $state)->count();
        }

        return [
            'datasets' => [
                [
                    'label' => 'Prospects',
                    'data' => $counts,
                    'backgroundColor' => ['#6366f1', '#06b6d4', '#f59e0b', '#10b981', '#ef4444', '#6b7280'],
                ],
            ],
            'labels' => ['Lead', 'Nurtured', 'Hot', 'Converti', 'Churned', 'Désinscrit'],
        ];
    }

    protected function getType(): string
    {
        return 'doughnut';
    }
}
