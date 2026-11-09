<section class="panel">
    <x-portal-errors />
    <div class="mb-6 grid gap-4 sm:grid-cols-2">
        <div><label for="my-search">Buscar por assunto ou protocolo</label><input id="my-search" wire:model.live.debounce.300ms="search" type="search" maxlength="160"></div>
        <div><label for="my-status">Situação</label><select id="my-status" wire:model.live="status"><option value="">Todas</option>@foreach($statuses as $value => $label)<option value="{{ $value }}">{{ $label }}</option>@endforeach</select></div>
    </div>
    <ul class="space-y-4">
        @forelse($tickets as $ticket)
            <li wire:key="ticket-{{ $ticket->id }}" class="rounded-xl border border-slate-300 p-4 dark:border-slate-600">
                <p class="muted break-all text-sm">{{ $ticket->protocol }} · {{ \Carbon\CarbonImmutable::parse($ticket->created_at)->tz(config('installation.display_timezone'))->format('d/m/Y H:i') }}</p>
                <h2 class="mt-2 break-words"><a class="text-link" href="{{ route('portal.show', $ticket->id) }}">{{ $ticket->subject }}</a></h2>
                <p class="mt-3">Situação: {{ $statuses[$ticket->status] }}</p>
            </li>
        @empty<li class="muted">Nenhum chamado encontrado. Você pode abrir um chamado para relatar um problema.</li>@endforelse
    </ul>
    <div class="mt-6">{{ $tickets->links() }}</div>
</section>
