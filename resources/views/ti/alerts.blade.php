<x-layout title="Alertas do TI">
    <a class="text-link" href="{{ route('ti.index') }}">Fila do TI</a>
    <h1 class="my-4">Alertas do TI</h1>
    <section class="panel space-y-3" aria-label="Meus alertas">
        <h2>Meus alertas</h2>
        <ul class="space-y-2">
            @forelse ($alerts as $alert)
                <li><a class="text-link" href="{{ route('ti.show', $alert->ticket_id) }}">{{ $alert->protocol }}</a>
                    · {{ $labels[$alert->milestone] }} ({{ ['operation' => 'Operação', 'response' => 'Resposta', 'solution' => 'Solução'][$alert->clock] }}, ciclo {{ $alert->cycle }})
                    · <time datetime="{{ $alert->detected_at }}">{{ \Carbon\CarbonImmutable::parse($alert->detected_at)->setTimezone(config('installation.display_timezone'))->format('d/m/Y H:i') }}</time></li>
            @empty
                <li>Nenhum alerta recebido.</li>
            @endforelse
        </ul>
        {{ $alerts->withQueryString()->links() }}
    </section>
    @if ($issues !== null)
        <section class="panel mt-4 space-y-3">
            <h2>Ocorrências de entrega</h2>
            <ul class="space-y-2">
                @forelse ($issues as $alert)
                    <li><a class="text-link" href="{{ route('ti.show', $alert->ticket_id) }}">{{ $alert->protocol }}</a>
                        · {{ $labels[$alert->milestone] }} · {{ ['owner' => 'Responsável', 'triage' => 'Triagem', 'team' => 'Referência', 'specialist' => 'Especialista', 'manager' => 'Gerência'][$alert->audience] }}
                        · {{ $alert->status === 'missing' ? 'Sem destinatário elegível no momento da detecção' : 'Falha após '.$alert->attempts.' tentativas' }}</li>
                @empty
                    <li>Nenhuma ocorrência para os chamados visíveis.</li>
                @endforelse
            </ul>
            {{ $issues->withQueryString()->links() }}
        </section>
    @endif
</x-layout>
