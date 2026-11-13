<x-layout title="Início">
    <section class="panel">
        <p class="eyebrow">Acesso confirmado</p>
        <h1 class="mt-2">Olá, {{ auth()->user()->name }}</h1>
        <p class="muted mt-4">Relate um problema e acompanhe seus chamados.</p>
        <nav aria-label="Opções da conta" class="mt-6 flex flex-wrap gap-4">
            <a class="primary" href="{{ route('portal.open') }}">Abrir chamado</a>
            <a class="secondary" href="{{ route('portal.index') }}">Meus chamados</a>
            @if(collect(\App\Services\TicketOperations::STAFF)->contains(fn ($role) => auth()->user()->hasRole($role)))
                <a class="secondary" href="{{ route('ti.index') }}">Fila do TI</a>
            @endif
            @can('catalogs.manage')<a class="primary" href="{{ route('admin.catalogs', 'units') }}">Cadastros e organização</a>@endcan
            @can('users.view')<a class="primary" href="{{ route('admin.users') }}">Administrar usuários</a>@endcan
            @if(auth()->user()->hasRole(\App\Enums\RoleName::Administrator) || auth()->user()->hasRole(\App\Enums\RoleName::Manager))
                <a class="secondary" href="{{ route('sla.index') }}">Calendário e políticas de SLA</a>
            @endif
            @if(auth()->user()->hasRole(\App\Enums\RoleName::Administrator) || auth()->user()->hasEnabledTwoFactorAuthentication())
                <a class="secondary" href="{{ route('security.show') }}">Segurança da conta</a>
            @endif
        </nav>
    </section>
</x-layout>
