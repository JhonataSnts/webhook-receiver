<?php

use App\Http\Controllers\WebhookEventController;
use App\Http\Controllers\WebhookReceiverController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return redirect()->route('events.index');
});

Route::post('/webhooks/{sourceUuid}', [WebhookReceiverController::class, 'store'])
    ->name('webhooks.receive');

Route::get('/events', [WebhookEventController::class, 'index'])->name('events.index');
Route::get('/events/{event:uuid}', [WebhookEventController::class, 'show'])->name('events.show');
