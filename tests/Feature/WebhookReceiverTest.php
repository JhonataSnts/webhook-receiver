<?php

namespace Tests\Feature;

use App\Jobs\ProcessWebhookEvent;
use App\Models\WebhookEvent;
use App\Models\WebhookSource;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
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
}
