<?php

namespace Tests\Feature;

use App\Models\WebhookEvent;
use App\Models\WebhookSource;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WebhookSourceManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_lists_webhook_sources(): void
    {
        $source = WebhookSource::query()->create([
            'name' => 'Billing Provider',
            'slug' => 'billing-provider',
            'signing_secret' => 'secret-value',
        ]);

        $response = $this->get('/sources');

        $response->assertOk()
            ->assertSee('Billing Provider')
            ->assertSee("/webhooks/{$source->uuid}");
    }

    public function test_it_creates_a_webhook_source_with_generated_secret(): void
    {
        $response = $this->post('/sources', [
            'name' => 'CRM Automation',
            'slug' => 'crm-automation',
            'target_url' => 'https://consumer.test/webhooks',
            'is_active' => '1',
        ]);

        $source = WebhookSource::query()->where('slug', 'crm-automation')->first();

        $response->assertRedirect("/sources/{$source->uuid}");

        $this->assertNotNull($source);
        $this->assertStringStartsWith('whsec_', $source->signing_secret);
        $this->assertTrue($source->is_active);
        $this->assertSame('https://consumer.test/webhooks', $source->target_url);
    }

    public function test_it_toggles_webhook_source_status(): void
    {
        $source = WebhookSource::query()->create([
            'name' => 'Billing Provider',
            'slug' => 'billing-provider',
            'signing_secret' => 'secret-value',
            'is_active' => true,
        ]);

        $this->post("/sources/{$source->uuid}/toggle")->assertRedirect();

        $this->assertFalse($source->fresh()->is_active);

        $this->post("/sources/{$source->uuid}/toggle")->assertRedirect();

        $this->assertTrue($source->fresh()->is_active);
    }

    public function test_it_filters_events_by_source(): void
    {
        $billing = WebhookSource::query()->create([
            'name' => 'Billing Provider',
            'slug' => 'billing-provider',
            'signing_secret' => 'secret-value',
        ]);

        $crm = WebhookSource::query()->create([
            'name' => 'CRM Automation',
            'slug' => 'crm-automation',
            'signing_secret' => 'another-secret',
        ]);

        WebhookEvent::query()->create([
            'webhook_source_id' => $billing->id,
            'payload_hash' => hash('sha256', 'billing'),
            'status' => 'received',
            'payload' => ['event' => 'invoice.paid'],
            'headers' => [],
            'received_at' => now(),
        ]);

        WebhookEvent::query()->create([
            'webhook_source_id' => $crm->id,
            'payload_hash' => hash('sha256', 'crm'),
            'status' => 'received',
            'payload' => ['event' => 'lead.created'],
            'headers' => [],
            'received_at' => now(),
        ]);

        $response = $this->get("/events?source={$billing->uuid}");

        $response->assertOk()
            ->assertSee('Billing Provider')
            ->assertDontSee('CRM Automation');
    }
}
