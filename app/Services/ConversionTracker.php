<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Prospect;
use App\Models\ProspectEvent;
use Illuminate\Support\Facades\Log;

/**
 * Suivi des conversions : détecte quand un prospect s'inscrit sur SOS-Expat.
 */
class ConversionTracker
{
    public function __construct(
        private LifecycleService $lifecycleService,
        private SosExpatChecker $sosExpatChecker,
    ) {}

    /**
     * Gérer quand un prospect s'inscrit sur SOS-Expat.
     * Appelé via webhook Firebase : chatter.registered avec correspondance email.
     */
    public function handleRegistration(string $email, ?string $firebaseUid = null): bool
    {
        $prospect = Prospect::where('email', strtolower($email))
            ->where('lifecycle_state', '!=', 'converted')
            ->first();

        if (!$prospect) {
            return false;
        }

        $this->lifecycleService->markConverted($prospect, $firebaseUid, 'webhook_inscription');

        Log::info("Prospect converti via inscription", [
            'prospect_id' => $prospect->id,
            'email' => $email,
            'firebase_uid' => $firebaseUid,
        ]);

        return true;
    }

    /**
     * Gérer quand un prospect visite le lien d'inscription.
     */
    public function handleJoinLinkVisit(string $joinCode): ?Prospect
    {
        $prospect = Prospect::where('join_code', $joinCode)
            ->where('lifecycle_state', '!=', 'converted')
            ->first();

        if (!$prospect) {
            return null;
        }

        ProspectEvent::create([
            'prospect_id' => $prospect->id,
            'event_type' => 'lien_inscription_visite',
            'event_data' => ['join_code' => $joinCode],
            'occurred_at' => now(),
        ]);

        // Passer en "hot" si pas encore
        if (in_array($prospect->lifecycle_state, ['lead', 'nurtured'])) {
            $prospect->update([
                'lifecycle_state' => 'hot',
                'lifecycle_changed_at' => now(),
                'last_activity_at' => now(),
            ]);
        }

        return $prospect;
    }

    /**
     * Réconciliation périodique : re-vérifier tous les prospects actifs contre SOS-Expat.
     * Attrape les conversions qui ont eu lieu en dehors du funnel suivi.
     */
    public function reconcileAll(): int
    {
        $converted = 0;

        Prospect::where('is_active', true)
            ->whereNotIn('lifecycle_state', ['converted', 'unsubscribed'])
            ->chunkById(100, function ($prospects) use (&$converted) {
                $emails = $prospects->pluck('email')->toArray();
                $existing = $this->sosExpatChecker->batchCheck($emails);

                foreach ($prospects as $prospect) {
                    if (in_array($prospect->email, $existing)) {
                        $this->lifecycleService->markConverted($prospect, null, 'reconciliation');
                        $converted++;
                    }
                }
            });

        return $converted;
    }
}
