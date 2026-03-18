<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\ImportController;
use App\Http\Controllers\Api\ProspectController;
use App\Http\Controllers\Api\SequenceController;
use App\Http\Controllers\Api\SuppressionController;
use App\Http\Controllers\Api\TemplateController;
use App\Http\Controllers\WebhookController;
use App\Http\Middleware\VerifyWebhookSignature;
use App\Http\Middleware\WebhookIdempotency;
use Illuminate\Support\Facades\Route;

// Vérification santé (public)
Route::get('/health', fn () => response()->json(['status' => 'ok', 'service' => 'conversion-engine']));

// Authentification (rate limité : 5 tentatives/minute)
Route::post('/auth/login', [AuthController::class, 'login'])
    ->middleware('throttle:5,1');

// Webhooks (signature HMAC + idempotence + rate limit)
Route::post('/webhook/firebase', [WebhookController::class, 'handleFirebase'])
    ->middleware([VerifyWebhookSignature::class, WebhookIdempotency::class, 'throttle:100,1']);
Route::post('/webhook/mailwizz', [WebhookController::class, 'handleMailWizz'])
    ->middleware([WebhookIdempotency::class, 'throttle:100,1']);

// Routes protégées par authentification
Route::middleware('auth:sanctum')->group(function () {

    // Auth
    Route::post('/auth/logout', [AuthController::class, 'logout']);
    Route::get('/auth/me', [AuthController::class, 'me']);

    // Dashboard
    Route::get('/dashboard/stats', [ProspectController::class, 'stats']);

    // Import CSV (rate limité : 10 imports/heure)
    Route::post('/import/upload', [ImportController::class, 'upload'])->middleware('throttle:10,60');
    Route::get('/import/batches', [ImportController::class, 'batches']);
    Route::get('/import/batches/{id}', [ImportController::class, 'batchDetail']);

    // Prospects CRUD
    Route::get('/prospects', [ProspectController::class, 'index']);
    Route::get('/prospects/{id}', [ProspectController::class, 'show']);
    Route::patch('/prospects/{id}', [ProspectController::class, 'update']);
    Route::delete('/prospects/{id}', [ProspectController::class, 'destroy']);

    // RGPD
    Route::post('/gdpr/export/{id}', [ProspectController::class, 'export']);
    Route::post('/gdpr/forget/{id}', [ProspectController::class, 'forget']);

    // Séquences CRUD
    Route::get('/sequences', [SequenceController::class, 'index']);
    Route::post('/sequences', [SequenceController::class, 'store']);
    Route::get('/sequences/{id}', [SequenceController::class, 'show']);
    Route::patch('/sequences/{id}', [SequenceController::class, 'update']);
    Route::post('/sequences/{id}/activate', [SequenceController::class, 'activate']);
    Route::delete('/sequences/{id}', [SequenceController::class, 'destroy']);

    // Templates CRUD
    Route::get('/templates', [TemplateController::class, 'index']);
    Route::post('/templates', [TemplateController::class, 'store']);
    Route::get('/templates/{id}', [TemplateController::class, 'show']);
    Route::patch('/templates/{id}', [TemplateController::class, 'update']);
    Route::post('/templates/{id}/variants', [TemplateController::class, 'addVariant']);
    Route::get('/templates/{id}/preview', [TemplateController::class, 'preview']);

    // Liste de suppression
    Route::get('/suppressions', [SuppressionController::class, 'index']);
    Route::post('/suppressions/{id}/lift', [SuppressionController::class, 'lift']);
});
