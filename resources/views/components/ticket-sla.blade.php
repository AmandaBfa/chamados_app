@props(['sla', 'version'])

@php
    $minutes = fn ($seconds) => number_format($seconds / 60, 1, ',', '.').' min';
    $date = fn ($instant) => $instant->setTimezone(config('installation.display_timezone'))->format('d/m/Y H:i:s');
@endphp

<section class="panel space-y-3" aria-labelledby="ticket-sla-title">
    <h2 id="ticket-sla-title">SLA · versão {{ $version }}</h2>
    <p>Idade total {{ $minutes($sla['age_seconds']) }} corridos · Tempo útil pausado na solução {{ $minutes($sla['paused_seconds']) }}</p>
    <div class="grid gap-3 sm:grid-cols-2">
        @foreach(['response' => 'Primeira resposta original', 'solution' => 'Solução'] as $key => $label)
            @php
                $clock = $sla['clocks'][$key];
                $cycle = $key === 'response' ? 1 : $sla['cycle'];
                $breach = $sla['breaches'][$cycle][$key] ?? null;
            @endphp
            <section class="rounded border p-3">
                <h3 class="font-semibold">{{ $label }}</h3>
                <p>Tempo útil consumido {{ $minutes($clock['used']) }}</p>
                @if($clock['remaining'] === null)
                    <p>Aguardando validação de prioridade</p>
                @else
                    <p>Saldo {{ $minutes($clock['remaining']) }}</p>
                    @if($clock['due'])<p>Vencimento {{ $date($clock['due']) }}</p>@endif
                @endif
                @if($key === 'solution' && $sla['paused'])<p>Pausado enquanto aguarda o solicitante</p>@endif
                @if($clock['end'])<p>Encerrado · {{ $clock['end'] }}</p>@endif
                @if($breach)<p class="font-semibold">Violação em {{ $date($breach) }}</p>@endif
            </section>
        @endforeach
    </div>
</section>
