<x-layout title="Meus chamados">
    <nav aria-label="Portal" class="mb-6 flex flex-wrap gap-4"><a class="text-link" href="{{ route('home') }}">Início</a><a class="primary" href="{{ route('portal.open') }}">Abrir chamado</a></nav>
    <h1 class="mb-6">Meus chamados</h1>
    <livewire:portal.my-tickets />
</x-layout>
