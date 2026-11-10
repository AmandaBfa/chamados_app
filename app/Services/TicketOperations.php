<?php

namespace App\Services;

use App\Enums\RoleName;
use App\Models\User;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class TicketOperations
{
    public const STATES = [
        'novo' => 'Novo', 'em_triagem' => 'Em triagem', 'em_atendimento' => 'Em atendimento',
        'aguardando_solicitante' => 'Aguardando solicitante', 'aguardando_terceiro' => 'Aguardando terceiro',
        'resolvido' => 'Resolvido', 'fechado' => 'Fechado', 'cancelado' => 'Cancelado',
    ];

    public const STAFF = [RoleName::Support, RoleName::Analyst, RoleName::Manager, RoleName::Developer, RoleName::Apprentice];

    public static function securityCategory(int $id): bool
    {
        return DB::table('categories')->where('id', $id)->where('seed_key', 'category-10')->exists();
    }

    public function queue(User $actor): Builder
    {
        app(PortalAccess::class)->authorize($actor);
        abort_unless($this->hasRole($actor, self::STAFF), 403);

        $query = DB::table('tickets')->where(function (Builder $query) use ($actor) {
            $query->where(fn ($common) => $common->where('restricted', false)
                ->whereNotIn('category_id', DB::table('categories')->where('seed_key', 'category-10')->select('id')))
                ->orWhereExists(fn ($grant) => $grant
                    ->selectRaw('1')->from('ticket_access_grants')->whereColumn('ticket_id', 'tickets.id')
                    ->where('user_id', $actor->id));
        });

        if (! $this->hasRole($actor, [RoleName::Support, RoleName::Analyst, RoleName::Manager])) {
            $query->where(function (Builder $query) use ($actor) {
                $query->where('assignee_id', $actor->id)->orWhereExists(fn ($member) => $member
                    ->selectRaw('1')->from('ticket_participants')->whereColumn('ticket_id', 'tickets.id')
                    ->where('user_id', $actor->id));
                if ($actor->hasRole(RoleName::Apprentice)) {
                    $query->orWhereIn('team_id', DB::table('team_members')->join('teams', 'teams.id', '=', 'team_members.team_id')
                        ->where('user_id', $actor->id)->where('team_members.active', true)->where('teams.active', true)
                        ->where('teams.specific_assignments_only', false)->select('team_id'));
                }
            });
        }

        return $query;
    }

    private function hasRole(User $actor, array $roles): bool
    {
        foreach ($roles as $role) {
            if ($actor->hasRole($role)) {
                return true;
            }
        }

        return false;
    }

    public function ticket(User $actor, int $id): object
    {
        $ticket = $this->queue($actor)->where('tickets.id', $id)->first();
        abort_unless($ticket, 404);

        return $ticket;
    }

    public function act(User $actor, int $id, int $version, string $action, array $input): void
    {
        $this->ticket($actor, $id);
        DB::transaction(function () use ($actor, $id, $version, $action, $input) {
            if (in_array($action, ['assign', 'claim', 'collaborate', 'classify', 'resolve'], true)) {
                DB::select('SELECT pg_advisory_xact_lock(73003)');
            }
            $locked = DB::table('tickets')->where('id', $id)->lockForUpdate()->firstOrFail();
            $this->ticket($actor, $id);
            $this->check($locked->lock_version === $version, 'O chamado foi atualizado. Recarregue antes de tentar novamente.');
            $active = in_array($locked->status, ['novo', 'em_triagem', 'em_atendimento', 'aguardando_solicitante', 'aguardando_terceiro'], true);
            $responsible = $locked->assignee_id === $actor->id;
            $participant = DB::table('ticket_participants')->where('ticket_id', $id)->where('user_id', $actor->id)->exists();
            $member = DB::table('team_members')->join('teams', 'teams.id', '=', 'team_members.team_id')
                ->where('team_id', $locked->team_id)->where('user_id', $actor->id)
                ->where('team_members.active', true)->where('teams.active', true)->exists();
            $lead = $this->hasRole($actor, [RoleName::Support, RoleName::Manager])
                || ($actor->hasRole(RoleName::Analyst) && ($member || $responsible));
            $canRespond = $lead || (($responsible || $participant) && $actor->hasRole(RoleName::Developer))
                || ($participant && $actor->hasRole(RoleName::Analyst));
            $this->check($active || in_array($action, ['reopen', 'decline_reopen', 'note'], true), 'A ação requer um chamado em andamento.');

            $input = array_map(fn ($value) => is_string($value) && trim($value) === '' ? null : $value, $input);
            $data = Validator::make($input, [
                'body' => ['nullable', 'string', 'max:10000'], 'reason' => ['nullable', 'string', 'max:2000'],
                'team_id' => ['nullable', 'integer'], 'user_id' => ['nullable', 'integer'],
                'category_id' => ['nullable', 'integer'], 'subcategory_id' => ['nullable', 'integer'], 'service_id' => ['nullable', 'integer'],
                'priority' => ['nullable', Rule::in(['P1', 'P2', 'P3', 'P4'])],
                'protocol' => ['nullable', 'string', 'max:255'], 'restoration' => ['nullable', 'string', 'max:10000'],
                'useful' => ['nullable', 'boolean'],
            ])->validate();
            $body = trim($data['body'] ?? '');
            $reason = trim($data['reason'] ?? '');
            $changes = [];
            $details = ['reason' => $reason];
            $summary = null;

            switch ($action) {
                case 'triage':
                    abort_unless($lead || $actor->hasRole(RoleName::Apprentice), 403);
                    $this->check($locked->status === 'novo', 'A triagem começa em um chamado novo.');
                    $changes['status'] = 'em_triagem';
                    break;
                case 'classify':
                    abort_unless($lead, 403);
                    $changes = $this->classification($data);
                    if (isset($data['priority']) && $data['priority'] !== $locked->priority) {
                        $this->check($reason !== '', 'Justifique a alteração de prioridade com impacto e urgência.');
                        $changes['priority'] = $data['priority'];
                    }
                    break;
                case 'start':
                    abort_unless($canRespond, 403);
                    $this->check($locked->status === 'em_triagem' && $locked->assignee_id !== null, 'Atribua um responsável antes de iniciar o atendimento.');
                    $changes['status'] = 'em_atendimento';
                    $summary = 'Atendimento iniciado.';
                    break;
                case 'assign':
                case 'claim':
                case 'collaborate':
                    abort_unless($lead, 403);
                    $team = $action === 'assign' ? ($data['team_id'] ?? null) : $locked->team_id;
                    $user = $action === 'claim' ? $actor->id : ($data['user_id'] ?? null);
                    $this->check($team && DB::table('teams')->where('id', $team)->where('active', true)->exists(), 'Selecione uma equipe ativa.');
                    if ($action === 'claim') {
                        $this->check($locked->assignee_id === null || $responsible, 'O chamado já possui responsável.');
                    } else {
                        $this->check($reason !== '', 'Informe a justificativa.');
                    }
                    if ($user) {
                        $user = (int) $user;
                        $target = User::lockForUpdate()->find($user);
                        $this->check($target && $target->canLoginLocally() && $target->hasVerifiedEmail()
                            && $this->hasRole($target, $action === 'collaborate' ? self::STAFF : [RoleName::Support, RoleName::Analyst, RoleName::Manager, RoleName::Developer])
                            && DB::table('team_members')->where('team_id', $team)->where('user_id', $user)->where('active', true)->exists()
                            && ((! $locked->restricted && ! self::securityCategory($locked->category_id))
                                || DB::table('ticket_access_grants')->where('ticket_id', $id)->where('user_id', $user)->exists()),
                            'Selecione um técnico elegível e autorizado da equipe.');
                    }
                    if ($action === 'collaborate') {
                        $this->check($user && $user !== $locked->assignee_id, 'Selecione um colaborador diferente do responsável.');
                        DB::table('ticket_participants')->insertOrIgnore(['ticket_id' => $id, 'user_id' => $user]);
                        $details['user_id'] = $user;
                        break;
                    }
                    if ($locked->team_id !== (int) $team) {
                        $details['participants_removed'] = DB::table('ticket_participants')->where('ticket_id', $id)->pluck('user_id');
                        DB::table('ticket_participants')->where('ticket_id', $id)->delete();
                    }
                    $changes = ['team_id' => $team, 'assignee_id' => $user];
                    if ($action === 'claim' && in_array($locked->status, ['novo', 'em_triagem'], true)) {
                        $changes['status'] = 'em_atendimento';
                    } elseif ($locked->status === 'novo') {
                        $changes['status'] = 'em_triagem';
                    }
                    break;
                case 'reply':
                case 'note':
                case 'wait_requester':
                    abort_unless($canRespond || ($action === 'note' && ($member || $participant || $responsible)), 403);
                    $this->check($body !== '', 'Escreva a mensagem.');
                    if ($action === 'wait_requester') {
                        $this->check($locked->status === 'em_atendimento', 'A espera começa durante o atendimento.');
                        $this->check($locked->priority !== 'P1' || ($actor->hasRole(RoleName::Manager) && $reason !== ''), 'Para P1, a espera exige exceção gerencial justificada.');
                        $changes['status'] = 'aguardando_solicitante';
                    }
                    $public = $action !== 'note';
                    $details['comment_id'] = self::post($id, $actor->id, $body, $public);
                    if ($public) {
                        $summary = $action === 'reply' ? 'O TI respondeu ao chamado.' : 'O TI solicitou informação indispensável.';
                        if (($data['useful'] ?? false) && ! $locked->first_response_at) {
                            $changes['first_response_at'] = now();
                        }
                    }
                    break;
                case 'wait_third':
                    abort_unless($canRespond, 403);
                    $this->check($locked->status === 'em_atendimento' && $body !== '' && trim($data['protocol'] ?? '') !== '', 'Informe dependência e protocolo durante o atendimento.');
                    $changes['status'] = 'aguardando_terceiro';
                    $details += ['dependency' => $body, 'protocol' => trim($data['protocol'])];
                    $summary = 'O chamado aguarda retorno de terceiro.';
                    break;
                case 'resume':
                    abort_unless($canRespond, 403);
                    $this->check(in_array($locked->status, ['aguardando_solicitante', 'aguardando_terceiro'], true) && $reason !== '', 'Informe o motivo para retomar o chamado em espera.');
                    $changes['status'] = 'em_atendimento';
                    $summary = 'Atendimento retomado.';
                    break;
                case 'resolve':
                    abort_unless($lead || ($responsible && $actor->hasRole(RoleName::Developer)), 403);
                    $assignee = $locked->assignee_id ? User::lockForUpdate()->find($locked->assignee_id) : null;
                    $this->check(in_array($locked->status, ['em_triagem', 'em_atendimento'], true) && $body !== '' && $assignee?->active,
                        'Informe a solução durante a triagem ou atendimento e mantenha um responsável ativo.');
                    $changes = $this->classification($data) + ['status' => 'resolvido'];
                    $details['restoration'] = trim($data['restoration'] ?? '');
                    $details['solution'] = $body;
                    $message = $details['restoration'] === '' ? $body : 'Restauração: '.$details['restoration']."\n\n".$body;
                    $details['comment_id'] = self::post($id, $actor->id, $message, true);
                    if (($data['useful'] ?? false) && ! $locked->first_response_at) {
                        $changes['first_response_at'] = now();
                    }
                    $summary = 'Solução registrada pelo TI.';
                    break;
                case 'cancel':
                    abort_unless($lead, 403);
                    $this->check($reason !== '', 'Informe o motivo do cancelamento.');
                    $changes['status'] = 'cancelado';
                    $details['comment_id'] = self::post($id, $actor->id, $reason, true);
                    $summary = 'Chamado cancelado.';
                    break;
                case 'reopen':
                case 'decline_reopen':
                    abort_unless($lead || ($responsible && $actor->hasRole(RoleName::Developer)), 403);
                    $this->check(in_array($locked->status, ['resolvido', 'fechado'], true) && $locked->reopen_requested_at && $reason !== '', 'Valide uma solicitação do mesmo problema e informe o motivo.');
                    if ($action === 'decline_reopen') {
                        $changes = ['reopen_requested_at' => null];
                        $details['comment_id'] = self::post($id, $actor->id, $reason, true);
                        $summary = 'O TI recusou a reabertura. Consulte o motivo na conversa.';
                        break;
                    }
                    $changes = ['status' => 'em_atendimento', 'cycle' => $locked->cycle + 1, 'reopen_requested_at' => null, 'first_response_at' => null];
                    $summary = 'O TI validou a reabertura do mesmo problema.';
                    break;
                default:
                    abort(422);
            }

            if (isset($changes['category_id']) && self::securityCategory((int) $changes['category_id'])) {
                $changes['restricted'] = true;
            }
            self::record($locked, $actor->id, 'ticket.'.$action, $changes, $details, $summary);
        }, 3);
    }

    public function requesterAction(User $actor, int $id, int $version, string $action, string $reason): void
    {
        app(PortalAccess::class)->own($actor, $id);
        DB::transaction(function () use ($actor, $id, $version, $action, $reason) {
            $ticket = DB::table('tickets')->where('id', $id)->lockForUpdate()->firstOrFail();
            app(PortalAccess::class)->own($actor, $id);
            $this->check($ticket->lock_version === $version, 'O chamado foi atualizado. Recarregue antes de tentar novamente.');
            if ($action === 'confirm') {
                $this->check($ticket->status === 'resolvido' && ! $ticket->reopen_requested_at, 'A confirmação exige uma solução sem pedido de reabertura pendente.');
                self::record($ticket, $actor->id, 'ticket.confirmed', ['status' => 'fechado'], [], 'Solução confirmada pelo solicitante.');

                return;
            }
            abort_unless($action === 'request_reopen', 422);
            $this->check(in_array($ticket->status, ['resolvido', 'fechado'], true) && ! $ticket->reopen_requested_at, 'A solicitação exige um chamado resolvido ou fechado, sem pedido pendente.');
            $reason = trim($reason);
            $this->check($reason !== '' && mb_strlen($reason) <= 10000, 'Descreva como o mesmo problema voltou.');
            $comment = self::post($id, $actor->id, $reason, true);
            self::record($ticket, $actor->id, 'ticket.reopen_requested', ['reopen_requested_at' => now()], ['comment_id' => $comment], 'O solicitante pediu reabertura.');
        }, 3);
    }

    private function classification(array $data): array
    {
        $category = $data['category_id'] ?? null;
        $subcategory = $data['subcategory_id'] ?? null;
        $service = $data['service_id'] ?? null;
        $this->check($category && DB::table('categories')->where('id', $category)->where('active', true)->exists(), 'Selecione uma categoria ativa.');
        if ($subcategory) {
            $this->check(DB::table('subcategories')->where('id', $subcategory)->where('category_id', $category)->where('active', true)->exists(), 'Selecione uma subcategoria compatível.');
        }
        if ($service) {
            $this->check(DB::table('services')->where('id', $service)->where('active', true)
                ->where(fn ($q) => $q->whereNull('category_id')->orWhere('category_id', $category))
                ->where(fn ($q) => $q->whereNull('subcategory_id')->orWhere('subcategory_id', $subcategory))->exists(),
                'Selecione um serviço compatível.');
        }

        return ['category_id' => $category, 'subcategory_id' => $subcategory, 'service_id' => $service];
    }

    private function check(bool $condition, string $message): void
    {
        if (! $condition) {
            throw ValidationException::withMessages(['operation' => $message]);
        }
    }

    private static function post(int $ticket, int $actor, string $body, bool $public): int
    {
        return DB::table('comments')->insertGetId([
            'ticket_id' => $ticket, 'author_id' => $actor, 'body' => $body,
            'visibility' => $public ? 'public' : 'internal', 'idempotency_key' => (string) Str::uuid(),
            'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    public static function record(object $ticket, int $actor, string $type, array $changes, array $details, ?string $summary): void
    {
        $instant = now();
        DB::table('tickets')->where('id', $ticket->id)->update($changes + [
            'updated_at' => $instant, 'lock_version' => $ticket->lock_version + 1,
        ]);
        DB::table('ticket_events')->insert([
            'ticket_id' => $ticket->id, 'actor_id' => $actor, 'event_type' => $type,
            'visibility' => $summary === null ? 'internal' : 'public', 'public_summary' => $summary,
            'details' => json_encode($details + ['cycle' => $changes['cycle'] ?? $ticket->cycle,
                'before' => array_intersect_key((array) $ticket, $changes), 'after' => $changes], JSON_THROW_ON_ERROR),
            'occurred_at' => $instant,
        ]);
    }
}
