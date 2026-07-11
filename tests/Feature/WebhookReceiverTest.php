<?php

namespace Tests\Feature;

use App\Jobs\ProcessWebhookEvent;
use App\Models\WebhookEvent;
use App\Models\WebhookSource;
use Illuminate\Contracts\Queue\Job as QueueJob;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Mockery;
use Tests\TestCase;

class WebhookReceiverTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_accepts_a_signed_webhook_event(): void
    {
        Queue::fake();

        $source = WebhookSource::query()->create([
            'name' => 'Billing Provider',
            'slug' => 'billing-provider',
            'signing_secret' => 'secret-value',
        ]);

        $payload = json_encode(['event' => 'invoice.paid', 'id' => 'evt_123']);
        $timestamp = now()->timestamp;

        $response = $this->signedPost($source, $payload, $timestamp, 'evt_123');

        $response->assertAccepted()
            ->assertJson(['status' => 'accepted']);

        $this->assertDatabaseHas('webhook_events', [
            'webhook_source_id' => $source->id,
            'idempotency_key' => 'evt_123',
            'status' => 'received',
        ]);

        Queue::assertPushed(ProcessWebhookEvent::class);
    }

    public function test_it_rejects_an_invalid_signature(): void
    {
        $source = WebhookSource::query()->create([
            'name' => 'Billing Provider',
            'slug' => 'billing-provider',
            'signing_secret' => 'secret-value',
        ]);

        $payload = json_encode(['event' => 'invoice.paid']);
        $timestamp = now()->timestamp;

        $response = $this->call('POST', "/webhooks/{$source->uuid}", [], [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_X_HOOKRELAY_TIMESTAMP' => $timestamp,
            'HTTP_X_HOOKRELAY_SIGNATURE' => 'sha256=invalid',
        ], $payload);

        $response->assertUnauthorized()
            ->assertJson([
                'status' => 'rejected',
                'reason' => 'invalid_signature',
            ]);

        $this->assertDatabaseHas('webhook_events', [
            'webhook_source_id' => $source->id,
            'status' => 'rejected',
            'rejection_reason' => 'invalid_signature',
        ]);
    }

    public function test_it_returns_duplicate_for_reused_idempotency_key(): void
    {
        Queue::fake();

        $source = WebhookSource::query()->create([
            'name' => 'Billing Provider',
            'slug' => 'billing-provider',
            'signing_secret' => 'secret-value',
        ]);

        $payload = json_encode(['event' => 'invoice.paid', 'id' => 'evt_123']);
        $timestamp = now()->timestamp;

        $this->signedPost($source, $payload, $timestamp, 'evt_123')->assertAccepted();
        $response = $this->signedPost($source, $payload, $timestamp, 'evt_123');

        $response->assertOk()
            ->assertJson(['status' => 'duplicate']);

        $this->assertSame(1, WebhookEvent::query()->count());
    }

    public function test_it_can_replay_a_stored_webhook_event(): void
    {
        Queue::fake();

        $source = WebhookSource::query()->create([
            'name' => 'Billing Provider',
            'slug' => 'billing-provider',
            'signing_secret' => 'secret-value',
        ]);

        $event = WebhookEvent::query()->create([
            'webhook_source_id' => $source->id,
            'idempotency_key' => 'evt_123',
            'payload_hash' => hash('sha256', '{"event":"invoice.paid"}'),
            'signature_header' => 'sha256=test',
            'timestamp_header' => now()->timestamp,
            'status' => 'failed',
            'payload' => ['event' => 'invoice.paid'],
            'headers' => [],
            'received_at' => now(),
        ]);

        $event->deliveryAttempts()->create([
            'attempt_number' => 1,
            'status' => 'failed',
            'attempted_at' => now(),
        ]);

        $response = $this->post("/events/{$event->uuid}/replay");

        $response->assertRedirect();

        $this->assertDatabaseHas('webhook_events', [
            'id' => $event->id,
            'status' => 'queued',
        ]);

        $this->assertDatabaseHas('webhook_delivery_attempts', [
            'webhook_event_id' => $event->id,
            'attempt_number' => 2,
            'status' => 'pending',
        ]);

        Queue::assertPushed(ProcessWebhookEvent::class);
    }

    public function test_it_does_not_replay_rejected_webhook_events(): void
    {
        Queue::fake();

        $source = WebhookSource::query()->create([
            'name' => 'Billing Provider',
            'slug' => 'billing-provider',
            'signing_secret' => 'secret-value',
        ]);

        $event = WebhookEvent::query()->create([
            'webhook_source_id' => $source->id,
            'payload_hash' => hash('sha256', '{"event":"invoice.paid"}'),
            'signature_header' => 'sha256=invalid',
            'timestamp_header' => now()->timestamp,
            'status' => 'rejected',
            'rejection_reason' => 'invalid_signature',
            'payload' => ['event' => 'invoice.paid'],
            'headers' => [],
            'received_at' => now(),
        ]);

        $response = $this->post("/events/{$event->uuid}/replay");

        $response->assertRedirect();

        $this->assertSame('rejected', $event->fresh()->status);
        $this->assertSame(0, $event->deliveryAttempts()->count());

        Queue::assertNotPushed(ProcessWebhookEvent::class);
    }

    public function test_failed_delivery_is_marked_as_retrying_when_attempts_remain(): void
    {
        $this->travelTo(now());

        Http::fake([
            'https://consumer.test/*' => Http::response(['error' => 'temporary failure'], 500),
        ]);

        $source = WebhookSource::query()->create([
            'name' => 'Billing Provider',
            'slug' => 'billing-provider',
            'signing_secret' => 'secret-value',
            'target_url' => 'https://consumer.test/webhooks',
        ]);

        $event = $this->storedEventFor($source);
        $attempt = $event->deliveryAttempts()->create([
            'attempt_number' => 1,
            'status' => 'pending',
        ]);

        $job = new ProcessWebhookEvent($event, $attempt->id);
        $job->setJob($this->queueJobAttempting(1));

        $this->expectException(\RuntimeException::class);

        try {
            $job->handle();
        } finally {
            $event->refresh();
            $attempt->refresh();

            $this->assertSame('retrying', $event->status);
            $this->assertSame('failed', $attempt->status);
            $this->assertSame(500, $attempt->response_status);
            $this->assertSame(now()->addSeconds(60)->timestamp, $attempt->next_retry_at->timestamp);
        }
    }

    public function test_failed_delivery_is_marked_as_final_failure_after_last_attempt(): void
    {
        $this->travelTo(now());

        Http::fake([
            'https://consumer.test/*' => Http::response(['error' => 'still failing'], 500),
        ]);

        $source = WebhookSource::query()->create([
            'name' => 'Billing Provider',
            'slug' => 'billing-provider',
            'signing_secret' => 'secret-value',
            'target_url' => 'https://consumer.test/webhooks',
        ]);

        $event = $this->storedEventFor($source);
        $attempt = $event->deliveryAttempts()->create([
            'attempt_number' => 3,
            'status' => 'pending',
        ]);

        $job = new ProcessWebhookEvent($event, $attempt->id);
        $job->setJob($this->queueJobAttempting(3));

        $this->expectException(\RuntimeException::class);

        try {
            $job->handle();
        } finally {
            $event->refresh();
            $attempt->refresh();

            $this->assertSame('failed', $event->status);
            $this->assertSame('failed', $attempt->status);
            $this->assertSame(500, $attempt->response_status);
            $this->assertNull($attempt->next_retry_at);
        }
    }

    private function signedPost(WebhookSource $source, string $payload, int $timestamp, string $idempotencyKey)
    {
        $signature = 'sha256='.hash_hmac('sha256', $timestamp.'.'.$payload, $source->signing_secret);

        return $this->call('POST', "/webhooks/{$source->uuid}", [], [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_X_HOOKRELAY_TIMESTAMP' => $timestamp,
            'HTTP_X_HOOKRELAY_SIGNATURE' => $signature,
            'HTTP_X_HOOKRELAY_IDEMPOTENCY_KEY' => $idempotencyKey,
        ], $payload);
    }

    private function storedEventFor(WebhookSource $source): WebhookEvent
    {
        return WebhookEvent::query()->create([
            'webhook_source_id' => $source->id,
            'idempotency_key' => 'evt_123',
            'payload_hash' => hash('sha256', '{"event":"invoice.paid"}'),
            'signature_header' => 'sha256=test',
            'timestamp_header' => now()->timestamp,
            'status' => 'received',
            'payload' => ['event' => 'invoice.paid'],
            'headers' => [],
            'received_at' => now(),
        ]);
    }

    private function queueJobAttempting(int $attempts): QueueJob
    {
        $job = Mockery::mock(QueueJob::class);
        $job->shouldReceive('attempts')->andReturn($attempts);

        return $job;
    }
}
