<?php

namespace Tests\Feature;

use App\Models\WebhookSource;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class SendDemoWebhookCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_sends_an_accepted_demo_webhook(): void
    {
        Http::fake([
            'https://hookrelay.test/*' => Http::response(['status' => 'accepted'], 202),
        ]);

        $source = $this->demoSource();

        $this->artisan('hookrelay:send-demo accepted --url=https://hookrelay.test')
            ->assertSuccessful();

        Http::assertSent(function ($request) use ($source) {
            return $request->url() === "https://hookrelay.test/webhooks/{$source->uuid}"
                && $request->hasHeader('X-HookRelay-Timestamp')
                && $request->hasHeader('X-HookRelay-Signature')
                && $request->hasHeader('X-HookRelay-Idempotency-Key')
                && str_starts_with($request->header('X-HookRelay-Signature')[0], 'sha256=');
        });
    }

    public function test_it_sends_duplicate_demo_webhook_twice_with_same_idempotency_key(): void
    {
        Http::fake([
            'https://hookrelay.test/*' => Http::sequence()
                ->push(['status' => 'accepted'], 202)
                ->push(['status' => 'duplicate'], 200),
        ]);

        $this->demoSource();

        $this->artisan('hookrelay:send-demo duplicate --url=https://hookrelay.test')
            ->assertSuccessful();

        Http::assertSentCount(2);

        $idempotencyKeys = [];

        Http::assertSent(function ($request) use (&$idempotencyKeys) {
            $idempotencyKeys[] = $request->header('X-HookRelay-Idempotency-Key')[0];

            return true;
        });

        $this->assertSame(['demo-duplicate-event', 'demo-duplicate-event'], $idempotencyKeys);
    }

    public function test_it_sends_rejected_demo_webhook_with_invalid_signature(): void
    {
        Http::fake([
            'https://hookrelay.test/*' => Http::response(['status' => 'rejected'], 401),
        ]);

        $this->demoSource();

        $this->artisan('hookrelay:send-demo rejected --url=https://hookrelay.test')
            ->assertSuccessful();

        Http::assertSent(function ($request) {
            return $request->header('X-HookRelay-Signature')[0] === 'sha256=invalid-demo-signature';
        });
    }

    private function demoSource(): WebhookSource
    {
        return WebhookSource::query()->create([
            'name' => 'Demo Source',
            'slug' => 'demo-source',
            'signing_secret' => 'hookrelay-demo-secret',
            'is_active' => true,
        ]);
    }
}
