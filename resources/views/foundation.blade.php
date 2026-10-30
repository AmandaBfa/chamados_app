<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ config('app.name') }}</title>
    <script>
        try { document.documentElement.classList.toggle('dark', localStorage.getItem('theme') === 'dark'); } catch (error) {}
    </script>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @livewireStyles
</head>
<body class="min-h-screen bg-slate-100 font-sans text-slate-900 antialiased dark:bg-slate-950 dark:text-slate-100">
    <a href="#conteudo" class="sr-only focus:not-sr-only focus:absolute focus:p-4">Ir para o conteúdo</a>
    <main id="conteudo" class="mx-auto max-w-2xl px-5 py-10 sm:py-20">
        <div class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm sm:p-10 dark:border-slate-700 dark:bg-slate-900">
            <div class="flex flex-wrap items-center justify-between gap-4">
                <p class="text-sm font-semibold uppercase tracking-wide text-indigo-700 dark:text-indigo-300">Etapa 1 · Fundação</p>
                <button type="button" id="theme-toggle" aria-pressed="false" class="rounded-lg border border-slate-300 px-3 py-2 text-sm dark:border-slate-600">Tema escuro</button>
            </div>
            <h1 class="mt-6 text-3xl font-semibold tracking-tight">{{ config('app.name') }}</h1>
            <p class="mt-4 leading-relaxed text-slate-600 dark:text-slate-300">Ambiente inicial de desenvolvimento. Esta tela permite verificar a renderização, os estilos e a comunicação da interface.</p>
            <p class="mt-4 rounded-lg bg-slate-100 p-4 text-sm leading-relaxed dark:bg-slate-800">Verificação técnica do ambiente. <a href="{{ route('login') }}" class="underline">Acessar o sistema</a>. A abertura e o atendimento de chamados ainda não estão disponíveis.</p>
            <livewire:foundation-check />
        </div>
    </main>
    @livewireScripts
</body>
</html>
