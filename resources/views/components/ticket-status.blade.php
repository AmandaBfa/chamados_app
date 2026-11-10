@props(['status'])

@php
    [$icon, $colors] = match ($status) {
        'novo' => ['○', 'bg-slate-100 text-slate-800 dark:bg-slate-800 dark:text-slate-100'],
        'em_triagem' => ['◇', 'bg-indigo-100 text-indigo-900 dark:bg-indigo-950 dark:text-indigo-100'],
        'em_atendimento' => ['▶', 'bg-sky-100 text-sky-900 dark:bg-sky-950 dark:text-sky-100'],
        'aguardando_solicitante', 'aguardando_terceiro' => ['Ⅱ', 'bg-amber-100 text-amber-900 dark:bg-amber-950 dark:text-amber-100'],
        'resolvido', 'fechado' => [$status === 'resolvido' ? '✓' : '■', 'bg-emerald-100 text-emerald-900 dark:bg-emerald-950 dark:text-emerald-100'],
        'cancelado' => ['×', 'bg-red-100 text-red-900 dark:bg-red-950 dark:text-red-100'],
    };
@endphp

<span class="inline-flex items-center gap-1.5 rounded-full px-2.5 py-1 text-sm font-semibold {{ $colors }}">
    <span aria-hidden="true">{{ $icon }}</span>
    {{ \App\Services\TicketOperations::STATES[$status] }}
</span>
