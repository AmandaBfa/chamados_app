<x-layout title="Fila do TI">
    <a class="text-link" href="{{ route('home') }}">Início</a>
    <a class="text-link ml-4" href="{{ route('ti.alerts') }}">Alertas</a>
    <livewire:ti.queue />
</x-layout>
