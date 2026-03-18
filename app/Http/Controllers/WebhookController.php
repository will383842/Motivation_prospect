<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Prospect;
use App\Models\WebhookEvent;
use App\Services\ConversionTracker;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class WebhookController extends Controller
{
    /**
     * Recevoir les webhooks Firebase (chatter.registered → marquer prospect comme converti).
     */
    public function handleFirebase(Request $request, ConversionTracker $tracker): JsonResponse
    {
        $data = $request->validate([
            'event' => 'required|string',
            'uid' => 'required|string',
            'data' => 'required|array',
        ]);

        $event = WebhookEvent::create([
            'source' => 'firebase',
            'external_event_id' => $request->header('X-Idempotency-Key', uniqid('evt_')),
            'event_type' => $data['event'],
            'payload' => $data['data'],
            'status' => 'processing',
        ]);

        // On ne s'intéresse qu'à chatter.registered → un prospect s'est converti
        if ($data['event'] === 'chatter.registered') {
            $email = $data['data']['email'] ?? null;
            $firebaseUid = $data['uid'];

            if ($email) {
                $converti = $tracker->handleRegistration($email, $firebaseUid);
                $event->update([
                    'status' => $converti ? 'processed' : 'skipped',
                    'processed_at' => now(),
                ]);
            }
        } else {
            $event->update(['status' => 'skipped', 'processed_at' => now()]);
        }

        return response()->json(['status' => 'accepté'], 202);
    }

    /**
     * Recevoir les callbacks MailWizz (bounce, ouverture, clic, désinscription).
     */
    public function handleMailWizz(Request $request): JsonResponse
    {
        $data = $request->all();
        $eventType = $data['event'] ?? $data['type'] ?? 'unknown';

        $event = WebhookEvent::create([
            'source' => 'mailwizz',
            'event_type' => $eventType,
            'payload' => $data,
            'status' => 'processing',
        ]);

        $email = $data['email'] ?? $data['subscriber_email'] ?? null;
        $prospect = $email ? Prospect::where('email', strtolower($email))->first() : null;

        if ($prospect) {
            match ($eventType) {
                'open', 'opened' => $this->handleOpen($prospect),
                'click', 'clicked' => $this->handleClick($prospect),
                'bounce', 'bounced' => $this->handleBounce($prospect),
                'unsubscribe', 'unsubscribed' => $this->handleUnsubscribe($prospect),
                'complaint', 'spam' => $this->handleComplaint($prospect),
                default => null,
            };
            $event->update(['status' => 'processed', 'processed_at' => now()]);
        } else {
            $event->update(['status' => 'skipped', 'processed_at' => now()]);
        }

        return response()->json(['status' => 'accepté'], 202);
    }

    private function handleOpen(Prospect $prospect): void
    {
        // Éviter le double-count : ne compter que si un log non-ouvert existe
        $lastLog = $prospect->emailLogs()->whereNull('opened_at')->latest()->first();
        if ($lastLog) {
            $prospect->increment('emails_opened');
            $prospect->update(['last_activity_at' => now()]);
            $lastLog->update(['status' => 'opened', 'opened_at' => now()]);
        }
    }

    private function handleClick(Prospect $prospect): void
    {
        // Éviter le double-count : ne compter que si un log non-cliqué existe
        $lastLog = $prospect->emailLogs()->whereNull('clicked_at')->latest()->first();
        if ($lastLog) {
            $prospect->increment('emails_clicked');
            $prospect->update(['last_activity_at' => now()]);
            $lastLog->update(['status' => 'clicked', 'clicked_at' => now()]);
        }
    }

    private function handleBounce(Prospect $prospect): void
    {
        $prospect->increment('emails_bounced');

        $prospect->suppressions()->firstOrCreate(
            ['channel' => 'email'],
            ['reason' => 'bounce', 'source' => 'mailwizz']
        );

        $lastLog = $prospect->emailLogs()->latest()->first();
        $lastLog?->update(['status' => 'bounced', 'bounced_at' => now()]);

        // Stopper toutes les séquences actives (adresse invalide)
        $prospect->sequences()->where('status', 'active')->update([
            'status' => 'exited',
            'exit_reason' => 'bounce',
            'completed_at' => now(),
        ]);
    }

    private function handleUnsubscribe(Prospect $prospect): void
    {
        $prospect->update([
            'is_active' => false,
            'lifecycle_state' => 'unsubscribed',
            'lifecycle_changed_at' => now(),
        ]);

        $prospect->suppressions()->firstOrCreate(
            ['channel' => 'email'],
            ['reason' => 'unsubscribe', 'source' => 'mailwizz']
        );

        $prospect->sequences()->where('status', 'active')->update([
            'status' => 'exited',
            'exit_reason' => 'desinscrit_mailwizz',
            'completed_at' => now(),
        ]);
    }

    private function handleComplaint(Prospect $prospect): void
    {
        $prospect->update(['is_active' => false]);

        $prospect->suppressions()->firstOrCreate(
            ['channel' => 'email'],
            ['reason' => 'complaint', 'source' => 'mailwizz']
        );

        $prospect->sequences()->where('status', 'active')->update([
            'status' => 'exited',
            'exit_reason' => 'plainte_spam',
            'completed_at' => now(),
        ]);
    }
}
