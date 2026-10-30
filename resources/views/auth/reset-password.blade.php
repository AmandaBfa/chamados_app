<x-layout title="Definir nova senha">
    <section class="panel mx-auto max-w-md">
        <h1>Definir nova senha</h1>
        <p class="muted mt-3">Use ao menos 12 caracteres, com letras maiúsculas, minúsculas e números.</p>
        <form action="{{ route('password.update') }}" method="post" class="mt-6 space-y-5">
            @csrf
            <input type="hidden" name="token" value="{{ $request->route('token') }}">
            <div><label for="email">E-mail</label><input id="email" name="email" type="email" value="{{ old('email', $request->query('email')) }}" autocomplete="username" required></div>
            <div><label for="password">Nova senha</label><input id="password" name="password" type="password" autocomplete="new-password" minlength="12" required></div>
            <div><label for="password_confirmation">Repita a nova senha</label><input id="password_confirmation" name="password_confirmation" type="password" autocomplete="new-password" required></div>
            <button class="primary" type="submit">Salvar nova senha</button>
        </form>
    </section>
</x-layout>
