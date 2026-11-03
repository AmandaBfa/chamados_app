<div class="space-y-8">
    @if(session('status'))<p role="status" class="rounded-lg border border-emerald-400 p-4">{{ session('status') }}</p>@endif
    @if($errors->any())
        <div role="alert" class="rounded-lg border border-red-400 p-4"><p class="font-semibold">Não foi possível salvar:</p><ul class="mt-2 list-inside list-disc">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>
    @endif
    <section class="panel" aria-labelledby="invite-title">
        <h2 id="invite-title">Convidar usuário</h2>
        <form wire:submit="createInvite" class="mt-5 space-y-5">
            <div class="grid gap-5 sm:grid-cols-2">
                <div><label for="invite-name">Nome</label><input id="invite-name" wire:model="inviteName" autocomplete="off" maxlength="160" required></div>
                <div><label for="invite-email">E-mail</label><input id="invite-email" wire:model="inviteEmail" type="email" autocomplete="off" required></div>
            </div>
            <fieldset><legend class="font-medium">Perfis autorizados</legend><div class="mt-3 flex flex-wrap gap-4">
                @foreach($availableRoles as $role)<label class="flex items-center gap-2"><input type="checkbox" wire:model="inviteRoles" value="{{ $role->value }}">{{ $role->label() }}</label>@endforeach
            </div></fieldset>
            <div><label for="invite-reason">Justificativa da concessão</label><textarea id="invite-reason" wire:model="inviteReason" rows="2" minlength="8" maxlength="500" required></textarea></div>
            <button class="primary" type="submit" wire:loading.attr="disabled" wire:target="createInvite">Autorizar e enviar convite</button>
        </form>
    </section>
    <section class="panel" aria-labelledby="users-title">
        <h2 id="users-title">Usuários cadastrados</h2>
        @can('catalogs.manage')<p class="mt-3"><a class="text-link" href="{{ route('admin.catalogs', 'users') }}">Editar unidade, setor, cargo e classificação dos usuários</a></p>@endcan
        <ul class="mt-5 divide-y divide-slate-200 dark:divide-slate-700">
            @foreach($users as $user)
                <li wire:key="user-{{ $user->id }}" class="flex flex-wrap items-center justify-between gap-4 py-4">
                    <div class="min-w-0 break-words">
                        <p class="font-medium">{{ $user->name }} · {{ $user->active ? 'Ativo' : 'Inativo' }}</p>
                        <p class="muted text-sm break-all">{{ $user->email }}</p>
                        <p class="muted text-sm">{{ $user->roles->pluck('label')->join(', ') }} · {{ $user->password === null ? 'Convite pendente' : ($user->hasVerifiedEmail() ? 'E-mail confirmado' : 'E-mail pendente') }}</p>
                    </div>
                    <button class="secondary" type="button" wire:click="edit({{ $user->id }})">Editar acesso de {{ $user->name }}</button>
                </li>
            @endforeach
        </ul>
        <div class="mt-5">{{ $users->links() }}</div>
    </section>
    @if($selectedId)
        <section class="panel" aria-labelledby="edit-title">
            <h2 id="edit-title">Editar acesso</h2>
            <form wire:submit="saveUser" class="mt-5 space-y-5">
                <div><label for="edit-name">Nome</label><input id="edit-name" wire:model="name" maxlength="160" required></div>
                <label class="flex items-center gap-2"><input type="checkbox" wire:model="active">Conta ativa</label>
                <fieldset><legend class="font-medium">Perfis autorizados</legend><div class="mt-3 flex flex-wrap gap-4">
                    @foreach($availableRoles as $role)<label class="flex items-center gap-2"><input type="checkbox" wire:model="selectedRoles" value="{{ $role->value }}">{{ $role->label() }}</label>@endforeach
                </div></fieldset>
                <div><label for="reason">Justificativa da alteração ou reenvio</label><textarea id="reason" wire:model="reason" rows="2" minlength="8" maxlength="500" required></textarea></div>
                <p class="muted text-sm">Alterar estado ou perfis encerra sessões e invalida links anteriores. Uma conta reativada precisa entrar novamente. O último administrador ativo é protegido.</p>
                <button class="primary" type="submit" wire:loading.attr="disabled">Salvar acesso</button>
                <button class="secondary" type="button" wire:click="reissueInvite({{ $selectedId }})" wire:loading.attr="disabled">Reenviar convite pendente</button>
            </form>
        </section>
    @endif
</div>
