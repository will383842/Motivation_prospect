<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\FatigueScoreService;
use Illuminate\Console\Command;

class CalculateFatigueScores extends Command
{
    protected $signature = 'fatigue:calculate';
    protected $description = 'Recalculer les scores de fatigue pour tous les prospects actifs';

    public function handle(FatigueScoreService $service): int
    {
        $count = $service->recalculateAll();
        $this->info("Scores de fatigue recalculés pour {$count} prospects.");
        return self::SUCCESS;
    }
}
