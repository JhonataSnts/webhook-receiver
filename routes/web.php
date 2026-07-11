<?php

use App\Http\Controllers\WebhookEventController;
use App\Http\Controllers\WebhookReceiverController;
use App\Http\Controllers\WebhookSourceController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return redirect()->route('events.index');
});

Route::post('/webhooks/{sourceUuid}', [WebhookReceiverController::class, 'store'])
    ->name('webhooks.receive');

Route::get('/sources', [WebhookSourceController::class, 'index'])->name('sources.index');
Route::get('/sources/create', [WebhookSourceController::class, 'create'])->name('sources.create');
Route::post('/sources', [WebhookSourceController::class, 'store'])->name('sources.store');
Route::get('/sources/{source:uuid}', [WebhookSourceController::class, 'show'])->name('sources.show');
Route::post('/sources/{source:uuid}/toggle', [WebhookSourceController::class, 'toggle'])->name('sources.toggle');

Route::get('/events', [WebhookEventController::class, 'index'])->name('events.index');
Route::post('/events/{event:uuid}/replay', [WebhookEventController::class, 'replay'])->name('events.replay');
Route::get('/events/{event:uuid}', [WebhookEventController::class, 'show'])->name('events.show');
