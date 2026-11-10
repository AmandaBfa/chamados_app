<x-layout title="Atendimento do TI">
    <a class="text-link" href="{{ route('ti.index') }}">Voltar à fila</a>
    <livewire:ti.detail :ticket-id="$ticket" />
</x-layout>
