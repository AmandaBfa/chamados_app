@props(['title' => 'Sistema de chamados', 'showErrorSummary' => true])
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $title }} · {{ config('app.name') }}</title>
    <script>try { document.documentElement.classList.toggle('dark', localStorage.getItem('theme') === 'dark'); } catch (error) {}</script>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @livewireStyles
</head>
<body class="min-h-screen bg-slate-100 text-slate-900 antialiased dark:bg-slate-950 dark:text-slate-100">
    <a href="#conteudo" class="sr-only focus:not-sr-only focus:absolute focus:z-50 focus:bg-white focus:p-4 focus:text-slate-900">Ir para o conteúdo</a>
    <header class="border-b border-slate-200 bg-white dark:border-slate-700 dark:bg-slate-900">
        <div class="mx-auto flex max-w-5xl flex-wrap items-center justify-between gap-4 px-5 py-4">
            <a class="font-semibold" href="{{ url('/') }}">{{ config('app.name') }}</a>
            <div class="flex flex-wrap items-center gap-4">
                <button type="button" id="theme-toggle" aria-pressed="false" class="secondary">Tema escuro</button>
                @auth
                    <form action="{{ route('logout') }}" method="post">@csrf<button class="secondary" type="submit">Sair</button></form>
                @endauth
            </div>
        </div>
    </header>
    <main id="conteudo" class="mx-auto max-w-5xl px-5 py-8 sm:py-12">
        @if(session('status'))
            <p role="status" class="mb-6 rounded-lg border border-emerald-400 bg-emerald-50 p-4 text-emerald-950 dark:bg-emerald-950 dark:text-emerald-100">{{ __(session('status')) }}</p>
        @endif
        @if($showErrorSummary && collect($errors->getBags())->contains(fn ($bag) => $bag->any()))
            <div role="alert" class="mb-6 rounded-lg border border-red-400 bg-red-50 p-4 text-red-900 dark:bg-red-950 dark:text-red-100">
                <p class="font-semibold">Confira os dados informados:</p>
                <ul class="mt-2 list-inside list-disc">
                    @foreach($errors->getBags() as $bag)
                        @foreach($bag->all() as $error)<li>{{ $error }}</li>@endforeach
                    @endforeach
                </ul>
            </div>
        @endif
        {{ $slot }}
    </main>
    @livewireScripts
</body>
</html>
