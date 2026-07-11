<?php

namespace App\Jobs;

use App\Models\WebhookDeliveryAttempt;
use App\Models\WebhookEvent;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Http;
use Throwable;

class ProcessWebhookEvent implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public function __construct(
        public WebhookEvent $event,
        public ?int $deliveryAttemptId = null,
    )
    {
    }

    public function backoff(): array
    {
        return [60, 300, 900];
    }

    public function handle(): void
    {
        $this->event->loadMissing('source');

        $attempt = $this->resolveAttempt();

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

    private function resolveAttempt(): WebhookDeliveryAttempt
    {
        if ($this->deliveryAttemptId) {
            return WebhookDeliveryAttempt::query()->findOrFail($this->deliveryAttemptId);
        }

        $pendingAttempt = $this->event->deliveryAttempts()
            ->where('status', 'pending')
            ->orderBy('attempt_number')
            ->first();

        if ($pendingAttempt) {
            return $pendingAttempt;
        }

        return $this->event->deliveryAttempts()->create([
            'attempt_number' => ($this->event->deliveryAttempts()->max('attempt_number') ?? 0) + 1,
            'status' => 'pending',
        ]);
    }
}
