<x-layout title="Recuperar senha">
    <section class="panel mx-auto max-w-md">
        <h1>Recuperar senha</h1>
        <p class="muted mt-3">Informe o e-mail autorizado. Se a conta estiver habilitada, enviaremos um link temporário.</p>
        <form action="{{ route('password.email') }}" method="post" class="mt-6 space-y-5">
            @csrf
            <div><label for="email">E-mail</label><input id="email" name="email" type="email" value="{{ old('email') }}" autocomplete="username" required></div>
            <button class="primary" type="submit">Solicitar recuperação</button>
        </form>
        <a class="text-link mt-5 inline-block" href="{{ route('login') }}">Voltar para entrar</a>
    </section>
</x-layout>
