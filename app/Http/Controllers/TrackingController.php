<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Prospect;
use App\Models\ProspectEvent;
use App\Services\ConversionTracker;

class TrackingController extends Controller
{
    /**
     * Gérer les visites du lien d'inscription : /join/{code}
     * Redirige vers la page d'inscription SOS-Expat.
     */
    public function join(string $code, ConversionTracker $tracker)
    {
        $prospect = $tracker->handleJoinLinkVisit($code);

        $registrationUrl = config('services.sos_expat.registration_url', 'https://sos-expat.com/chatter/register');

        if ($prospect) {
            $registrationUrl .= '?ref=conversion_engine&prospect=' . $prospect->join_code;
        }

        return redirect($registrationUrl);
    }

    /**
     * Gérer la désinscription : /unsubscribe/{code}
     */
    public function unsubscribe(string $code)
    {
        $prospect = Prospect::where('join_code', $code)->first();

        if ($prospect) {
            $prospect->update([
                'is_active' => false,
                'lifecycle_state' => 'unsubscribed',
                'lifecycle_changed_at' => now(),
            ]);

            $prospect->suppressions()->firstOrCreate(
                ['channel' => 'email'],
                ['reason' => 'desinscription', 'source' => 'utilisateur']
            );

            $prospect->sequences()->where('status', 'active')->update([
                'status' => 'exited',
                'exit_reason' => 'desinscrit',
                'completed_at' => now(),
            ]);

            ProspectEvent::create([
                'prospect_id' => $prospect->id,
                'event_type' => 'desinscrit',
                'event_data' => ['methode' => 'lien'],
                'occurred_at' => now(),
            ]);
        }

        return view('unsubscribed');
    }
}
