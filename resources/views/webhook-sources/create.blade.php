<x-layouts.app title="Nova fonte - HookRelay">
    <div class="page-head">
        <div>
            <h1>Nova fonte</h1>
            <p class="subtitle">Crie uma origem de webhook com secret próprio e endpoint dedicado.</p>
        </div>
        <a class="button" href="{{ route('sources.index') }}">Voltar</a>
    </div>

    <section class="panel section">
        <form class="form-grid" method="POST" action="{{ route('sources.store') }}">
            @csrf

            <div class="field">
                <label for="name">Nome</label>
                <input id="name" name="name" value="{{ old('name') }}" required>
                @error('name')
                    <div class="field-error">{{ $message }}</div>
                @enderror
            </div>

            <div class="field">
                <label for="slug">Slug</label>
                <input id="slug" name="slug" value="{{ old('slug') }}" required>
                @error('slug')
                    <div class="field-error">{{ $message }}</div>
                @enderror
            </div>

            <div class="field">
                <label for="target_url">Target URL</label>
                <input id="target_url" name="target_url" value="{{ old('target_url') }}" placeholder="https://example.com/internal-webhook">
                @error('target_url')
                    <div class="field-error">{{ $message }}</div>
                @enderror
            </div>

            <label class="inline-field">
                <input type="checkbox" name="is_active" value="1" @checked(old('is_active', true))>
                <span>Ativa</span>
            </label>

            <div>
                <button class="button primary" type="submit">Criar fonte</button>
            </div>
        </form>
    </section>
</x-layouts.app>
