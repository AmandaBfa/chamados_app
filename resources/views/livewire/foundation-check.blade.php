<section aria-labelledby="verification-title" class="mt-8 border-t border-slate-200 pt-6 dark:border-slate-700">
    <h2 id="verification-title" class="text-lg font-semibold">Verificação da interface</h2>
    <p class="mt-2 text-sm text-slate-600 dark:text-slate-300">O botão verifica a comunicação com o servidor usando Livewire.</p>
    <button type="button" wire:click="verify" wire:loading.attr="disabled" class="mt-4 rounded-lg bg-indigo-700 px-5 py-3 font-medium text-white hover:bg-indigo-800 disabled:opacity-60">
        <span wire:loading.remove wire:target="verify">Verificar comunicação</span>
        <span wire:loading wire:target="verify">Verificando…</span>
    </button>
    <p role="status" class="mt-4 text-sm">{{ $verified ? 'Comunicação com o servidor verificada.' : 'Aguardando verificação.' }}</p>
</section>
