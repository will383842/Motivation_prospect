<?php

declare(strict_types=1);

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Vérifie si un email est déjà inscrit sur SOS-Expat.
 * Utilise un endpoint Firebase Cloud Function pour la vérification batch.
 */
class SosExpatChecker
{
    public function isRegistered(string $email): bool
    {
        $cacheKey = 'sos_check:' . hash('sha256', strtolower($email));

        return Cache::remember($cacheKey, 86400, function () use ($email) {
            return $this->checkViaApi($email);
        });
    }

    /**
     * Vérification batch : retourne les emails qui SONT déjà inscrits.
     */
    public function batchCheck(array $emails): array
    {
        $uncached = [];
        $results = [];

        foreach ($emails as $email) {
            $email = strtolower(trim($email));
            $cacheKey = 'sos_check:' . hash('sha256', $email);

            if (Cache::has($cacheKey)) {
                if (Cache::get($cacheKey)) {
                    $results[] = $email;
                }
            } else {
                $uncached[] = $email;
            }
        }

        if (!empty($uncached)) {
            $apiResults = $this->batchCheckViaApi($uncached);
            foreach ($uncached as $email) {
                $isRegistered = in_array($email, $apiResults);
                Cache::put('sos_check:' . hash('sha256', $email), $isRegistered, 86400);
                if ($isRegistered) {
                    $results[] = $email;
                }
            }
        }

        return $results;
    }

    private function checkViaApi(string $email): bool
    {
        $apiUrl = config('services.sos_expat.check_email_url');
        $apiKey = config('services.sos_expat.api_key');

        if (!$apiUrl || !$apiKey) {
            Log::warning('API SOS-Expat non configurée, vérification ignorée');
            return false;
        }

        try {
            $response = Http::timeout(10)
                ->withHeaders(['X-API-Key' => $apiKey])
                ->post($apiUrl, ['emails' => [$email]]);

            if ($response->successful()) {
                $existing = $response->json('existing', []);
                return in_array(strtolower($email), array_map('strtolower', $existing));
            }
        } catch (\Throwable $e) {
            Log::warning("Vérification SOS-Expat échouée : {$e->getMessage()}");
        }

        return false;
    }

    private function batchCheckViaApi(array $emails): array
    {
        $apiUrl = config('services.sos_expat.check_email_url');
        $apiKey = config('services.sos_expat.api_key');

        if (!$apiUrl || !$apiKey) {
            return [];
        }

        try {
            $existing = [];
            foreach (array_chunk($emails, 100) as $chunk) {
                $response = Http::timeout(30)
                    ->withHeaders(['X-API-Key' => $apiKey])
                    ->post($apiUrl, ['emails' => $chunk]);

                if ($response->successful()) {
                    $existing = array_merge($existing, $response->json('existing', []));
                }
            }
            return array_map('strtolower', $existing);
        } catch (\Throwable $e) {
            Log::warning("Vérification batch SOS-Expat échouée : {$e->getMessage()}");
            return [];
        }
    }
}
