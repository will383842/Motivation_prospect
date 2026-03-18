<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\SuppressionList;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SuppressionController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = SuppressionList::with('prospect:id,email,first_name,last_name');
        if ($request->filled('reason')) {
            $query->where('reason', $request->reason);
        }
        return response()->json($query->latest()->paginate($request->integer('per_page', 25)));
    }

    public function lift(string $id): JsonResponse
    {
        $suppression = SuppressionList::findOrFail($id);
        $suppression->update(['lifted_at' => now()]);
        return response()->json(['message' => 'Suppression levée']);
    }
}
