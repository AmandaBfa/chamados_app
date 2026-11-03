<div class="space-y-6">
    @if(session('status'))<p role="status" class="rounded-lg border border-emerald-400 p-4">{{ session('status') }}</p>@endif
    @if($errors->any())<div role="alert" class="rounded-lg border border-red-400 p-4"><p>Confira os dados:</p><ul class="list-inside list-disc">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif
    <section class="panel" aria-labelledby="catalog-title">
        <h2 id="catalog-title">{{ $definition['label'] }}</h2>
        <div class="my-5 grid gap-4 sm:grid-cols-2">
            <div><label for="catalog-search">Buscar por nome</label><input id="catalog-search" wire:model.live.debounce.300ms="search" type="search" maxlength="160"></div>
            <div><label for="catalog-status">Estado</label><select id="catalog-status" wire:model.live="statusFilter"><option value="all">Todos</option><option value="active">Ativos</option><option value="inactive">Inativos</option></select></div>
        </div>
        <ul class="divide-y divide-slate-200 dark:divide-slate-700">
            @forelse($rows as $row)
                <li wire:key="{{ $kind }}-{{ $row->id }}" class="flex flex-wrap items-center justify-between gap-3 py-4">
                    <div class="min-w-0 break-words"><p class="font-medium">{{ $row->name }}</p><p class="muted">{{ $row->active ? 'Ativo' : 'Inativo' }} @if($kind === 'units') · {{ $row->city }} @endif</p></div>
                    <button type="button" class="secondary" wire:click="edit({{ $row->id }})">Editar {{ $row->name }}</button>
                </li>
            @empty<li class="muted py-5">Nenhum registro encontrado.</li>@endforelse
        </ul>
        <div class="mt-4">{{ $rows->links() }}</div>
        @if($kind !== 'users')<button type="button" class="secondary mt-5" wire:click="newRecord">Novo registro</button>@endif
    </section>
    @if($kind !== 'users' || $selectedId)
    <section class="panel" aria-labelledby="form-title">
        <h2 id="form-title">{{ $selectedId ? 'Editar registro' : 'Adicionar registro' }}</h2>
        @if($kind === 'teams' || $kind === 'team_members')<p class="muted mt-3">Equipes, referências e N1/N2/N3 não concedem perfis ou acesso global. Cadastre os membros antes de escolher referência e substituto. A cobertura é uma indicação manual.</p>@endif
        @if($kind === 'services')<p class="muted mt-3">Criticidade é proposta para o piloto e depende de validação corporativa. Nenhum serviço gera P1 automaticamente.</p>@endif
        <form wire:submit="save" class="mt-5 space-y-5">
            <div class="grid gap-5 sm:grid-cols-2">
            @foreach($definition['fields'] as $field => $label)
                <div wire:key="field-{{ $kind }}-{{ $field }}">
                @if(in_array($field, \App\Services\CatalogDefinition::booleans(), true))
                    <label class="flex items-center gap-2" for="field-{{ $field }}"><input id="field-{{ $field }}" type="checkbox" wire:model="form.{{ $field }}">{{ $label }}</label>
                @elseif(isset($options[$field]))
                    <label for="field-{{ $field }}">{{ $label }}</label>
                    <select id="field-{{ $field }}" wire:model.live="form.{{ $field }}"><option value="">Não informado</option>@foreach($options[$field] as $option)<option value="{{ $option->id }}">{{ $option->name }}{{ $option->active ? '' : ' (inativo — vínculo existente)' }}</option>@endforeach</select>
                @elseif($field === 'proposed_criticality')
                    <label for="field-{{ $field }}">{{ $label }}</label><select id="field-{{ $field }}" wire:model="form.{{ $field }}"><option value="">A validar</option>@foreach(['baixa' => 'Baixa', 'normal' => 'Normal', 'alta' => 'Alta', 'critica' => 'Crítica'] as $value => $text)<option value="{{ $value }}">{{ $text }}</option>@endforeach</select>
                @else
                    <label for="field-{{ $field }}">{{ $label }}</label><input id="field-{{ $field }}" wire:model="form.{{ $field }}" maxlength="{{ $field === 'support_level' ? 20 : 160 }}" @if(in_array($field, ['name', 'city'])) required @endif>
                @endif
                </div>
            @endforeach
            </div>
            @if($kind !== 'users')<label class="flex items-center gap-2"><input type="checkbox" wire:model="form.active">Registro ativo</label><p class="muted text-sm">Inativar preserva os vínculos e o histórico. Itens inativos deixam de ser oferecidos em novas seleções.</p>@endif
            <div><label for="catalog-reason">Justificativa</label><textarea id="catalog-reason" wire:model="reason" minlength="8" maxlength="500" required rows="2"></textarea></div>
            <button class="primary" type="submit" wire:loading.attr="disabled">Salvar cadastro</button>
        </form>
    </section>
    @endif
</div>
