<?php

namespace App\Http\Controllers;

use App\Models\WebhookEvent;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

class WebhookEventController extends Controller
{
    public function index(Request $request): View
    {
        $status = $request->query('status');

        $events = WebhookEvent::query()
            ->with('source')
            ->when($status, fn ($query) => $query->where('status', $status))
            ->latest('received_at')
            ->paginate(15)
            ->withQueryString();

        $stats = [
            'total' => WebhookEvent::query()->count(),
            'received_today' => WebhookEvent::query()->whereDate('received_at', today())->count(),
            'processed' => WebhookEvent::query()->where('status', 'processed')->count(),
            'failed' => WebhookEvent::query()->where('status', 'failed')->count(),
            'rejected' => WebhookEvent::query()->where('status', 'rejected')->count(),
        ];

        return view('webhook-events.index', compact('events', 'stats', 'status'));
    }

    public function show(WebhookEvent $event): View
    {
        $event->load('source', 'deliveryAttempts');

        return view('webhook-events.show', compact('event'));
    }
}
