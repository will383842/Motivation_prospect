<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\EmailLog;
use App\Models\Prospect;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * Système de contrôle d'envoi email (9-point gate).
 * Vérifie 9 conditions avant chaque envoi pour protéger contre le spam.
 */
class ProspectDispatcher
{
    private const LIMITES = [
        'daily' => 2,          // max 2 emails/jour par prospect
        'weekly' => 5,         // max 5 emails/semaine par prospect
        'gap_hours' => 12,     // min 12h entre 2 emails
        'quiet_start' => '22:00',
        'quiet_end' => '08:00',
    ];

    public function __construct(
        private TemplateRenderer $templateRenderer,
        private MailWizzSender $mailWizzSender,
        private FatigueScoreService $fatigueService,
    ) {}

    /**
     * Envoyer un email à un prospect via le système de contrôle 9 points.
     */
    public function send(Prospect $prospect, string $templateSlug, bool $isUrgent = false): bool
    {
        // Point 1 : Interrupteur global
        if (Cache::get('email_kill_switch', false)) {
            Log::info("Interrupteur global actif, envoi ignoré pour {$prospect->id}");
            return false;
        }

        // Point 2 : Liste de suppression
        if ($prospect->isSuppressed()) {
            return false;
        }

        // Point 3 : Vérification lifecycle
        if (in_array($prospect->lifecycle_state, ['converted', 'unsubscribed', 'churned'])) {
            return false;
        }

        // Point 4 : Déjà utilisateur SOS-Expat
        if ($prospect->is_sos_expat_user) {
            return false;
        }

        // Point 5 : Vérification actif
        if (!$prospect->is_active) {
            return false;
        }

        // Point 6 : Score de fatigue (défaut 1.0 si jamais calculé)
        if (($prospect->fatigue_multiplier ?? 1.0) <= 0.0) {
            Log::info("Score de fatigue trop élevé pour le prospect {$prospect->id}, envoi ignoré");
            return false;
        }

        // Point 7 : Limite quotidienne
        $countQuotidien = $this->getCount($prospect, 'daily');
        $limiteQuotidienne = $isUrgent ? self::LIMITES['daily'] + 1 : self::LIMITES['daily'];
        if ($countQuotidien >= $limiteQuotidienne) {
            return false;
        }

        // Point 8 : Limite hebdomadaire
        if ($this->getCount($prospect, 'weekly') >= self::LIMITES['weekly']) {
            return false;
        }

        // Point 9 : Délai de refroidissement
        $dernierEnvoi = EmailLog::where('prospect_id', $prospect->id)
            ->whereNotNull('sent_at')
            ->latest('sent_at')
            ->value('sent_at');

        if ($dernierEnvoi && Carbon::parse($dernierEnvoi)->diffInHours(now()) < self::LIMITES['gap_hours']) {
            return false;
        }

        // Point 10 : Heures silencieuses (fuseau horaire du prospect)
        if ($this->isHeuresSilencieuses($prospect)) {
            return false;
        }

        // Rendre le template
        $rendu = $this->templateRenderer->renderForProspect($templateSlug, $prospect);
        if (!$rendu) {
            Log::warning("Template introuvable : {$templateSlug} pour la langue {$prospect->language}");
            return false;
        }

        // Créer le log
        $log = EmailLog::create([
            'prospect_id' => $prospect->id,
            'template_slug' => $templateSlug,
            'subject' => $rendu['subject'],
            'status' => 'queued',
            'metadata' => [
                'language' => $prospect->language,
                'fatigue_multiplier' => $prospect->fatigue_multiplier,
            ],
        ]);

        // Envoyer via MailWizz
        $envoye = $this->mailWizzSender->send(
            to: $prospect->email,
            subject: $rendu['subject'],
            bodyHtml: $rendu['body_html'],
            bodyText: $rendu['body_text'] ?? null,
            metadata: ['log_id' => $log->id, 'prospect_id' => $prospect->id],
        );

        if ($envoye) {
            $log->update(['status' => 'sent', 'sent_at' => now()]);
            $prospect->increment('emails_sent');
            $this->incrementCount($prospect, 'daily');
            $this->incrementCount($prospect, 'weekly');
        } else {
            $log->update(['status' => 'failed']);
        }

        return $envoye;
    }

    private function isHeuresSilencieuses(Prospect $prospect): bool
    {
        $tz = $prospect->timezone ?: 'UTC';
        $heureLocale = now()->timezone($tz)->format('H:i');
        return $heureLocale >= self::LIMITES['quiet_start'] || $heureLocale < self::LIMITES['quiet_end'];
    }

    private function getCount(Prospect $prospect, string $periode): int
    {
        $key = match ($periode) {
            'daily' => "email_count:daily:{$prospect->id}:" . now()->toDateString(),
            'weekly' => "email_count:weekly:{$prospect->id}:" . now()->format('Y-\WW'),
        };
        return (int) Cache::get($key, 0);
    }

    private function incrementCount(Prospect $prospect, string $periode): void
    {
        $key = match ($periode) {
            'daily' => "email_count:daily:{$prospect->id}:" . now()->toDateString(),
            'weekly' => "email_count:weekly:{$prospect->id}:" . now()->format('Y-\WW'),
        };
        $ttl = match ($periode) {
            'daily' => 86400,
            'weekly' => 604800,
        };
        Cache::increment($key);
        if (Cache::get($key) === 1) {
            Cache::put($key, 1, $ttl);
        }
    }
}
