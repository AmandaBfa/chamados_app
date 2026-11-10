<?php

namespace App\Services;

use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;

class RequesterTickets
{
    public const STATUSES = ['novo' => 'Novo', 'em_triagem' => 'Em triagem', 'em_atendimento' => 'Em atendimento',
        'aguardando_solicitante' => 'Aguardando solicitante', 'aguardando_terceiro' => 'Aguardando terceiro',
        'resolvido' => 'Resolvido', 'fechado' => 'Fechado', 'cancelado' => 'Cancelado'];

    public function open(User $actor, array $input, string $key, array $files = [], ?int $related = null): object
    {
        app(PortalAccess::class)->authorize($actor);
        Validator::make(['key' => $key], ['key' => ['required', 'uuid']])->validate();
        $written = [];
        try {
            return DB::transaction(function () use ($actor, $input, $key, $files, $related, &$written) {
                // Mesmo lock dos catálogos impede seleção concorrente à inativação/mudança do pai.
                DB::select('SELECT pg_advisory_xact_lock(73003)');
                $requester = User::lockForUpdate()->findOrFail($actor->id);
                app(PortalAccess::class)->authorize($requester);
                $existing = DB::table('tickets')->where('requester_id', $requester->id)->where('idempotency_key', $key)->first();
                if ($existing) {
                    return $existing;
                }
                $data = $this->validateOpening($input);
                $previous = $related === null ? null : app(PortalAccess::class)->own($actor, $related);
                $context = [];
                foreach (['unit_id' => 'units', 'department_id' => 'departments'] as $field => $table) {
                    $row = $requester->$field ? DB::table($table)->find($requester->$field) : null;
                    $context[$table] = $row ? ['id' => $row->id, 'name' => $row->name] + ($table === 'units' ? ['city' => $row->city] : []) : null;
                }
                $id = DB::table('tickets')->insertGetId($data + [
                    'protocol' => 'CH-'.strtoupper((string) Str::ulid()), 'idempotency_key' => $key,
                    'requester_id' => $requester->id, 'unit_id' => $requester->unit_id, 'department_id' => $requester->department_id,
                    'opening_context' => json_encode($context, JSON_THROW_ON_ERROR), 'type' => 'incidente',
                    'status' => 'novo', 'related_ticket_id' => $related, 'created_at' => now(), 'updated_at' => now(),
                    'restricted' => ($previous && ($previous->restricted || TicketOperations::securityCategory($previous->category_id)))
                        || TicketOperations::securityCategory($data['category_id']),
                ]);
                $this->attach($actor, $id, null, $files, $written);
                $this->event($id, $actor->id, 'ticket.opened', 'Chamado aberto pelo solicitante.');

                return DB::table('tickets')->find($id);
            });
        } catch (\Throwable $exception) {
            $this->cleanup($written);
            throw $exception;
        }
    }

    private function validateOpening(array $input): array
    {
        foreach ($input as $field => $value) {
            if (is_string($value) && trim($value) === '') {
                $input[$field] = null;
            }
        }
        $data = Validator::make($input, [
            'subject' => ['required', 'string', 'max:'.config('tickets.max_subject')],
            'description' => ['required', 'string', 'max:'.config('tickets.max_description')],
            'category_id' => ['required', 'integer', Rule::exists('categories', 'id')->where('active', true)],
            'subcategory_id' => ['nullable', 'integer', Rule::exists('subcategories', 'id')->where('active', true)->where('category_id', filter_var($input['category_id'] ?? null, FILTER_VALIDATE_INT) ?: 0)],
            'service_id' => ['nullable', 'integer', Rule::exists('services', 'id')->where('active', true)],
            'equipment_reference_id' => ['nullable', 'integer', Rule::exists('equipment_references', 'id')->where('active', true)],
            'impact_scope' => ['required', Rule::in(['pessoa', 'equipe', 'unidade', 'maior'])],
            'affected_process' => ['required', 'string', 'max:500'],
            'work_condition' => ['required', Rule::in(['continua', 'parcialmente_impedido', 'impedido'])],
            'started_unknown' => ['required', 'boolean'],
            'started_at' => ['nullable', 'date_format:Y-m-d\TH:i'],
        ], [], ['subject' => 'assunto', 'description' => 'descrição', 'category_id' => 'categoria', 'subcategory_id' => 'subcategoria',
            'service_id' => 'serviço', 'equipment_reference_id' => 'equipamento', 'impact_scope' => 'abrangência', 'affected_process' => 'processo afetado',
            'work_condition' => 'condição de trabalho', 'started_unknown' => 'início desconhecido', 'started_at' => 'início aproximado'])->validate();
        foreach (['subject', 'description', 'affected_process'] as $field) {
            $data[$field] = trim($data[$field]);
            if ($data[$field] === '') {
                throw ValidationException::withMessages([$field => 'Preencha este campo.']);
            }
        }
        if (! $data['started_unknown'] && empty($data['started_at'])) {
            throw ValidationException::withMessages(['started_at' => 'Informe o início aproximado ou marque Não sei.']);
        }
        $data['started_at'] = $data['started_unknown'] ? null : CarbonImmutable::createFromFormat('!Y-m-d\TH:i', $data['started_at'], config('installation.display_timezone'))->utc();
        if ($data['started_at']?->isFuture()) {
            throw ValidationException::withMessages(['started_at' => 'O início aproximado não pode estar no futuro.']);
        }
        if (! empty($data['service_id'])) {
            $service = DB::table('services')->find($data['service_id']);
            if (($service->category_id && $service->category_id !== (int) $data['category_id']) ||
                ($service->subcategory_id && $service->subcategory_id !== (int) ($data['subcategory_id'] ?? 0))) {
                throw ValidationException::withMessages(['service_id' => 'O serviço não corresponde à categoria/subcategoria selecionada.']);
            }
        }
        foreach (['subcategory_id', 'service_id', 'equipment_reference_id'] as $field) {
            $data[$field] = $data[$field] ?? null;
        }

        return $data;
    }

    public function comment(User $actor, int $ticketId, string $body, string $key, array $files = []): void
    {
        app(PortalAccess::class)->own($actor, $ticketId);
        Validator::make(['key' => $key], ['key' => ['required', 'uuid']])->validate();
        $written = [];
        try {
            DB::transaction(function () use ($actor, $ticketId, $body, $key, $files, &$written) {
                $ticket = DB::table('tickets')->where('id', $ticketId)->lockForUpdate()->firstOrFail();
                app(PortalAccess::class)->own($actor, $ticketId);
                if (DB::table('comments')->where('ticket_id', $ticketId)->where('author_id', $actor->id)->where('idempotency_key', $key)->exists()) {
                    return;
                }
                $body = trim($body);
                Validator::make(['body' => $body], ['body' => ['required', 'string', 'max:'.config('tickets.max_description')]], [], ['body' => 'comentário'])->validate();
                $id = DB::table('comments')->insertGetId(['ticket_id' => $ticketId, 'author_id' => $actor->id, 'body' => $body,
                    'idempotency_key' => $key, 'visibility' => 'public', 'created_at' => now(), 'updated_at' => now()]);
                $this->attach($actor, $ticketId, $id, $files, $written);
                $returning = $ticket->status === 'aguardando_solicitante';
                TicketOperations::record($ticket, $actor->id, $returning ? 'ticket.requester_returned' : 'comment.added',
                    $returning ? ['status' => 'em_atendimento'] : [], ['comment_id' => $id],
                    $returning ? 'O solicitante respondeu. Atendimento retomado.' : 'Comentário público adicionado pelo solicitante.');
            });
        } catch (\Throwable $exception) {
            $this->cleanup($written);
            throw $exception;
        }
    }

    private function attach(User $actor, int $ticketId, ?int $commentId, array $files, array &$written): void
    {
        $validated = app(TicketFileValidator::class)->validate($files);
        foreach ($validated as $item) {
            $file = $item['file'];
            abort_unless($file instanceof TemporaryUploadedFile, 422);
            $receipt = DB::table('temporary_upload_receipts')->where('path_hash', hash('sha256', $file->getFilename()))->lockForUpdate()->first();
            if (! $receipt || $receipt->user_id !== $actor->id || $receipt->consumed_at || now()->gte($receipt->expires_at)) {
                throw ValidationException::withMessages(['files' => 'Envio expirado, já utilizado ou pertencente a outro usuário. Selecione o arquivo novamente.']);
            }
            $path = 'tickets/'.Str::uuid().'.'.$item['extension'];
            $written[] = $path;
            if (! Storage::disk('local')->put($path, file_get_contents($file->getRealPath()))) {
                throw new \RuntimeException('Não foi possível armazenar o anexo privado. Tente novamente.');
            }
            DB::table('attachments')->insert([
                'ticket_id' => $ticketId, 'comment_id' => $commentId, 'uploader_id' => $actor->id,
                'storage_key' => $path, 'download_name' => $item['download_name'], 'mime' => $item['mime'],
                'size' => $item['size'], 'sha256' => $item['sha256'], 'visibility' => 'public', 'created_at' => now(), 'updated_at' => now(),
            ]);
            DB::table('temporary_upload_receipts')->where('path_hash', $receipt->path_hash)->update(['consumed_at' => now()]);
        }
    }

    private function cleanup(array $paths): void
    {
        foreach ($paths as $path) {
            try {
                Storage::disk('local')->delete($path);
            } catch (\Throwable) {
                // Não mascarar o erro original nem registrar conteúdo/caminhos privados.
                report(new \RuntimeException('Falha ao remover arquivo privado de uma transação não concluída. Requer revisão do armazenamento.'));
            }
        }
    }

    private function event(int $ticketId, int $actorId, string $type, string $summary): void
    {
        DB::table('ticket_events')->insert(['ticket_id' => $ticketId, 'actor_id' => $actorId, 'event_type' => $type,
            'visibility' => 'public', 'public_summary' => $summary, 'details' => '{}', 'occurred_at' => now()]);
    }

    public function detail(User $actor, int $id): array
    {
        $ticket = app(PortalAccess::class)->own($actor, $id);
        // Somente DTO permitido chega à view; notas internas e detalhes técnicos nem são carregados.
        $publicTicket = array_intersect_key((array) $ticket, array_flip(['id', 'protocol', 'subject', 'description', 'status',
            'impact_scope', 'affected_process', 'work_condition', 'started_unknown', 'started_at', 'created_at', 'reopen_requested_at', 'related_ticket_id']));
        $publicTicket['context'] = json_decode($ticket->opening_context, true, 512, JSON_THROW_ON_ERROR);
        $labels = [];
        foreach (['category_id' => 'categories', 'subcategory_id' => 'subcategories', 'service_id' => 'services', 'equipment_reference_id' => 'equipment_references'] as $field => $table) {
            $labels[$field] = $ticket->$field ? DB::table($table)->where('id', $ticket->$field)->value('name') : null;
        }

        return ['ticket' => $publicTicket, 'labels' => $labels,
            'comments' => DB::table('comments')->join('users', 'users.id', '=', 'comments.author_id')->where('ticket_id', $id)->where('visibility', 'public')
                ->orderBy('comments.id')->get(['comments.id', 'comments.body', 'comments.created_at', 'users.name as author']),
            'attachments' => $this->publicAttachments($id)->orderBy('attachments.id')->get(['attachments.id', 'attachments.comment_id', 'attachments.download_name', 'attachments.size']),
            'events' => DB::table('ticket_events')->where('ticket_id', $id)->where('visibility', 'public')->orderBy('id')->get(['event_type', 'public_summary', 'occurred_at']),
        ];
    }

    public function attachment(User $actor, int $ticketId, int $attachmentId): object
    {
        app(PortalAccess::class)->own($actor, $ticketId);

        $attachment = $this->publicAttachments($ticketId)->where('attachments.id', $attachmentId)->first();
        abort_unless($attachment, 404);

        return $attachment;
    }

    private function publicAttachments(int $ticketId): Builder
    {
        return DB::table('attachments')->where('attachments.ticket_id', $ticketId)->where('attachments.visibility', 'public')
            ->where(fn ($q) => $q->whereNull('comment_id')->orWhereExists(fn ($sub) => $sub->selectRaw('1')->from('comments')
                ->whereColumn('comments.id', 'attachments.comment_id')->whereColumn('comments.ticket_id', 'attachments.ticket_id')->where('comments.visibility', 'public')));
    }
}
