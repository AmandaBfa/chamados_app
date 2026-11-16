<section class="space-y-4">
    @if(session('status'))<p role="status">{{ session('status') }}</p>@endif
    <h1 class="text-2xl font-semibold">Fila do TI</h1>
    <label class="block">Buscar por protocolo ou assunto <input class="field" type="search" wire:model.live.debounce.350ms="search"></label>
    <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
        @foreach (['status' => 'Situação', 'team_id' => 'Equipe', 'assignee_id' => 'Responsável', 'category_id' => 'Categoria', 'service_id' => 'Serviço', 'unit_id' => 'Unidade', 'priority' => 'Prioridade'] as $field => $label)
            <label>{{ $label }}
                <select class="field" wire:model.live="filters.{{ $field }}">
                    <option value="">Todas as opções</option>
                    @foreach ($choices[$field] as $value => $name)
                        <option value="{{ $value }}">{{ $name }}</option>
                    @endforeach
                </select>
            </label>
        @endforeach
    </div>
    <label class="inline-flex items-center gap-2"><input type="checkbox" wire:model.live="stale">
        Mostrar só P3/P4 abertos e parados
    </label>
    <p class="muted">A fila mostra P1 primeiro. Depois vêm os prazos vencidos, os menores saldos úteis e os chamados mais antigos.</p>
    <div class="overflow-x-auto" tabindex="0" role="region" aria-label="Tabela de chamados">
        <table class="w-full text-left">
            <thead><tr>
                @foreach (['Protocolo', 'Assunto', 'Situação', 'Equipe', 'Responsável', 'Categoria', 'Serviço', 'Unidade', 'Prioridade', 'Idade e movimentação', 'Saldo de SLA'] as $heading)
                    <th scope="col" class="p-2">{{ $heading }}</th>
                @endforeach
            </tr></thead>
            <tbody>
                @forelse ($tickets as $ticket)
                    <tr class="border-t">
                        <td class="p-2"><a class="text-link" href="{{ route('ti.show', $ticket->id) }}">{{ $ticket->protocol }}</a></td>
                        <td class="p-2">{{ $ticket->subject }}</td>
                        <td class="p-2"><x-ticket-status :status="$ticket->status" /></td>
                        @foreach (['team_id', 'assignee_id', 'category_id', 'service_id', 'unit_id'] as $field)
                            <td class="p-2">
                                {{ $choices[$field][$ticket->$field] ?? '—' }}
                                @if ($field === 'assignee_id' && $ticket->inactive_assignee && !in_array($ticket->status, ['resolvido', 'fechado', 'cancelado']))
                                    <strong>Redistribuir</strong>
                                @endif
                            </td>
                        @endforeach
                        <td class="p-2">{{ $ticket->priority ?? 'A validar' }}</td>
                        <td class="p-2">
                            {{ number_format($ticket->age / 3600, 1, ',', '.') }} h corridas
                            @if ($ticket->business !== null)
                                <br>{{ number_format($ticket->business / 3600, 1, ',', '.') }} h de cobertura
                                <br>{{ number_format($ticket->inactive / 3600, 1, ',', '.') }} h úteis sem movimento
                            @endif
                            <br><time datetime="{{ $ticket->movement }}">{{ \Carbon\CarbonImmutable::parse($ticket->movement)->setTimezone(config('installation.display_timezone'))->format('d/m/Y H:i') }}</time>
                        </td>
                        <td class="p-2">
                            @if ($ticket->response_overdue || $ticket->solution_overdue)<strong>Vencido</strong><br>@endif
                            {{ $ticket->balance === null ? '—' : number_format($ticket->balance / 60, 1, ',', '.').' min úteis' }}
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="11" class="p-2">Nenhum chamado encontrado.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    {{ $tickets->links() }}
</section>
