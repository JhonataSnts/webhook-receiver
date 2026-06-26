<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WebhookDeliveryAttempt extends Model
{
    use HasFactory;

    protected $fillable = [
        'webhook_event_id',
        'attempt_number',
        'status',
        'response_status',
        'response_body',
        'error_message',
        'attempted_at',
        'next_retry_at',
    ];

    protected $casts = [
        'attempted_at' => 'datetime',
        'next_retry_at' => 'datetime',
    ];

    public function event(): BelongsTo
    {
        return $this->belongsTo(WebhookEvent::class, 'webhook_event_id');
    }
}
