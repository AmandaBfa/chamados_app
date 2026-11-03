<x-layout title="Cadastros e organização">
    <h1>Cadastros e organização</h1>
    <nav aria-label="Cadastros" class="my-6 flex flex-wrap gap-3">
        <a class="text-link" href="{{ route('home') }}">Início</a>
        @foreach(\App\Services\CatalogDefinition::all() as $key => $definition)
            <a class="text-link" href="{{ route('admin.catalogs', $key) }}" @if($kind === $key) aria-current="page" @endif>{{ $definition['label'] }}</a>
        @endforeach
    </nav>
    <livewire:admin.catalog-panel :kind="$kind" :key="$kind" />
</x-layout>
