<x-layout title="Início">
    <section class="panel">
        <p class="eyebrow">Acesso confirmado</p>
        <h1 class="mt-2">Olá, {{ auth()->user()->name }}</h1>
        <p class="muted mt-4">Sua conta está autenticada. A abertura e o atendimento de chamados serão disponibilizados nas próximas etapas.</p>
        <nav aria-label="Opções da conta" class="mt-6 flex flex-wrap gap-4">
            @can('users.view')<a class="primary" href="{{ route('admin.users') }}">Administrar usuários</a>@endcan
            @if(auth()->user()->hasRole(\App\Enums\RoleName::Administrator) || auth()->user()->hasEnabledTwoFactorAuthentication())
                <a class="secondary" href="{{ route('security.show') }}">Segurança da conta</a>
            @endif
        </nav>
    </section>
</x-layout>
