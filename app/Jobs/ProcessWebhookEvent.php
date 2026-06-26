<?php

namespace App\Jobs;

use App\Models\WebhookEvent;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Http;
use Throwable;

class ProcessWebhookEvent implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public function __construct(public WebhookEvent $event)
    {
    }

    public function backoff(): array
    {
        return [60, 300, 900];
    }

    public function handle(): void
    {
        $this->event->loadMissing('source');

        $attempt = $this->event->deliveryAttempts()->firstOrCreate(
            ['attempt_number' => $this->attempts()],
            ['status' => 'pending'],
        );

        $this->event->update(['status' => 'processing']);

        if (! $this->event->source->target_url) {
            $attempt?->update([
                'status' => 'skipped',
                'attempted_at' => now(),
            ]);

            $this->event->update([
                'status' => 'processed',
                'processed_at' => now(),
            ]);

            return;
        }

        try {
            $response = Http::timeout(10)
                ->acceptJson()
                ->post($this->event->source->target_url, $this->event->payload ?? []);

            $attempt?->update([
                'status' => $response->successful() ? 'succeeded' : 'failed',
                'response_status' => $response->status(),
                'response_body' => str($response->body())->limit(2000)->toString(),
                'attempted_at' => now(),
            ]);

            if (! $response->successful()) {
                throw new \RuntimeException('Target returned HTTP '.$response->status());
            }

            $this->event->update([
                'status' => 'processed',
                'processed_at' => now(),
            ]);
        } catch (Throwable $exception) {
            $attempt?->update([
                'status' => 'failed',
                'error_message' => $exception->getMessage(),
                'attempted_at' => now(),
                'next_retry_at' => now()->addSeconds($this->backoff()[min($this->attempts() - 1, 2)]),
            ]);

            $this->event->update(['status' => 'failed']);

            throw $exception;
        }
    }
}
