<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\EmailTemplate;
use App\Models\EmailTemplateVariant;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TemplateController extends Controller
{
    public function index(): JsonResponse
    {
        $templates = EmailTemplate::withCount('variants')->latest()->get();
        return response()->json($templates);
    }

    public function show(string $id): JsonResponse
    {
        $template = EmailTemplate::with('variants')->findOrFail($id);
        return response()->json($template);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'slug' => 'required|string|max:100|unique:email_templates,slug',
            'name' => 'required|string|max:255',
            'category' => 'nullable|string|in:onboarding,nurture,conversion,reactivation',
            'variants' => 'required|array|min:1',
            'variants.*.language' => 'required|string|max:5',
            'variants.*.subject' => 'required|string|max:500',
            'variants.*.body_html' => 'required|string',
            'variants.*.body_text' => 'nullable|string',
            'variants.*.preview_text' => 'nullable|string|max:255',
        ]);

        $template = EmailTemplate::create([
            'slug' => $validated['slug'],
            'name' => $validated['name'],
            'category' => $validated['category'] ?? null,
            'is_active' => true,
        ]);

        foreach ($validated['variants'] as $variantData) {
            EmailTemplateVariant::create(array_merge($variantData, ['template_id' => $template->id, 'is_active' => true]));
        }

        return response()->json(['message' => 'Template créé', 'data' => $template->load('variants')], 201);
    }

    public function update(Request $request, string $id): JsonResponse
    {
        $template = EmailTemplate::findOrFail($id);

        $validated = $request->validate([
            'name' => 'nullable|string|max:255',
            'category' => 'nullable|string',
            'is_active' => 'nullable|boolean',
        ]);

        $template->update(array_filter($validated, fn($v) => $v !== null));
        return response()->json(['message' => 'Template mis à jour', 'data' => $template->fresh()]);
    }

    public function addVariant(Request $request, string $id): JsonResponse
    {
        $template = EmailTemplate::findOrFail($id);

        $validated = $request->validate([
            'language' => 'required|string|max:5',
            'subject' => 'required|string|max:500',
            'body_html' => 'required|string',
            'body_text' => 'nullable|string',
            'preview_text' => 'nullable|string|max:255',
        ]);

        $existing = $template->variants()->where('language', $validated['language'])->first();
        if ($existing) {
            $existing->update($validated);
            return response()->json(['message' => 'Variante mise à jour', 'data' => $existing->fresh()]);
        }

        $variant = EmailTemplateVariant::create(array_merge($validated, ['template_id' => $template->id, 'is_active' => true]));
        return response()->json(['message' => 'Variante ajoutée', 'data' => $variant], 201);
    }

    public function preview(Request $request, string $id): JsonResponse
    {
        $template = EmailTemplate::findOrFail($id);
        $language = $request->input('language', 'fr');

        $variant = $template->variants()->where('language', $language)->first()
            ?? $template->variants()->first();

        if (!$variant) {
            return response()->json(['error' => 'Aucune variante trouvée'], 404);
        }

        $fakeProspect = new \App\Models\Prospect([
            'first_name' => 'Marie', 'last_name' => 'Dupont', 'email' => 'marie@example.com',
            'country' => 'FR', 'language' => $language, 'job_title' => 'Développeuse', 'company' => 'TechCorp',
            'join_code' => 'PREVIEW123',
        ]);

        $renderer = app(\App\Services\TemplateRenderer::class);
        $rendered = $renderer->renderForProspect($template->slug, $fakeProspect);

        return response()->json(['rendered' => $rendered, 'variant' => $variant]);
    }
}
