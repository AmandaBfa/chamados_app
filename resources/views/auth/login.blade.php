<x-layout title="Entrar">
    <section class="panel mx-auto max-w-md" aria-labelledby="login-title">
        <p class="eyebrow">Acesso autorizado</p>
        <h1 id="login-title">Entre na sua conta</h1>
        <p class="muted mt-3">Use o e-mail cadastrado pela administração e sua senha.</p>
        <form action="{{ route('login.store') }}" method="post" class="mt-6 space-y-5">
            @csrf
            <div><label for="email">E-mail</label><input id="email" name="email" type="email" autocomplete="username" value="{{ old('email') }}" required autofocus></div>
            <div><label for="password">Senha</label><input id="password" name="password" type="password" autocomplete="current-password" required></div>
            <button class="primary w-full" type="submit">Entrar</button>
        </form>
        <a class="text-link mt-5 inline-block" href="{{ route('password.request') }}">Esqueci minha senha</a>
        <p class="muted mt-6 text-sm">Não há cadastro público. Se precisar de acesso, solicite um convite à administração.</p>
    </section>
</x-layout>
