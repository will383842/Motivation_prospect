<?php

use App\Http\Controllers\TrackingController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return redirect('/admin');
});

// Routes de suivi (publiques, sans auth)
Route::get('/join/{code}', [TrackingController::class, 'join'])->name('join');
Route::get('/unsubscribe/{code}', [TrackingController::class, 'unsubscribe'])->name('unsubscribe');
