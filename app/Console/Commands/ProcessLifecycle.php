<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\LifecycleService;
use Illuminate\Console\Command;

class ProcessLifecycle extends Command
{
    protected $signature = 'lifecycle:process';
    protected $description = 'Traiter les transitions de cycle de vie pour tous les prospects actifs';

    public function handle(LifecycleService $service): int
    {
        $count = $service->processAll();
        $this->info("Traité {$count} transitions de cycle de vie.");
        return self::SUCCESS;
    }
}
