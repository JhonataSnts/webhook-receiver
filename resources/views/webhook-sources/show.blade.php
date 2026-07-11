<x-layouts.app title="{{ $source->name }} - HookRelay">
    <div class="page-head">
        <div>
            <h1>{{ $source->name }}</h1>
            <p class="subtitle">{{ $source->slug }}</p>
        </div>
        <div style="display: flex; gap: 10px; flex-wrap: wrap;">
            <form method="POST" action="{{ route('sources.toggle', $source) }}">
                @csrf
                <button class="button" type="submit">{{ $source->is_active ? 'Desativar' : 'Ativar' }}</button>
            </form>
            <a class="button" href="{{ route('events.index', ['source' => $source->uuid]) }}">Eventos</a>
            <a class="button" href="{{ route('sources.index') }}">Voltar</a>
        </div>
    </div>

    <section class="stats">
        <div class="stat"><span>Status</span><strong>{{ $source->is_active ? 'active' : 'inactive' }}</strong></div>
        <div class="stat"><span>Eventos</span><strong>{{ $source->events_count }}</strong></div>
        <div class="stat"><span>Criada</span><strong>{{ $source->created_at->format('d/m') }}</strong></div>
        <div class="stat"><span>Target</span><strong>{{ $source->target_url ? 'set' : 'none' }}</strong></div>
        <div class="stat"><span>UUID</span><strong class="mono" style="font-size: 13px;">{{ $source->uuid }}</strong></div>
    </section>

    <div class="grid">
        <section class="panel section">
            <h2>Endpoint</h2>
            <pre>POST {{ url('/webhooks/'.$source->uuid) }}</pre>
        </section>

        <section class="panel section">
            <h2>Signing secret</h2>
            <pre>{{ $source->signing_secret }}</pre>
        </section>
    </div>

    <section class="panel table-wrap" style="margin-top: 16px;">
        <div class="toolbar">
            <strong>Eventos recentes</strong>
        </div>

        @if ($recentEvents->isEmpty())
            <div class="empty">Nenhum evento recebido para esta fonte.</div>
        @else
            <table>
                <thead>
                    <tr>
                        <th>Evento</th>
                        <th>Status</th>
                        <th>Idempotencia</th>
                        <th>Recebido</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($recentEvents as $event)
                        <tr>
                            <td><a class="mono" href="{{ route('events.show', $event) }}">{{ $event->uuid }}</a></td>
                            <td><span class="status {{ $event->status }}">{{ $event->status }}</span></td>
                            <td class="mono">{{ $event->idempotency_key ?: 'payload_hash' }}</td>
                            <td>{{ $event->received_at->format('d/m/Y H:i:s') }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif
    </section>
</x-layouts.app>
