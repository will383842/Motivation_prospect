<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\ConversionTracker;
use Illuminate\Console\Command;

class ReconcileConversions extends Command
{
    protected $signature = 'conversions:reconcile';
    protected $description = 'Vérifier tous les prospects actifs contre SOS-Expat pour détecter les conversions';

    public function handle(ConversionTracker $tracker): int
    {
        $this->info('Réconciliation des prospects contre SOS-Expat...');
        $count = $tracker->reconcileAll();
        $this->info("Trouvé et converti {$count} prospects déjà inscrits sur SOS-Expat.");
        return self::SUCCESS;
    }
}
