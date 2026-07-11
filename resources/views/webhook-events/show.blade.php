<x-layouts.app title="Evento {{ $event->uuid }} - HookRelay">
    <div class="page-head">
        <div>
            <h1>Evento recebido</h1>
            <p class="subtitle mono">{{ $event->uuid }}</p>
        </div>
        <div style="display: flex; gap: 10px; flex-wrap: wrap;">
            @if ($event->status !== 'rejected')
                <form method="POST" action="{{ route('events.replay', $event) }}">
                    @csrf
                    <button class="button" type="submit">Replay</button>
                </form>
            @endif
            <a class="button" href="{{ route('events.index') }}">Voltar</a>
        </div>
    </div>

    <section class="stats">
        <div class="stat"><span>Fonte</span><strong>{{ $event->source->name }}</strong></div>
        <div class="stat"><span>Status</span><strong>{{ $event->status }}</strong></div>
        <div class="stat"><span>Tentativas</span><strong>{{ $event->deliveryAttempts->count() }}</strong></div>
        <div class="stat"><span>IP</span><strong>{{ $event->ip_address ?: '-' }}</strong></div>
        <div class="stat"><span>Recebido</span><strong>{{ $event->received_at->format('H:i:s') }}</strong></div>
    </section>

    <div class="grid">
        <section class="panel section">
            <h2>Payload</h2>
            <pre>{{ json_encode($event->payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) }}</pre>
        </section>

        <section class="panel section">
            <h2>Headers</h2>
            <pre>{{ json_encode($event->headers, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) }}</pre>
        </section>
    </div>

    <section class="panel table-wrap" style="margin-top: 16px;">
        <div class="toolbar">
            <strong>Tentativas de processamento</strong>
        </div>
        @if ($event->deliveryAttempts->isEmpty())
            <div class="empty">Nenhuma tentativa registrada.</div>
        @else
            <table>
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Status</th>
                        <th>HTTP</th>
                        <th>Erro</th>
                        <th>Tentado em</th>
                        <th>Proximo retry</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($event->deliveryAttempts as $attempt)
                        <tr>
                            <td>{{ $attempt->attempt_number }}</td>
                            <td><span class="status {{ $attempt->status }}">{{ $attempt->status }}</span></td>
                            <td>{{ $attempt->response_status ?: '-' }}</td>
                            <td>{{ $attempt->error_message ?: '-' }}</td>
                            <td>{{ $attempt->attempted_at?->format('d/m/Y H:i:s') ?: '-' }}</td>
                            <td>{{ $attempt->next_retry_at?->format('d/m/Y H:i:s') ?: '-' }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif
    </section>
</x-layouts.app>
