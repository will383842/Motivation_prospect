<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class VerifyWebhookSignature
{
    public function handle(Request $request, Closure $next): Response
    {
        $signature = $request->header('X-Webhook-Signature');
        $timestamp = $request->header('X-Webhook-Timestamp');

        if (!$signature || !$timestamp) {
            abort(401, 'Signature manquante');
        }

        if (abs(time() - (int) $timestamp) > 300) {
            abort(401, 'Horodatage expiré');
        }

        $payload = $request->getContent();
        $expected = hash_hmac('sha256', $timestamp . '.' . $payload, config('services.sos_expat.webhook_secret'));

        if (!hash_equals($expected, $signature)) {
            abort(401, 'Signature invalide');
        }

        return $next($request);
    }
}
