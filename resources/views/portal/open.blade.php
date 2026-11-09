<x-layout title="Abrir chamado">
    <nav aria-label="Portal" class="mb-6 flex flex-wrap gap-4"><a class="text-link" href="{{ route('home') }}">Início</a><a class="text-link" href="{{ route('portal.index') }}">Meus chamados</a></nav>
    <h1 class="mb-6">Abrir chamado</h1>
    <livewire:portal.open-ticket />
</x-layout>
