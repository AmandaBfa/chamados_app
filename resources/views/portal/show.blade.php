<x-layout title="Detalhe do chamado">
    <nav aria-label="Portal" class="mb-6 flex flex-wrap gap-4"><a class="text-link" href="{{ route('home') }}">Início</a><a class="text-link" href="{{ route('portal.index') }}">Meus chamados</a><a class="text-link" href="{{ route('portal.open') }}">Abrir chamado</a></nav>
    <livewire:portal.ticket-detail :ticket-id="$ticket" />
</x-layout>
