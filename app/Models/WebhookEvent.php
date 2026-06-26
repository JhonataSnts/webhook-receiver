<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class WebhookEvent extends Model
{
    use HasFactory;

    protected $fillable = [
        'uuid',
        'webhook_source_id',
        'idempotency_key',
        'payload_hash',
        'signature_header',
        'timestamp_header',
        'status',
        'rejection_reason',
        'payload',
        'headers',
        'ip_address',
        'user_agent',
        'received_at',
        'processed_at',
    ];

    protected $casts = [
        'payload' => 'array',
        'headers' => 'array',
        'received_at' => 'datetime',
        'processed_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::creating(function (WebhookEvent $event) {
            $event->uuid ??= (string) Str::uuid();
        });
    }

    public function source(): BelongsTo
    {
        return $this->belongsTo(WebhookSource::class, 'webhook_source_id');
    }

    public function deliveryAttempts(): HasMany
    {
        return $this->hasMany(WebhookDeliveryAttempt::class);
    }
}
