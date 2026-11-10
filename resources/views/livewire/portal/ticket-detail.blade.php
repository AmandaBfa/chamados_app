<div class="space-y-6">
    @if(session('status'))<p role="status" class="rounded-lg border border-emerald-400 p-4">{{ session('status') }}</p>@endif
    <section class="panel">
        <p class="eyebrow break-all">{{ $ticket['protocol'] }}</p><h1 class="mt-3 break-words">{{ $ticket['subject'] }}</h1>
        <p class="mt-4 font-semibold">Situação <x-ticket-status :status="$ticket['status']" /></p>
        @if($ticket['related_ticket_id'])
            <a class="text-link" href="{{ route('portal.show', $ticket['related_ticket_id']) }}">Chamado anterior vinculado</a>
        @endif
        @if(in_array($ticket['status'], ['resolvido', 'fechado']))
            <div class="mt-4 space-y-3">
                @if($ticket['reopen_requested_at'])
                    <p>Pedido de reabertura aguardando avaliação do TI.</p>
                @else
                    @if($ticket['status'] === 'resolvido')
                        <button type="button" class="secondary" wire:click="solution('confirm')" wire:loading.attr="disabled">Confirmar solução</button>
                    @endif
                    <form wire:submit="solution('request_reopen')">
                        <label for="reopen-reason">O mesmo problema voltou? Descreva o que aconteceu.</label>
                        <textarea id="reopen-reason" wire:model="reopenReason" required maxlength="10000"></textarea>
                        <button class="secondary mt-2" type="submit" wire:loading.attr="disabled">Solicitar reabertura</button>
                    </form>
                @endif
                <a class="text-link" href="{{ route('portal.open', ['relacionado' => $ticket['id']]) }}">Abrir chamado vinculado para outro problema</a>
            </div>
        @endif
        <dl class="muted mt-5 space-y-2">
            <div><dt class="inline font-medium">Unidade na abertura:</dt> <dd class="inline">{{ $ticket['context']['units']['name'] ?? 'Não informada' }} @if(isset($ticket['context']['units']['city'])) · {{ $ticket['context']['units']['city'] }} @endif</dd></div>
            <div><dt class="inline font-medium">Setor na abertura:</dt> <dd class="inline">{{ $ticket['context']['departments']['name'] ?? 'Não informado' }}</dd></div>
            <div><dt class="inline font-medium">Categoria / subcategoria:</dt> <dd class="inline">{{ $labels['category_id'] }} / {{ $labels['subcategory_id'] ?? 'Não identificada' }}</dd></div>
            <div><dt class="inline font-medium">Serviço / equipamento:</dt> <dd class="inline">{{ $labels['service_id'] ?? 'Não identificado' }} / {{ $labels['equipment_reference_id'] ?? 'Não identificado' }}</dd></div>
            <div><dt class="inline font-medium">Abrangência:</dt> <dd class="inline">{{ ['pessoa' => 'Uma pessoa', 'equipe' => 'Uma equipe', 'unidade' => 'Uma unidade', 'maior' => 'Abrangência maior'][$ticket['impact_scope']] }}</dd></div>
            <div><dt class="inline font-medium">Condição de trabalho:</dt> <dd class="inline">{{ ['continua' => 'Consegue continuar', 'parcialmente_impedido' => 'Parcialmente impedido', 'impedido' => 'Impedido'][$ticket['work_condition']] }}</dd></div>
            <div><dt class="inline font-medium">Início aproximado:</dt> <dd class="inline">{{ $ticket['started_unknown'] ? 'Não informado' : \Carbon\CarbonImmutable::parse($ticket['started_at'])->tz(config('installation.display_timezone'))->format('d/m/Y H:i') }}</dd></div>
        </dl>
        <h2 class="mt-6">Descrição</h2><p class="mt-3 whitespace-pre-wrap break-words">{{ $ticket['description'] }}</p>
        <h2 class="mt-6">Atividade ou processo afetado</h2><p class="mt-3 whitespace-pre-wrap break-words">{{ $ticket['affected_process'] }}</p>
    </section>
    <section class="panel" aria-labelledby="conversation-title">
        <h2 id="conversation-title">Conversa pública</h2>
        <ul class="mt-4 space-y-4">@forelse($comments as $comment)
            <li wire:key="comment-{{ $comment->id }}" class="rounded-lg border border-slate-300 p-4 dark:border-slate-600"><p class="muted text-sm">{{ $comment->author }} · {{ \Carbon\CarbonImmutable::parse($comment->created_at)->tz(config('installation.display_timezone'))->format('d/m/Y H:i') }}</p><p class="mt-3 whitespace-pre-wrap break-words">{{ $comment->body }}</p></li>
        @empty<li class="muted">Ainda não há comentários públicos.</li>@endforelse</ul>
        <div class="mt-6"><x-portal-errors /></div>
        <form wire:submit="comment" class="mt-4 space-y-5">
            <div><label for="comment-body">Adicionar comentário público</label><textarea id="comment-body" wire:model="body" required rows="4" maxlength="{{ config('tickets.max_description') }}"></textarea></div>
            <x-portal-files :files="$files" id="comment-files" />
            <button type="submit" class="primary" wire:loading.attr="disabled" wire:target="comment,files">Enviar comentário</button>
        </form>
    </section>
    <section class="panel" aria-labelledby="attachments-title">
        <h2 id="attachments-title">Anexos públicos</h2>
        <ul class="mt-4 space-y-3">@forelse($attachments as $file)<li class="break-all"><a class="text-link" href="{{ route('portal.attachment', [$ticket['id'], $file->id]) }}">Baixar {{ $file->download_name }}</a><span class="muted"> · {{ number_format($file->size / 1024, 1, ',', '.') }} KB · {{ $file->comment_id ? 'Comentário #'.$file->comment_id : 'Abertura' }}</span></li>@empty<li class="muted">Nenhum anexo público.</li>@endforelse</ul>
    </section>
    <section class="panel" aria-labelledby="history-title">
        <h2 id="history-title">Histórico público</h2><ol class="mt-4 space-y-3">@foreach($events as $event)<li><span class="muted">{{ \Carbon\CarbonImmutable::parse($event->occurred_at)->tz(config('installation.display_timezone'))->format('d/m/Y H:i') }}</span> · {{ $event->public_summary }}</li>@endforeach</ol>
    </section>
</div>
