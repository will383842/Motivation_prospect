<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\EmailTemplate;
use App\Models\Prospect;

/**
 * Rendu des templates email avec substitution de variables.
 * Chaîne de fallback : langue exacte → anglais → français → n'importe quelle langue.
 */
class TemplateRenderer
{
    public function renderForProspect(string $slug, Prospect $prospect): ?array
    {
        $template = EmailTemplate::where('slug', $slug)->where('is_active', true)->first();
        if (!$template) {
            return null;
        }

        $variant = $template->variants()->where('language', $prospect->language)->where('is_active', true)->first()
            ?? $template->variants()->where('language', 'en')->where('is_active', true)->first()
            ?? $template->variants()->where('language', 'fr')->where('is_active', true)->first()
            ?? $template->variants()->where('is_active', true)->first();

        if (!$variant) {
            return null;
        }

        $variables = $this->buildVariables($prospect);

        return [
            'subject' => $this->substitute($variant->subject, $variables),
            'body_html' => $this->substitute($variant->body_html, $variables),
            'body_text' => $variant->body_text ? $this->substitute($variant->body_text, $variables) : null,
        ];
    }

    private function buildVariables(Prospect $prospect): array
    {
        $joinUrl = config('app.url') . '/join/' . $prospect->join_code;
        $unsubUrl = config('app.url') . '/unsubscribe/' . $prospect->join_code;

        return [
            'prenom' => $prospect->first_name ?? '',
            'nom' => $prospect->last_name ?? '',
            'nom_complet' => $prospect->full_name,
            'email' => $prospect->email,
            'pays' => $prospect->country ?? '',
            'poste' => $prospect->job_title ?? '',
            'entreprise' => $prospect->company ?? '',
            'lien_inscription' => $joinUrl,
            'lien_desinscription' => $unsubUrl,
            // Aliases anglais pour compatibilité
            'first_name' => $prospect->first_name ?? '',
            'last_name' => $prospect->last_name ?? '',
            'full_name' => $prospect->full_name,
            'job_title' => $prospect->job_title ?? '',
            'company' => $prospect->company ?? '',
            'join_link' => $joinUrl,
            'unsubscribe_link' => $unsubUrl,
        ];
    }

    private function substitute(string $text, array $variables): string
    {
        foreach ($variables as $key => $value) {
            $safeValue = htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
            $text = str_replace('{{' . $key . '}}', $safeValue, $text);
        }
        return $text;
    }
}
