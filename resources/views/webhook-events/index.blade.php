<x-layouts.app title="Eventos - HookRelay">
    <div class="page-head">
        <div>
            <h1>Histórico de webhooks</h1>
            <p class="subtitle">Eventos recebidos, rejeitados, processados e falhos em um fluxo auditável.</p>
        </div>
        <span class="button mono">POST /webhooks/{{ '{source_uuid}' }}</span>
    </div>

    <section class="stats">
        <div class="stat"><span>Total</span><strong>{{ $stats['total'] }}</strong></div>
        <div class="stat"><span>Hoje</span><strong>{{ $stats['received_today'] }}</strong></div>
        <div class="stat"><span>Processados</span><strong>{{ $stats['processed'] }}</strong></div>
        <div class="stat"><span>Retrying</span><strong>{{ $stats['retrying'] }}</strong></div>
        <div class="stat"><span>Falhos</span><strong>{{ $stats['failed'] }}</strong></div>
    </section>

    <section class="panel table-wrap">
        <div class="toolbar">
            <div class="filters">
                <a class="chip {{ $status ? '' : 'active' }}" href="{{ route('events.index') }}">Todos</a>
                @foreach (['received', 'queued', 'processing', 'retrying', 'processed', 'failed', 'rejected'] as $option)
                    <a class="chip {{ $status === $option ? 'active' : '' }}" href="{{ route('events.index', array_filter(['status' => $option, 'source' => $sourceUuid])) }}">{{ ucfirst($option) }}</a>
                @endforeach
                @if ($sourceUuid)
                    <a class="chip active" href="{{ route('events.index') }}">Fonte filtrada</a>
                @endif
            </div>
        </div>

        @if ($events->isEmpty())
            <div class="empty">Nenhum evento recebido ainda.</div>
        @else
            <table>
                <thead>
                    <tr>
                        <th>Evento</th>
                        <th>Fonte</th>
                        <th>Status</th>
                        <th>Idempotência</th>
                        <th>Recebido</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($events as $event)
                        <tr>
                            <td>
                                <a class="mono" href="{{ route('events.show', $event) }}">{{ $event->uuid }}</a>
                                <div class="muted mono">{{ $event->payload_hash }}</div>
                            </td>
                            <td>
                                {{ $event->source->name }}
                                <div class="muted">{{ $event->source->slug }}</div>
                            </td>
                            <td>
                                <span class="status {{ $event->status }}">{{ $event->status }}</span>
                                @if ($event->rejection_reason)
                                    <div class="muted">{{ $event->rejection_reason }}</div>
                                @endif
                            </td>
                            <td class="mono">{{ $event->idempotency_key ?: 'payload_hash' }}</td>
                            <td>
                                {{ $event->received_at->format('d/m/Y H:i:s') }}
                                <div class="muted">{{ $event->received_at->diffForHumans() }}</div>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>

            <div class="pagination">{{ $events->links() }}</div>
        @endif
    </section>
</x-layouts.app>
