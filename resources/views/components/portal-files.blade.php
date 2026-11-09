@props(['files' => [], 'id' => 'files'])
<div>
    <label for="{{ $id }}">Anexos (opcionais)</label>
    <p id="{{ $id }}-help" class="muted text-sm">Até {{ config('tickets.max_files') }} arquivos por envio, {{ config('tickets.max_file_kb') / 1024 }} MB cada. PNG, JPEG, PDF simples ou TXT. Não envie senhas ou dados desnecessários.</p>
    <input id="{{ $id }}" wire:model="files" type="file" multiple accept=".png,.jpg,.jpeg,.pdf,.txt" aria-describedby="{{ $id }}-help">
    <p wire:loading wire:target="files" role="status" class="muted mt-2">Enviando arquivos. Aguarde antes de salvar.</p>
    @if(count($files))
        <ul class="mt-3 space-y-2">@foreach($files as $file)
            <li class="flex flex-wrap items-center gap-3 break-all"><span>{{ $file->getClientOriginalName() }}</span><button class="secondary" type="button" wire:click="removeFile({{ $loop->index }})">Remover arquivo</button></li>
        @endforeach</ul>
    @endif
</div>
