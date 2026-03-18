<?php

namespace App\Filament\Widgets;

use App\Models\Prospect;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class StatsOverview extends BaseWidget
{
    protected static ?int $sort = 1;
    protected int|string|array $columnSpan = 'full';

    protected function getStats(): array
    {
        $total = Prospect::count();
        $converted = Prospect::where('lifecycle_state', 'converted')->count();
        $emailsSent = (int) Prospect::sum('emails_sent');
        $emailsOpened = (int) Prospect::sum('emails_opened');

        return [
            Stat::make('Total Prospects', number_format($total))
                ->description('Prospects importés')
                ->icon('heroicon-o-users')
                ->color('primary'),

            Stat::make('Leads Actifs', number_format(Prospect::where('lifecycle_state', 'lead')->where('is_active', true)->count()))
                ->description('En attente de nurturing')
                ->icon('heroicon-o-inbox')
                ->color('info'),

            Stat::make('Hot', number_format(Prospect::where('lifecycle_state', 'hot')->count()))
                ->description('Intéressés (ont cliqué)')
                ->icon('heroicon-o-fire')
                ->color('warning'),

            Stat::make('Convertis', number_format($converted))
                ->description($total > 0 ? round($converted / $total * 100, 1) . '% taux conversion' : '0%')
                ->icon('heroicon-o-check-circle')
                ->color('success'),

            Stat::make('Emails Envoyés', number_format($emailsSent))
                ->description('Total tous prospects')
                ->icon('heroicon-o-envelope')
                ->color('gray'),

            Stat::make('Taux Ouverture', $emailsSent > 0 ? round($emailsOpened / $emailsSent * 100, 1) . '%' : '0%')
                ->description(number_format($emailsOpened) . ' ouverts')
                ->icon('heroicon-o-envelope-open')
                ->color($emailsSent > 0 && $emailsOpened / max(1, $emailsSent) > 0.2 ? 'success' : 'warning'),
        ];
    }
}
