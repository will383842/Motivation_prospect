<?php

declare(strict_types=1);

namespace App\Services;

use Illuminate\Support\Facades\Log;

/**
 * Envoi d'emails via MailWizz Email Engine Cold.
 * Actuellement un stub — sera connecté à l'API MailWizz plus tard.
 */
class MailWizzSender
{
    /**
     * Envoyer un email via MailWizz.
     * Pour l'instant, log l'email pour test. Remplacer par les appels API réels plus tard.
     */
    public function send(string $to, string $subject, string $bodyHtml, ?string $bodyText = null, array $metadata = []): bool
    {
        // TODO: Remplacer par l'intégration API MailWizz
        // $apiUrl = config('services.mailwizz.api_url');
        // $apiKey = config('services.mailwizz.api_key');

        Log::info('[MailWizz STUB] Email mis en file', [
            'destinataire' => $to,
            'sujet' => $subject,
            'taille_body' => strlen($bodyHtml),
            'metadata' => $metadata,
        ]);

        // Simuler le succès pour l'instant
        return true;
    }

    /**
     * Vérifier le statut de livraison d'un email envoyé.
     */
    public function checkStatus(string $messageId): ?string
    {
        // TODO: Implémenter la vérification de statut MailWizz
        return null;
    }
}
