<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Prospect;
use App\Services\GdprService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProspectController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = Prospect::query();

        if ($request->filled('lifecycle_state')) {
            $query->where('lifecycle_state', $request->lifecycle_state);
        }
        if ($request->filled('source')) {
            $query->where('source', $request->source);
        }
        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('email', 'like', "%{$search}%")
                  ->orWhere('first_name', 'like', "%{$search}%")
                  ->orWhere('last_name', 'like', "%{$search}%")
                  ->orWhere('company', 'like', "%{$search}%");
            });
        }
        if ($request->filled('import_batch_id')) {
            $query->where('import_batch_id', $request->import_batch_id);
        }
        if ($request->boolean('active_only', false)) {
            $query->where('is_active', true);
        }

        $sortBy = $request->input('sort_by', 'created_at');
        $sortDir = $request->input('sort_dir', 'desc');
        $query->orderBy($sortBy, $sortDir);

        return response()->json($query->paginate($request->integer('per_page', 25)));
    }

    public function show(string $id): JsonResponse
    {
        $prospect = Prospect::with(['events' => fn($q) => $q->latest('occurred_at')->take(50), 'emailLogs' => fn($q) => $q->latest()->take(50), 'sequences.sequence', 'consents'])->findOrFail($id);
        return response()->json($prospect);
    }

    public function update(Request $request, string $id): JsonResponse
    {
        $prospect = Prospect::findOrFail($id);

        $validated = $request->validate([
            'first_name' => 'nullable|string|max:255',
            'last_name' => 'nullable|string|max:255',
            'phone' => 'nullable|string|max:50',
            'country' => 'nullable|string|max:10',
            'language' => 'nullable|string|max:5',
            'tags' => 'nullable|array',
            'lifecycle_state' => 'nullable|string|in:lead,nurtured,hot,converted,churned,unsubscribed',
            'is_active' => 'nullable|boolean',
        ]);

        $prospect->update($validated);

        if (isset($validated['lifecycle_state'])) {
            $prospect->update(['lifecycle_changed_at' => now()]);
        }

        return response()->json(['message' => 'Prospect mis à jour', 'data' => $prospect->fresh()]);
    }

    public function destroy(string $id): JsonResponse
    {
        $prospect = Prospect::findOrFail($id);
        $prospect->delete();
        return response()->json(['message' => 'Prospect supprimé']);
    }

    public function stats(): JsonResponse
    {
        // Requête agrégée unique (au lieu de 14 queries séparées)
        $agg = Prospect::selectRaw("
            COUNT(*) as total,
            SUM(CASE WHEN is_active = true THEN 1 ELSE 0 END) as actifs,
            SUM(CASE WHEN lifecycle_state = 'lead' THEN 1 ELSE 0 END) as lead_count,
            SUM(CASE WHEN lifecycle_state = 'nurtured' THEN 1 ELSE 0 END) as nurtured_count,
            SUM(CASE WHEN lifecycle_state = 'hot' THEN 1 ELSE 0 END) as hot_count,
            SUM(CASE WHEN lifecycle_state = 'converted' THEN 1 ELSE 0 END) as converted_count,
            SUM(CASE WHEN lifecycle_state = 'churned' THEN 1 ELSE 0 END) as churned_count,
            SUM(CASE WHEN lifecycle_state = 'unsubscribed' THEN 1 ELSE 0 END) as unsubscribed_count,
            SUM(emails_sent) as emails_envoyes,
            SUM(emails_opened) as emails_ouverts,
            SUM(emails_clicked) as emails_cliques
        ")->first();

        $total = (int) $agg->total;
        $emailsEnvoyes = (int) $agg->emails_envoyes;

        $stats = [
            'total' => $total,
            'actifs' => (int) $agg->actifs,
            'par_etat' => [
                'lead' => (int) $agg->lead_count,
                'nurtured' => (int) $agg->nurtured_count,
                'hot' => (int) $agg->hot_count,
                'converted' => (int) $agg->converted_count,
                'churned' => (int) $agg->churned_count,
                'unsubscribed' => (int) $agg->unsubscribed_count,
            ],
            'par_source' => Prospect::selectRaw('source, count(*) as total')->groupBy('source')->pluck('total', 'source'),
            'emails' => [
                'envoyes' => $emailsEnvoyes,
                'ouverts' => (int) $agg->emails_ouverts,
                'cliques' => (int) $agg->emails_cliques,
                'taux_ouverture' => $emailsEnvoyes > 0
                    ? round((int) $agg->emails_ouverts / $emailsEnvoyes * 100, 1) : 0,
                'taux_clic' => $emailsEnvoyes > 0
                    ? round((int) $agg->emails_cliques / $emailsEnvoyes * 100, 1) : 0,
            ],
            'conversions' => [
                'total' => (int) $agg->converted_count,
                'taux' => $total > 0
                    ? round((int) $agg->converted_count / $total * 100, 1) : 0,
            ],
            'imports_recents' => \App\Models\ImportBatch::latest()->take(5)->get(['id', 'filename', 'source', 'imported', 'duplicates_skipped', 'sos_users_skipped', 'status', 'created_at']),
        ];

        return response()->json($stats);
    }

    public function export(string $id, GdprService $gdprService): JsonResponse
    {
        $prospect = Prospect::findOrFail($id);
        return response()->json($gdprService->exportData($prospect));
    }

    public function forget(string $id, GdprService $gdprService): JsonResponse
    {
        $prospect = Prospect::findOrFail($id);
        $gdprService->anonymize($prospect);
        return response()->json(['message' => 'Prospect anonymisé conformément au RGPD']);
    }
}
