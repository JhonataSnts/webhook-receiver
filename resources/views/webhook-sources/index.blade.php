<x-layouts.app title="Fontes - HookRelay">
    <div class="page-head">
        <div>
            <h1>Fontes de webhook</h1>
            <p class="subtitle">Cadastre origens externas, gere endpoints e controle quais fontes podem enviar eventos.</p>
        </div>
        <a class="button primary" href="{{ route('sources.create') }}">Nova fonte</a>
    </div>

    <section class="panel table-wrap">
        @if ($sources->isEmpty())
            <div class="empty">Nenhuma fonte cadastrada ainda.</div>
        @else
            <table>
                <thead>
                    <tr>
                        <th>Fonte</th>
                        <th>Endpoint</th>
                        <th>Status</th>
                        <th>Eventos</th>
                        <th>Criada em</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($sources as $source)
                        <tr>
                            <td>
                                <a href="{{ route('sources.show', $source) }}">{{ $source->name }}</a>
                                <div class="muted">{{ $source->slug }}</div>
                            </td>
                            <td class="mono">/webhooks/{{ $source->uuid }}</td>
                            <td>
                                <span class="status {{ $source->is_active ? 'processed' : 'failed' }}">
                                    {{ $source->is_active ? 'active' : 'inactive' }}
                                </span>
                            </td>
                            <td>{{ $source->events_count }}</td>
                            <td>{{ $source->created_at->format('d/m/Y H:i') }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>

            <div class="pagination">{{ $sources->links() }}</div>
        @endif
    </section>
</x-layouts.app>
