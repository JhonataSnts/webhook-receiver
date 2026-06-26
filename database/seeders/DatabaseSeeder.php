<?php

namespace Database\Seeders;

use App\Models\WebhookSource;
// use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        WebhookSource::query()->updateOrCreate([
            'slug' => 'demo-source',
        ], [
            'uuid' => '11111111-1111-4111-8111-111111111111',
            'name' => 'Demo Source',
            'signing_secret' => 'hookrelay-demo-secret',
            'target_url' => null,
            'is_active' => true,
        ]);
    }
}
