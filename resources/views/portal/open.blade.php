<x-layout title="Abrir chamado">
    <nav aria-label="Portal" class="mb-6 flex flex-wrap gap-4"><a class="text-link" href="{{ route('home') }}">Início</a><a class="text-link" href="{{ route('portal.index') }}">Meus chamados</a></nav>
    <h1 class="mb-6">Abrir chamado</h1>
    @if($related)<p class="mb-4">O novo problema ficará vinculado ao <a class="text-link" href="{{ route('portal.show', $related) }}">chamado anterior</a>.</p>@endif
    <livewire:portal.open-ticket :related="$related" />
</x-layout>
