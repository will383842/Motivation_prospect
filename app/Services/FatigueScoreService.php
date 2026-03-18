<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Prospect;

/**
 * Calcule un score de fatigue par prospect (0-100) pour éviter le sur-messaging.
 * 4 facteurs : ratio ignoré, excès fréquence, décroissance récence, ratés consécutifs.
 */
class FatigueScoreService
{
    private const POIDS_IGNORE = 40;
    private const POIDS_FREQUENCE = 20;
    private const POIDS_RECENCE = 15;
    private const POIDS_CONSECUTIF = 25;

    private const EMAILS_HEBDO_OPTIMAL = 3;

    public function calculate(Prospect $prospect): float
    {
        $ignore = $this->ratioIgnore($prospect) * self::POIDS_IGNORE;
        $frequence = $this->excesFrequence($prospect) * self::POIDS_FREQUENCE;
        $recence = $this->decroissanceRecence($prospect) * self::POIDS_RECENCE;
        $consecutif = $this->ratesConsecutifs($prospect) * self::POIDS_CONSECUTIF;

        return min(100, $ignore + $frequence + $recence + $consecutif);
    }

    public function getMultiplier(float $score): float
    {
        return match (true) {
            $score <= 20 => 1.0,    // Envoi normal
            $score <= 40 => 0.75,   // Réduit 25%
            $score <= 60 => 0.5,    // Réduit 50%
            $score <= 80 => 0.25,   // Réduit 75%
            default => 0.0,         // Bloqué totalement
        };
    }

    /**
     * Recalculer les scores de fatigue pour tous les prospects actifs.
     * Appelé toutes les 6h par cron.
     */
    public function recalculateAll(): int
    {
        $count = 0;
        Prospect::where('is_active', true)
            ->where('lifecycle_state', '!=', 'converted')
            ->chunkById(100, function ($prospects) use (&$count) {
                foreach ($prospects as $prospect) {
                    $score = $this->calculate($prospect);
                    $multiplier = $this->getMultiplier($score);
                    $prospect->update([
                        'fatigue_score' => $score,
                        'fatigue_multiplier' => $multiplier,
                    ]);
                    $count++;
                }
            });
        return $count;
    }

    private function ratioIgnore(Prospect $prospect): float
    {
        $sent = (int) $prospect->emails_sent;
        if ($sent <= 0) {
            return 0;
        }
        $interacted = (int) $prospect->emails_opened + (int) $prospect->emails_clicked;
        return max(0, min(1, ($sent - $interacted) / $sent));
    }

    private function excesFrequence(Prospect $prospect): float
    {
        $envoyesCetteSemaine = $prospect->emailLogs()
            ->where('created_at', '>=', now()->startOfWeek())
            ->count();

        if ($envoyesCetteSemaine <= self::EMAILS_HEBDO_OPTIMAL) {
            return 0;
        }
        return min(1, ($envoyesCetteSemaine - self::EMAILS_HEBDO_OPTIMAL) / self::EMAILS_HEBDO_OPTIMAL);
    }

    private function decroissanceRecence(Prospect $prospect): float
    {
        $derniereInteraction = $prospect->emailLogs()
            ->where(function ($q) {
                $q->whereNotNull('opened_at')->orWhereNotNull('clicked_at');
            })
            ->latest('created_at')
            ->value('created_at');

        if (!$derniereInteraction) {
            return $prospect->emails_sent > 0 ? 1.0 : 0;
        }

        $joursDepuis = now()->diffInDays($derniereInteraction);
        return min(1, $joursDepuis / 14);
    }

    private function ratesConsecutifs(Prospect $prospect): float
    {
        $logsRecents = $prospect->emailLogs()
            ->whereIn('status', ['sent', 'delivered'])
            ->latest('created_at')
            ->take(10)
            ->get();

        $consecutif = 0;
        foreach ($logsRecents as $log) {
            if (!$log->opened_at && !$log->clicked_at) {
                $consecutif++;
            } else {
                break;
            }
        }
        return min(1, $consecutif / 5);
    }
}
