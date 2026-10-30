<x-layout title="Confirmar sua identidade">
    <section class="panel mx-auto max-w-md">
        <h1>Confirme sua senha</h1>
        <p class="muted mt-3">Esta ação altera dados de acesso ou configurações de segurança.</p>
        <form action="{{ route('password.confirm.store') }}" method="post" class="mt-6 space-y-5">
            @csrf
            <div><label for="password">Senha atual</label><input id="password" name="password" type="password" autocomplete="current-password" required autofocus></div>
            <button class="primary" type="submit">Confirmar e continuar</button>
        </form>
    </section>
</x-layout>
