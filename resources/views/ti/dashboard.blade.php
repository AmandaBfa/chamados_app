<x-layout title="Indicadores de TI">
    <a class="text-link" href="{{ route('ti.index') }}">Fila do TI</a>
    <h1 class="my-4">Indicadores de TI</h1>
    <nav aria-label="Áreas do painel" class="mb-4 flex flex-wrap gap-2">
        @foreach ($tabs as $key => $name)
            <a class="{{ $tab === $key ? 'primary' : 'secondary' }}" @if ($tab === $key) aria-current="page" @endif
                href="{{ route('ti.dashboard', array_replace($links, ['aba' => $key])) }}">{{ $name }}</a>
        @endforeach
    </nav>
    <form method="get" action="{{ route('ti.dashboard') }}" class="panel mb-4 space-y-3">
        <input type="hidden" name="aba" value="{{ $tab }}">

        <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
            <label>Início do período <input class="field" type="date" name="inicio" value="{{ old('inicio', $links['inicio']) }}" required></label>
            <label>Fim do período <input class="field" type="date" name="fim" value="{{ old('fim', $links['fim']) }}" required></label>
            @foreach ($choices as $field => [$label, $options])
                <label>{{ $label }}
                    <select class="field" name="{{ $field }}">
                        <option value="">Todas as opções</option>
                        @foreach ($options as $value => $name)
                            <option value="{{ $value }}" @selected((string) ($data[$field] ?? '') === (string) $value)>{{ $name }}</option>
                        @endforeach
                    </select>
                </label>
            @endforeach
            @if ($tab === 'recorrencias')
                <label>Agrupar por
                    <select class="field" name="agrupar">
                        @foreach ($dimensions as $field => [$label, $table])
                            <option value="{{ $field }}" @selected($dimension === $field)>{{ $label }}</option>
                        @endforeach
                    </select>
                </label>
            @endif
        </div>
        <button class="primary" type="submit">Aplicar filtros</button>
    </form>
    <p class="muted mb-4">Unidade, cidade e setor da abertura. Demais filtros usam a classificação e atribuição atuais. Abertos e vencidos retratam o momento da consulta.</p>
    @if ($counts !== [])
        <section class="panel mb-4 space-y-3" aria-label="Indicadores e listas">
            <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                @foreach ($counts as $key => $count)
                    <a class="secondary flex flex-col items-start" href="{{ route('ti.dashboard', $links + ['indicador' => $key]) }}#detalhamento">
                        <span>{{ $labels[$key] }}</span>
                        <strong class="text-xl">{{ $count }}</strong>
                    </a>
                @endforeach
            </div>
            @if ($tab === 'desempenho')
                <p>Taxa de reabertura · {{ $counts['reopen_within'] }}/{{ $counts['reopen_cohort'] }}
                    @if ($counts['reopen_cohort']) · {{ number_format(100 * $counts['reopen_within'] / $counts['reopen_cohort'], 1, ',', '.') }}%
                    @else · Sem chamados com janela completa @endif
                </p>
                <p class="muted">Primeira resolução no período e janela completa de {{ $windowDays }} dias corridos. Conta reaberturas validadas pelo TI.</p>
                @foreach ($times as $key => $summary)
                    <div>
                        <a class="text-link" href="{{ route('ti.dashboard', $links + ['indicador' => $key]) }}#detalhamento">{{ $labels[$key] }} · {{ $summary['total'] }} registros</a>
                        <ul>
                            @foreach ($summary['values'] as $percentile => $value)
                                <li>{{ $percentile === 50 ? 'Mediana' : 'P'.$percentile }} · {{ $value === null ? 'Sem dados' : number_format($value, 1, ',', '.').' min' }}</li>
                            @endforeach
                        </ul>
                    </div>
                @endforeach
                @foreach (['responses' => 'Cumprimento de resposta', 'solutions' => 'Cumprimento de solução'] as $key => $label)
                    <div>
                        <p>{{ $label }} · {{ $counts[$key.'_met'] }}/{{ $counts[$key] }}
                            @if ($counts[$key]) · {{ number_format(100 * $counts[$key.'_met'] / $counts[$key], 1, ',', '.') }}%
                            @else · Sem casos elegíveis @endif
                        </p>
                        @if ($counts[$key])
                            <meter class="w-full max-w-sm" min="0" max="{{ $counts[$key] }}" value="{{ $counts[$key.'_met'] }}" aria-label="{{ $label }}"></meter>
                        @endif
                    </div>
                @endforeach
            @endif
        </section>
    @endif
    @if ($groups)
        <section class="panel mb-4 space-y-3" aria-label="Frequências">
            <h2>Frequência nas entradas do período</h2>
            <p class="muted">A frequência de chamados por grupo não confirma uma causa comum.</p>
            <ul class="space-y-3">
                @forelse ($groups as $group)
                    <li>
                        <a class="text-link" href="{{ route('ti.dashboard', $links + ['indicador' => 'recurrence', 'grupo' => $group->id ?? 0]) }}#detalhamento">{{ $group->name ?? 'Não informado' }} · {{ $group->total }}</a>
                        <meter class="block w-full max-w-sm" min="0" max="{{ $groups->first()->total }}" value="{{ $group->total }}" aria-label="{{ $group->name ?? 'Não informado' }}"></meter>
                    </li>
                @empty
                    <li>Nenhuma entrada no período.</li>
                @endforelse
            </ul>
            {{ $groups->links() }}
        </section>
    @endif
    @if ($details)
        <section class="panel space-y-3" id="detalhamento">
            <h2>{{ $labels[$indicator] }} · {{ $details->total() }}</h2>
            <ul class="divide-y">
                @forelse ($details as $ticket)
                    <li class="py-2">
                        <a class="text-link" href="{{ route('ti.show', $ticket->id) }}">{{ $ticket->protocol }}</a>
                        · {{ $ticket->subject }} · {{ \App\Services\TicketOperations::STATES[$ticket->status] }}
                        @isset ($ticket->metric_at) · {{ \Carbon\CarbonImmutable::parse($ticket->metric_at)->setTimezone($zone)->format('d/m/Y H:i') }} @endisset
                        @isset ($ticket->minutes) · {{ number_format($ticket->minutes, 1, ',', '.') }} min corridos @endisset
                        @isset ($ticket->metric_cycle) · Ciclo {{ $ticket->metric_cycle }} @endisset
                        @isset ($ticket->cancellation_reason) <p>{{ $ticket->cancellation_reason }}</p> @endisset
                    </li>
                @empty
                    <li>Nenhum chamado nesta população.</li>
                @endforelse
            </ul>
            {{ $details->links() }}
        </section>
    @endif
</x-layout>
