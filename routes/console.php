<?php

use App\Models\WebhookSource;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Artisan;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('hookrelay:send-demo {scenario=accepted : accepted, duplicate or rejected} {--source=demo-source : Webhook source slug} {--url= : Base application URL}', function () {
    $scenario = strtolower((string) $this->argument('scenario'));

    if (! in_array($scenario, ['accepted', 'duplicate', 'rejected'], true)) {
        $this->error('Invalid scenario. Use accepted, duplicate or rejected.');

        return self::FAILURE;
    }

    $source = WebhookSource::query()
        ->where('slug', $this->option('source'))
        ->first();

    if (! $source) {
        $this->error('Webhook source not found. Create one in /sources or run php artisan db:seed.');

        return self::FAILURE;
    }

    $baseUrl = rtrim($this->option('url') ?: config('app.url'), '/');
    $endpoint = "{$baseUrl}/webhooks/{$source->uuid}";
    $idempotencyKey = $scenario === 'duplicate'
        ? 'demo-duplicate-event'
        : 'demo-'.now()->format('YmdHis').'-'.str()->random(8);

    $payload = json_encode([
        'event' => 'invoice.paid',
        'id' => $idempotencyKey,
        'source' => $source->slug,
        'amount' => 19990,
        'currency' => 'BRL',
        'sent_at' => now()->toIso8601String(),
    ], JSON_UNESCAPED_SLASHES);

    $requests = $scenario === 'duplicate' ? 2 : 1;

    for ($currentRequest = 1; $currentRequest <= $requests; $currentRequest++) {
        $timestamp = now()->timestamp;
        $signature = 'sha256='.hash_hmac('sha256', $timestamp.'.'.$payload, $source->signing_secret);

        if ($scenario === 'rejected') {
            $signature = 'sha256=invalid-demo-signature';
        }

        $response = Http::acceptJson()
            ->withHeaders([
                'X-HookRelay-Timestamp' => $timestamp,
                'X-HookRelay-Signature' => $signature,
                'X-HookRelay-Idempotency-Key' => $idempotencyKey,
            ])
            ->withBody($payload, 'application/json')
            ->post($endpoint);

        $this->line("Request {$currentRequest} -> HTTP {$response->status()}");
        $this->line($response->body());
    }

    $this->newLine();
    $this->info('Open /events to inspect the received webhook history.');

    return self::SUCCESS;
})->purpose('Send signed demo webhook requests to HookRelay');
