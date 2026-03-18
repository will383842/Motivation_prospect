<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\EmailSequence;
use App\Models\ProspectSequence;
use App\Models\SequenceStep;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SequenceController extends Controller
{
    public function index(): JsonResponse
    {
        $sequences = EmailSequence::withCount(['steps', 'enrollments'])->latest()->get();
        return response()->json($sequences);
    }

    public function show(string $id): JsonResponse
    {
        $sequence = EmailSequence::with('steps')->withCount('enrollments')->findOrFail($id);

        $stats = [
            'inscrits_actifs' => ProspectSequence::where('sequence_id', $id)->where('status', 'active')->count(),
            'termines' => ProspectSequence::where('sequence_id', $id)->where('status', 'completed')->count(),
            'sortis' => ProspectSequence::where('sequence_id', $id)->where('status', 'exited')->count(),
        ];

        return response()->json(['sequence' => $sequence, 'stats' => $stats]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'slug' => 'required|string|max:100|unique:email_sequences,slug',
            'description' => 'nullable|string',
            'trigger_event' => 'nullable|string|max:100',
            'priority' => 'integer|min:0',
            'steps' => 'required|array|min:1',
            'steps.*.type' => 'required|string|in:email,delay,condition,ab_split,action',
            'steps.*.template_slug' => 'nullable|string',
            'steps.*.config' => 'nullable|array',
        ]);

        $sequence = EmailSequence::create([
            'name' => $validated['name'],
            'slug' => $validated['slug'],
            'description' => $validated['description'] ?? null,
            'trigger_event' => $validated['trigger_event'] ?? null,
            'status' => 'draft',
            'priority' => $validated['priority'] ?? 0,
        ]);

        foreach ($validated['steps'] as $index => $stepData) {
            SequenceStep::create([
                'sequence_id' => $sequence->id,
                'step_order' => $index + 1,
                'type' => $stepData['type'],
                'template_slug' => $stepData['template_slug'] ?? null,
                'config' => $stepData['config'] ?? null,
            ]);
        }

        return response()->json(['message' => 'Séquence créée', 'data' => $sequence->load('steps')], 201);
    }

    public function update(Request $request, string $id): JsonResponse
    {
        $sequence = EmailSequence::findOrFail($id);

        $validated = $request->validate([
            'name' => 'nullable|string|max:255',
            'description' => 'nullable|string',
            'trigger_event' => 'nullable|string|max:100',
            'priority' => 'nullable|integer|min:0',
            'status' => 'nullable|string|in:draft,active,paused,archived',
        ]);

        $sequence->update(array_filter($validated, fn($v) => $v !== null));

        return response()->json(['message' => 'Séquence mise à jour', 'data' => $sequence->fresh()]);
    }

    public function activate(string $id): JsonResponse
    {
        $sequence = EmailSequence::findOrFail($id);
        if ($sequence->steps()->count() === 0) {
            return response()->json(['error' => 'Impossible d\'activer une séquence sans étapes'], 422);
        }
        $sequence->update(['status' => 'active']);
        return response()->json(['message' => 'Séquence activée']);
    }

    public function destroy(string $id): JsonResponse
    {
        $sequence = EmailSequence::findOrFail($id);
        $activeEnrollments = ProspectSequence::where('sequence_id', $id)->where('status', 'active')->count();
        if ($activeEnrollments > 0) {
            return response()->json(['error' => "Impossible de supprimer : {$activeEnrollments} prospects actifs dans cette séquence"], 422);
        }
        $sequence->delete();
        return response()->json(['message' => 'Séquence archivée']);
    }
}
