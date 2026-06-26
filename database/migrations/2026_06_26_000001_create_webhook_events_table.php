<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('webhook_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('webhook_source_id')->constrained()->cascadeOnDelete();
            $table->uuid('uuid')->unique();
            $table->string('idempotency_key')->nullable();
            $table->string('payload_hash', 64);
            $table->string('signature_header')->nullable();
            $table->integer('timestamp_header')->nullable();
            $table->string('status')->default('received');
            $table->string('rejection_reason')->nullable();
            $table->json('payload')->nullable();
            $table->json('headers');
            $table->string('ip_address')->nullable();
            $table->string('user_agent')->nullable();
            $table->timestamp('received_at');
            $table->timestamp('processed_at')->nullable();
            $table->timestamps();

            $table->unique(['webhook_source_id', 'idempotency_key']);
            $table->index(['webhook_source_id', 'payload_hash']);
            $table->index(['status', 'received_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('webhook_events');
    }
};
