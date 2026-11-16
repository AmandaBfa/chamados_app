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
            <noscript><p>Ative o JavaScript para editar o calendário.</p></noscript>
            <section data-sla-calendar hidden class="space-y-4">
                <input type="hidden" data-weekly name="weekly_windows" value="{{ old('weekly_windows', $current->weekly_windows) }}">
                <input type="hidden" data-dates name="exceptions" value="{{ old('exceptions', $current->exceptions) }}">
                <input type="hidden" data-notes name="exception_notes" value="{{ old('exception_notes', $current->exception_notes ?? '{}') }}">
                <input type="hidden" data-holidays value="{{ json_encode($holidays, JSON_THROW_ON_ERROR) }}">
                <details>
                    <summary>Horário semanal de atendimento</summary>
                    <p class="muted">Use HH:MM. Para encerrar à meia-noite, informe 24:00.</p>
                    <div data-weekly-grid class="space-y-2 pt-2"></div>
                </details>
                <h3 class="font-semibold">Feriados e exceções</h3>
                <div class="flex flex-wrap items-end gap-2">
                    <label>Ano da base de feriados
                        <select data-year>
                            @foreach (array_keys(config('sla_holidays.movable')) as $year)
                                <option @selected($today->year === $year)>{{ $year }}</option>
                            @endforeach
                        </select>
                    </label>
                    <button type="button" data-import class="secondary">Adicionar feriados de Goiânia (GO)</button>
                </div>
                <p class="muted">Revise as datas antes de enviar. A importação preserva as exceções já preenchidas.</p>
                <div class="grid gap-4 md:grid-cols-2">
                    <div>
                        <label>Mês do calendário <input type="month" data-month value="{{ $today->format('Y-m') }}"></label>
                        <p class="muted my-2 text-sm">◦ Feriado sugerido · • Exceção na proposta</p>
                        <div data-days class="grid grid-cols-7 gap-1" role="group" aria-label="Dias do mês"></div>
                    </div>
                    <div class="space-y-2">
                        <label>Data da exceção <input type="date" data-date value="{{ $today->toDateString() }}"></label>
                        <label>Cobertura na data
                            <select data-type>
                                <option value="normal">Horário semanal</option>
                                <option value="closed">Sem atendimento</option>
                                <option value="special">Horário especial</option>
                            </select>
                        </label>
                        <div data-hours class="space-y-2" hidden></div>
                        <label>Motivo ou observação <input type="text" data-note maxlength="160"></label>
                        <button type="button" data-save class="secondary">Aplicar edição da data</button>
                    </div>
                </div>
                <p data-status role="status" class="muted"></p>
            </section>
            <?php $targets = json_decode($current->targets, true); ?>
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
            <button type="submit" class="primary" disabled>Enviar para validação</button>
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
                @php
                    $week = json_decode($version->weekly_windows, true);
                    $notes = json_decode($version->exception_notes ?? '{}', true);
                    $formatWindows = fn ($windows) => $windows ? implode(', ', array_map(fn ($window) => implode('–', $window), $windows)) : 'Sem atendimento';
                @endphp
                <h3 class="font-semibold">Horário semanal</h3>
                <ul>
                    @foreach (['Segunda', 'Terça', 'Quarta', 'Quinta', 'Sexta', 'Sábado', 'Domingo'] as $index => $day)
                        <li>{{ $day }} · {{ $formatWindows($week[$index + 1] ?? []) }}</li>
                    @endforeach
                </ul>
                <h3 class="font-semibold">Feriados e exceções</h3>
                <ul>
                    @forelse (json_decode($version->exceptions, true) as $day => $windows)
                        <li>{{ \Carbon\CarbonImmutable::parse($day)->format('d/m/Y') }} · {{ $formatWindows($windows) }}
                            @if (!empty($notes[$day])) · {{ $notes[$day] }} @endif
                        </li>
                    @empty
                        <li>Nenhuma data cadastrada.</li>
                    @endforelse
                </ul>
                <h3 class="font-semibold">Metas em minutos úteis</h3>
                <ul>
                    @foreach (json_decode($version->targets, true) as $priority => [$response, $solution])
                        <li>{{ $priority }} · resposta {{ $response }} min · solução {{ $solution }} min</li>
                    @endforeach
                </ul>
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
