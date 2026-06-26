<?php

namespace App\Http\Controllers;

use App\Jobs\ProcessWebhookEvent;
use App\Models\WebhookEvent;
use App\Models\WebhookSource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class WebhookReceiverController extends Controller
{
    private const TIMESTAMP_TOLERANCE_SECONDS = 300;

    public function store(Request $request, string $sourceUuid): JsonResponse
    {
        $source = WebhookSource::query()
            ->where('uuid', $sourceUuid)
            ->where('is_active', true)
            ->firstOrFail();

        $rawPayload = $request->getContent();
        $payloadHash = hash('sha256', $rawPayload);
        $idempotencyKey = $request->header('X-HookRelay-Idempotency-Key');
        $signature = $request->header('X-HookRelay-Signature');
        $timestamp = $request->header('X-HookRelay-Timestamp')
            ? (int) $request->header('X-HookRelay-Timestamp')
            : null;
        $headers = collect($request->headers->all())
            ->map(fn (array $values) => implode(', ', $values))
            ->all();

        $rejectionReason = $this->rejectionReason($source, $rawPayload, $signature, $timestamp);
        $payload = $this->decodePayload($rawPayload);

        if (! $rejectionReason) {
            $duplicate = $this->findDuplicate($source, $idempotencyKey, $payloadHash);

            if ($duplicate) {
                return response()->json([
                    'status' => 'duplicate',
                    'event_id' => $duplicate->uuid,
                ], 200);
            }
        }

        $event = DB::transaction(function () use (
            $source,
            $idempotencyKey,
            $payloadHash,
            $signature,
            $timestamp,
            $headers,
            $request,
            $payload,
            $rejectionReason,
        ) {
            $event = WebhookEvent::query()->create([
                'webhook_source_id' => $source->id,
                'idempotency_key' => $idempotencyKey,
                'payload_hash' => $payloadHash,
                'signature_header' => $signature,
                'timestamp_header' => $timestamp,
                'status' => $rejectionReason ? 'rejected' : 'received',
                'rejection_reason' => $rejectionReason,
                'payload' => $payload,
                'headers' => $headers,
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
                'received_at' => now(),
            ]);

            if (! $rejectionReason) {
                $event->deliveryAttempts()->create([
                    'attempt_number' => 1,
                    'status' => 'pending',
                ]);
            }

            return $event;
        });

        if ($rejectionReason) {
            return response()->json([
                'status' => 'rejected',
                'reason' => $rejectionReason,
                'event_id' => $event->uuid,
            ], 401);
        }

        ProcessWebhookEvent::dispatch($event);

        return response()->json([
            'status' => 'accepted',
            'event_id' => $event->uuid,
        ], 202);
    }

    private function findDuplicate(WebhookSource $source, ?string $idempotencyKey, string $payloadHash): ?WebhookEvent
    {
        return WebhookEvent::query()
            ->where('webhook_source_id', $source->id)
            ->where(function ($query) use ($idempotencyKey, $payloadHash) {
                if ($idempotencyKey) {
                    $query->where('idempotency_key', $idempotencyKey);

                    return;
                }

                $query->where('payload_hash', $payloadHash);
            })
            ->first();
    }

    private function rejectionReason(
        WebhookSource $source,
        string $rawPayload,
        ?string $signature,
        ?int $timestamp,
    ): ?string {
        if (! $timestamp) {
            return 'missing_timestamp';
        }

        if (abs(Carbon::createFromTimestamp($timestamp)->diffInSeconds(now(), false)) > self::TIMESTAMP_TOLERANCE_SECONDS) {
            return 'stale_timestamp';
        }

        if (! $signature) {
            return 'missing_signature';
        }

        $expected = 'sha256='.hash_hmac('sha256', $timestamp.'.'.$rawPayload, $source->signing_secret);

        if (! hash_equals($expected, $signature)) {
            return 'invalid_signature';
        }

        return null;
    }

    private function decodePayload(string $rawPayload): mixed
    {
        $payload = json_decode($rawPayload, true);

        if (json_last_error() === JSON_ERROR_NONE) {
            return $payload;
        }

        return ['_raw' => $rawPayload];
    }
}
