<section class="space-y-5">
    @if(session('status'))<p role="status">{{ session('status') }}</p>@endif
    <h1 class="text-2xl font-semibold break-words">{{ $ticket->protocol }} · {{ $ticket->subject }}</h1>
    <div class="flex flex-wrap items-center gap-3">
        <x-ticket-status :status="$ticket->status" />
        <span>Ciclo {{ $ticket->cycle }}</span>
        <span>Prioridade {{ $ticket->priority ?? 'a validar' }}</span>
    </div>
    <p><strong>Responsável:</strong> {{ $responsibleName ?? 'A definir' }}</p>
    @if($sla)
        <x-ticket-sla :sla="$sla" :version="$ticket->sla_version_id" />
    @endif
    @if($ticket->status === 'em_triagem' && $responsibleName)
        <p class="muted">Revise a classificação, converse com o solicitante ou encaminhe o chamado.</p>
    @elseif($ticket->status === 'aguardando_solicitante')
        <p class="muted">A resposta do solicitante retoma o atendimento automaticamente.</p>
    @elseif($ticket->status === 'aguardando_terceiro')
        <p class="muted">Retome o atendimento quando receber o retorno do terceiro.</p>
    @endif
    <section class="rounded-lg border border-slate-300 p-4 dark:border-slate-600" aria-labelledby="requester-title">
        <h2 id="requester-title">Solicitante</h2>
        <dl class="mt-3 grid gap-3 break-words sm:grid-cols-2">
            <div><dt class="font-medium">Nome</dt><dd>{{ $requester->name }}</dd></div>
            <div><dt class="font-medium">E-mail</dt><dd>{{ $requester->email }}</dd></div>
            <div>
                <dt class="font-medium">Unidade na abertura</dt>
                <dd>{{ $openingContext['units']['name'] ?? 'Não informada' }}@if(!empty($openingContext['units']['city'])) · {{ $openingContext['units']['city'] }}@endif</dd>
            </div>
            <div><dt class="font-medium">Setor na abertura</dt><dd>{{ $openingContext['departments']['name'] ?? 'Não informado' }}</dd></div>
        </dl>
    </section>
    <p class="whitespace-pre-wrap">{{ $ticket->description }}</p>
    <p>Processo afetado: {{ $ticket->affected_process }} · Abrangência: {{ $ticket->impact_scope }} · Condição: {{ str_replace('_', ' ', $ticket->work_condition) }}</p>
    @if ($ticket->reopen_requested_at)<p class="font-semibold">Reabertura solicitada pelo colaborador.</p>@endif
    <x-portal-errors />
    @if($actions)
        <section class="space-y-3" aria-labelledby="ticket-actions-title">
            <h2 id="ticket-actions-title">Ações disponíveis</h2>
            <div class="flex flex-wrap gap-2">
                @foreach($actions as $key => $label)
                    <button type="button" class="{{ $loop->first || $action === $key ? 'primary' : 'secondary' }}"
                        wire:click="chooseAction('{{ $key }}')" wire:loading.attr="disabled">{{ $label }}</button>
                @endforeach
            </div>
        </section>
    @endif
    @if(isset($actions[$action]) && !in_array($action, ['triage', 'start'], true))
    <form wire:submit="save" class="panel space-y-3" wire:key="ticket-action-{{ $action }}">
        <h2>{{ $actions[$action] }}</h2>
        @foreach (['category_id' => 'Categoria', 'subcategory_id' => 'Subcategoria', 'service_id' => 'Serviço', 'team_id' => 'Equipe', 'user_id' => $action === 'forward' ? 'Novo responsável' : 'Técnico'] as $field => $label)
            @if ((in_array($action, ['classify', 'resolve']) && in_array($field, ['category_id', 'subcategory_id', 'service_id'])) || ($action === 'forward' && in_array($field, ['team_id', 'user_id'])) || ($action === 'take_triage' && $field === 'team_id') || ($action === 'collaborate' && $field === 'user_id'))
                <label class="block">{{ $label }}
                    <select class="field" wire:model.live="input.{{ $field }}" @required(in_array($action, ['forward', 'take_triage'], true))>
                        <option value="">Selecione</option>
                        @foreach ($choices[$field] as $id => $name)<option value="{{ $id }}">{{ $name }}</option>@endforeach
                    </select>
                </label>
            @endif
        @endforeach
        @if ($action === 'classify')
            <div class="grid gap-3 sm:grid-cols-2">
                <label>Impacto no negócio
                    <select class="field" wire:model.live="input.business_impact">
                        <option value="">Avaliar</option>
                        <option value="alto">Alto · processo essencial ou unidade amplamente afetada</option>
                        <option value="medio">Médio · equipe ou processo relevante</option>
                        <option value="baixo">Baixo · situação localizada</option>
                    </select>
                </label>
                <label>Urgência
                    <select class="field" wire:model.live="input.urgency">
                        <option value="">Avaliar</option>
                        <option value="alta">Alta · bloqueio sem alternativa e necessidade imediata</option>
                        <option value="media">Média · prejuízo com alternativa limitada</option>
                        <option value="baixa">Baixa · pode aguardar</option>
                    </select>
                </label>
            </div>
            @if($suggestion)<p role="status">Prioridade sugerida pela matriz: {{ $suggestion }}. Valide a prioridade abaixo.</p>@endif
            <label class="block">Prioridade
                <select class="field" wire:model="input.priority"><option value="">Manter atual</option>
                    @foreach (['P1', 'P2', 'P3', 'P4'] as $priority)<option>{{ $priority }}</option>@endforeach
                </select>
            </label>
            <p>Justifique mudanças pela combinação de impacto no negócio e urgência.</p>
            @if($ticket->status === 'aguardando_solicitante' && $ticket->priority !== 'P1')
                <p>Alterar para P1 retoma o relógio de solução durante a espera.</p>
                @if(auth()->user()->hasRole(\App\Enums\RoleName::Manager))
                    <label class="block"><input type="checkbox" wire:model="input.keep_p1_pause">
                        Manter a pausa ao alterar para P1 por exceção gerencial. Inclua o motivo na justificativa.
                    </label>
                @endif
            @endif
        @endif
        @if (in_array($action, ['reply', 'note', 'wait_requester', 'wait_third', 'resolve']))
            <label class="block">{{ ['reply' => 'Resposta pública', 'note' => 'Nota interna, visível somente ao TI autorizado', 'wait_requester' => 'Pergunta pública indispensável', 'wait_third' => 'Fornecedor ou dependência', 'resolve' => 'Solução comunicada ao solicitante'][$action] }}
                <textarea class="field" wire:model="input.body" rows="4" required></textarea>
            </label>
        @endif
        @if (in_array($action, ['reply', 'wait_requester', 'resolve']))
            <label class="block"><input type="checkbox" wire:model="input.useful"> Confirmo que este retorno público demonstra entendimento ou oferece encaminhamento útil.</label>
        @endif
        @if ($action === 'wait_third')
            <label class="block">Protocolo do terceiro <input class="field" wire:model="input.protocol" required></label>
        @endif
        @if ($action === 'resolve')
            <label class="block">Restauração temporária, se aplicável <textarea class="field" wire:model="input.restoration"></textarea></label>
        @endif
        @if (in_array($action, ['forward', 'collaborate', 'classify', 'wait_requester', 'resume', 'cancel', 'reopen', 'decline_reopen']))
            <label class="block">{{ $action === 'forward' ? 'Motivo do encaminhamento' : 'Justificativa' }} <textarea class="field" wire:model="input.reason" @required(in_array($action, ['forward', 'collaborate', 'resume', 'cancel', 'reopen', 'decline_reopen'], true))></textarea></label>
        @endif
        <button class="primary" type="submit" wire:loading.attr="disabled">Confirmar</button>
    </form>
    @endif
    <h2 class="text-xl font-semibold">Conversa</h2>
    @foreach ($comments as $comment)
        <article class="rounded border p-3 {{ $comment->visibility === 'internal' ? 'border-amber-500' : 'border-sky-500' }}">
            <strong>{{ $comment->visibility === 'internal' ? 'Nota interna' : 'Mensagem pública' }} · {{ $comment->name }}</strong>
            <p class="whitespace-pre-wrap">{{ $comment->body }}</p>
        </article>
    @endforeach
    @foreach ($attachments as $file)
        <a class="text-link block" href="{{ route('ti.attachment', [$ticket->id, $file->id]) }}">{{ $file->download_name }}</a>
    @endforeach
    <h2 class="text-xl font-semibold">Histórico</h2>
    @foreach ($events as $event)
        <details class="rounded border p-3">
            <summary>{{ $event->public_summary ?? (['ticket.note' => 'Nota interna', 'ticket.triage' => 'Triagem iniciada', 'ticket.take_triage' => 'Triagem assumida', 'ticket.forward' => 'Chamado encaminhado', 'ticket.assign' => 'Atribuição atualizada', 'ticket.claim' => 'Chamado assumido', 'ticket.classify' => 'Classificação atualizada', 'ticket.collaborate' => 'Colaboração designada'][$event->event_type] ?? $event->event_type) }} · {{ $event->name ?? 'Sistema' }} · {{ \Illuminate\Support\Carbon::parse($event->occurred_at)->setTimezone(config('installation.display_timezone'))->format('d/m/Y H:i:s') }}</summary>
            <pre class="whitespace-pre-wrap break-all">{{ json_encode(json_decode($event->details), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) }}</pre>
        </details>
    @endforeach
</section>
