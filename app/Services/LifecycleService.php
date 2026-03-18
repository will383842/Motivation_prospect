<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Prospect;
use App\Models\ProspectEvent;
use Illuminate\Support\Facades\Log;

/**
 * Gestion du cycle de vie des prospects.
 * 5 états : lead → nurtured → hot → converted / churned / unsubscribed
 */
class LifecycleService
{
    public function processAll(): int
    {
        $count = 0;

        Prospect::where('is_active', true)
            ->whereNotIn('lifecycle_state', ['converted', 'unsubscribed'])
            ->chunkById(100, function ($prospects) use (&$count) {
                foreach ($prospects as $prospect) {
                    $newState = $this->determineState($prospect);
                    if ($newState && $newState !== $prospect->lifecycle_state) {
                        $oldState = $prospect->lifecycle_state;
                        $prospect->update([
                            'lifecycle_state' => $newState,
                            'lifecycle_changed_at' => now(),
                        ]);

                        ProspectEvent::create([
                            'prospect_id' => $prospect->id,
                            'event_type' => 'lifecycle_changed',
                            'event_data' => ['de' => $oldState, 'vers' => $newState],
                            'occurred_at' => now(),
                        ]);

                        $count++;
                    }
                }
            });

        return $count;
    }

    private function determineState(Prospect $prospect): ?string
    {
        $current = $prospect->lifecycle_state;

        // lead → hot : a cliqué un lien (passe directement à hot)
        if ($current === 'lead' && $prospect->emails_clicked > 0) {
            return 'hot';
        }

        // lead → nurtured : a ouvert 2+ emails
        if ($current === 'lead' && $prospect->emails_opened >= 2) {
            return 'nurtured';
        }

        // nurtured → hot : a cliqué un lien
        if ($current === 'nurtured' && $prospect->emails_clicked > 0) {
            return 'hot';
        }

        // Tout état → churned : 30 jours sans activité + au moins 3 emails envoyés
        if (in_array($current, ['lead', 'nurtured']) && $prospect->emails_sent >= 3) {
            $lastActivity = $prospect->last_activity_at ?? $prospect->created_at;
            if ($lastActivity && $lastActivity->diffInDays(now()) >= 30) {
                return 'churned';
            }
        }

        return null;
    }

    /**
     * Marquer un prospect comme converti (quand il s'inscrit sur SOS-Expat).
     */
    public function markConverted(Prospect $prospect, ?string $firebaseUid = null, ?string $convertedVia = null): void
    {
        $prospect->update([
            'lifecycle_state' => 'converted',
            'lifecycle_changed_at' => now(),
            'converted_at' => now(),
            'firebase_uid' => $firebaseUid,
            'converted_via' => $convertedVia,
        ]);

        ProspectEvent::create([
            'prospect_id' => $prospect->id,
            'event_type' => 'converted',
            'event_data' => ['firebase_uid' => $firebaseUid, 'via' => $convertedVia],
            'occurred_at' => now(),
        ]);

        Log::info("Prospect converti", ['prospect_id' => $prospect->id, 'firebase_uid' => $firebaseUid]);
    }
}
