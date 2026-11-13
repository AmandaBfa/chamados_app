<x-layout title="Calendário e políticas de SLA">
    <a class="text-link" href="{{ route('home') }}">Início</a>
    <h1>Calendário e políticas de SLA</h1>
    <p>Configuração de desenvolvimento e piloto. A versão vigente é a {{ $current->id }}.</p>
    @if(session('status'))<p role="status">{{ session('status') }}</p>@endif
    <x-portal-errors />

    @if(auth()->user()->hasRole(\App\Enums\RoleName::Administrator))
        <form method="post" action="{{ route('sla.store') }}" class="panel space-y-3">
            @csrf
            <input type="hidden" name="base_version_id" value="{{ old('base_version_id', $current->id) }}">
            <h2>Propor versão</h2>
            <label class="block">Fuso do calendário <input class="field" name="timezone" value="{{ old('timezone', $current->timezone) }}" required></label>
            <label class="block">Janelas semanais em JSON
                <textarea class="field" name="weekly_windows" rows="5" required>{{ old('weekly_windows', $current->weekly_windows) }}</textarea>
            </label>
            <p>Use chaves de 1 (segunda) a 7 (domingo). Exemplo para segunda: <code>{"1":[["08:00","17:00"]]}</code>.</p>
            <label class="block">Feriados e exceções em JSON
                <textarea class="field" name="exceptions" rows="4" required>{{ old('exceptions', $current->exceptions) }}</textarea>
            </label>
            <p>Use a data local e suas janelas. Uma lista vazia representa dia sem cobertura. Exemplo: <code>{"2026-12-25":[]}</code>.</p>
            @php($targets = json_decode($current->targets, true))
            @foreach (['P1', 'P2', 'P3', 'P4'] as $priority)
                <fieldset class="grid gap-2 sm:grid-cols-2">
                    <legend>{{ $priority }}</legend>
                    @foreach (['Primeira resposta', 'Solução'] as $index => $label)
                        <label>{{ $label }} (minutos úteis)
                            <input type="number" min="1" max="525600" name="targets[{{ $priority }}][{{ $index }}]" class="field"
                                value="{{ old('targets.'.$priority.'.'.$index, $targets[$priority][$index]) }}" required>
                        </label>
                    @endforeach
                </fieldset>
            @endforeach
            <label class="block">Justificativa <textarea name="reason" class="field" minlength="8" maxlength="2000" required>{{ old('reason') }}</textarea></label>
            <button class="primary">Enviar para validação</button>
        </form>
    @endif

    <section class="space-y-3">
        <h2>Versões</h2>
        @foreach($versions as $version)
            <details class="panel space-y-2">
                <summary>Versão {{ $version->id }} · {{ $version->effective_at ? 'Validada' : 'Pendente' }}</summary>
                <p>{{ $version->reason }}</p>
                <p>Fuso {{ $version->timezone }} · Referência {{ $version->base_version_id ?? 'inicial' }}</p>
                <p>Configuração por {{ $version->configurator_name ?? 'Sistema' }} · Validação por {{ $version->approver_name ?? ($version->effective_at ? 'Sistema' : 'Pendente') }}</p>
                @if($version->effective_at)
                    <p>Vigente a partir de {{ \Carbon\CarbonImmutable::parse($version->effective_at)->setTimezone(config('installation.display_timezone'))->format('d/m/Y H:i:s') }}</p>
                @endif
                <p>Janelas <code class="break-all">{{ $version->weekly_windows }}</code></p>
                <p>Exceções <code class="break-all">{{ $version->exceptions }}</code></p>
                <p>Metas em minutos úteis (resposta, solução) <code class="break-all">{{ $version->targets }}</code></p>
                @if(!$version->effective_at && (int) $version->base_version_id === (int) $current->id && auth()->user()->hasRole(\App\Enums\RoleName::Manager))
                    <form method="post" action="{{ route('sla.approve', $version->id) }}">
                        @csrf
                        <button class="primary">Validar e ativar versão {{ $version->id }}</button>
                    </form>
                @endif
            </details>
        @endforeach
        {{ $versions->links() }}
    </section>
</x-layout>
