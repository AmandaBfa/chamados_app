<x-layout title="Confirmar e-mail">
    <section class="panel mx-auto max-w-lg">
        <h1>Confirme seu e-mail</h1>
        <p class="muted mt-3">Abra a mensagem enviada para o e-mail autorizado e use o link de confirmação. O acesso ao sistema só será liberado após essa etapa.</p>
        <form action="{{ route('verification.send') }}" method="post" class="mt-6">
            @csrf<button class="primary" type="submit">Reenviar confirmação</button>
        </form>
    </section>
</x-layout>
