<x-layout title="Segundo fator">
    <section class="panel mx-auto max-w-md">
        <h1>Confirme o segundo fator</h1>
        <p class="muted mt-3">Abra seu aplicativo autenticador e informe o código de seis dígitos.</p>
        <form action="{{ route('two-factor.login.store') }}" method="post" class="mt-6 space-y-5">
            @csrf
            <div><label for="code">Código do autenticador</label><input id="code" name="code" inputmode="numeric" autocomplete="one-time-code" pattern="[0-9]{6}" maxlength="6" required autofocus></div>
            <button class="primary" type="submit">Validar código</button>
        </form>
        <details class="mt-8 border-t border-slate-300 pt-5 dark:border-slate-700">
            <summary class="cursor-pointer font-medium">Usar um código de recuperação</summary>
            <p class="muted mt-3 text-sm">Cada código pode ser usado uma vez. Recuperar a senha por e-mail não remove o segundo fator.</p>
            <form action="{{ route('two-factor.login.store') }}" method="post" class="mt-4 space-y-4">
                @csrf
                <div><label for="recovery_code">Código de recuperação</label><input id="recovery_code" name="recovery_code" autocomplete="off" required></div>
                <button class="secondary" type="submit">Usar código de recuperação</button>
            </form>
        </details>
    </section>
</x-layout>
