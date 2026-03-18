<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\ConsentRecord;
use App\Models\Prospect;

/**
 * Conformité RGPD : export de données, anonymisation, gestion du consentement.
 */
class GdprService
{
    /**
     * Exporter toutes les données d'un prospect (droit d'accès Art. 15 RGPD).
     */
    public function exportData(Prospect $prospect): array
    {
        return [
            'donnees_personnelles' => [
                'email' => $prospect->email,
                'prenom' => $prospect->first_name,
                'nom' => $prospect->last_name,
                'telephone' => $prospect->phone,
                'pays' => $prospect->country,
                'langue' => $prospect->language,
                'source' => $prospect->source,
                'poste' => $prospect->job_title,
                'entreprise' => $prospect->company,
            ],
            'engagement' => [
                'emails_envoyes' => $prospect->emails_sent,
                'emails_ouverts' => $prospect->emails_opened,
                'emails_cliques' => $prospect->emails_clicked,
                'etat_lifecycle' => $prospect->lifecycle_state,
                'score_engagement' => $prospect->engagement_score,
            ],
            'historique_emails' => $prospect->emailLogs()
                ->select('template_slug', 'subject', 'status', 'sent_at', 'opened_at', 'clicked_at')
                ->limit(1000)
                ->get()
                ->toArray(),
            'consentements' => $prospect->consents()->get()->toArray(),
        ];
    }

    /**
     * Anonymiser un prospect (droit à l'effacement Art. 17 RGPD).
     */
    public function anonymize(Prospect $prospect): void
    {
        // Arrêter toutes les séquences
        $prospect->sequences()->where('status', 'active')->update([
            'status' => 'exited',
            'exit_reason' => 'effacement_rgpd',
            'completed_at' => now(),
        ]);

        // Anonymiser les données personnelles
        $prospect->update([
            'email' => 'anonymise_' . $prospect->id . '@supprime.local',
            'first_name' => null,
            'last_name' => null,
            'phone' => null,
            'job_title' => null,
            'company' => null,
            'extra' => null,
            'is_active' => false,
            'lifecycle_state' => 'unsubscribed',
        ]);

        // Anonymiser les logs email
        $prospect->emailLogs()->update(['subject' => '[SUPPRIMÉ]', 'metadata' => null]);

        // Anonymiser les events (supprimer les données personnelles)
        $prospect->events()->update(['event_data' => null]);

        // Révoquer tous les consentements
        $prospect->consents()->whereNull('revoked_at')->update(['revoked_at' => now()]);

        // Ajouter à la liste de suppression
        $prospect->suppressions()->firstOrCreate(
            ['channel' => 'email'],
            ['reason' => 'effacement_rgpd', 'source' => 'rgpd']
        );
    }

    /**
     * Enregistrer un consentement.
     */
    public function recordConsent(Prospect $prospect, string $type, bool $granted = true): ConsentRecord
    {
        return ConsentRecord::create([
            'prospect_id' => $prospect->id,
            'consent_type' => $type,
            'granted' => $granted,
            'version' => '1.0',
            'granted_at' => $granted ? now() : null,
        ]);
    }
}
