<?php

namespace App\Http\Controllers;

use App\Models\WebhookSource;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class WebhookSourceController extends Controller
{
    public function index(): View
    {
        $sources = WebhookSource::query()
            ->withCount('events')
            ->latest()
            ->paginate(15);

        return view('webhook-sources.index', compact('sources'));
    }

    public function create(): View
    {
        return view('webhook-sources.create');
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'slug' => ['required', 'string', 'max:255', 'alpha_dash', Rule::unique('webhook_sources', 'slug')],
            'target_url' => ['nullable', 'url', 'max:2048'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $source = WebhookSource::query()->create([
            ...$validated,
            'is_active' => $request->boolean('is_active', true),
            'signing_secret' => 'whsec_'.Str::random(48),
        ]);

        return redirect()
            ->route('sources.show', $source)
            ->with('status', 'Fonte criada com sucesso.');
    }

    public function show(WebhookSource $source): View
    {
        $source->loadCount('events');

        $recentEvents = $source->events()
            ->latest('received_at')
            ->limit(10)
            ->get();

        return view('webhook-sources.show', compact('source', 'recentEvents'));
    }

    public function toggle(WebhookSource $source): RedirectResponse
    {
        $source->update(['is_active' => ! $source->is_active]);

        return back()->with('status', $source->is_active ? 'Fonte ativada.' : 'Fonte desativada.');
    }
}
