<x-layout title="Aceitar convite">
    <section class="panel mx-auto max-w-md">
        <h1>Aceitar convite</h1>
        <p class="muted mt-3">Olá, {{ $invitedName }}. Defina sua senha para confirmar o e-mail e ativar o acesso autorizado.</p>
        <p class="muted mt-3 text-sm">Use ao menos 12 caracteres, letras maiúsculas, minúsculas e números.</p>
        <form action="{{ route('invitation.accept', ['token' => $token]) }}" method="post" class="mt-6 space-y-5">
            @csrf
            <div><label for="password">Senha</label><input id="password" name="password" type="password" autocomplete="new-password" minlength="12" required></div>
            <div><label for="password_confirmation">Repita a senha</label><input id="password_confirmation" name="password_confirmation" type="password" autocomplete="new-password" required></div>
            <button class="primary" type="submit">Aceitar e confirmar e-mail</button>
        </form>
    </section>
</x-layout>
