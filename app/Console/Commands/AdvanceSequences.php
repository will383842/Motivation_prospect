<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\SequenceEngine;
use Illuminate\Console\Command;

class AdvanceSequences extends Command
{
    protected $signature = 'sequences:advance';
    protected $description = 'Avancer toutes les séquences prospect actives qui sont dues';

    public function handle(SequenceEngine $engine): int
    {
        $count = $engine->advanceAll();
        $this->info("Avancé {$count} inscriptions de séquence.");
        return self::SUCCESS;
    }
}
