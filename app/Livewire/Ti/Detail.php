<?php

namespace App\Livewire\Ti;

use App\Services\TicketOperations;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Locked;
use Livewire\Component;

class Detail extends Component
{
    #[Locked]
    public int $ticketId;

    #[Locked]
    public int $version;

    public string $action = '';

    public array $input = [];

    public function mount(int $ticketId, TicketOperations $service): void
    {
        $this->ticketId = $ticketId;
        $ticket = $service->ticket(auth()->user(), $ticketId);
        $this->version = $ticket->lock_version;
        $this->input = array_intersect_key((array) $ticket, array_flip(['category_id', 'subcategory_id', 'service_id', 'team_id']));
    }

    public function save(TicketOperations $service): void
    {
        $service->act(auth()->user(), $this->ticketId, $this->version, $this->action, $this->input);
        session()->flash('status', 'Operação registrada.');
        if ($service->queue(auth()->user())->where('id', $this->ticketId)->exists()) {
            $this->redirectRoute('ti.show', ['ticket' => $this->ticketId]);
        } else {
            $this->redirectRoute('ti.index');
        }
    }

    public function chooseAction(string $action, TicketOperations $service): void
    {
        $actor = auth()->user();
        $ticket = $service->ticket($actor, $this->ticketId);
        abort_unless(isset($service->availableActions($actor, $ticket)[$action]), 403);

        $this->resetValidation();
        $this->action = $action;
        $this->input = array_intersect_key((array) $ticket, array_flip(['category_id', 'subcategory_id', 'service_id', 'team_id']));
        if ($action === 'take_triage') {
            $teams = $service->triageTeams($actor, $ticket);
            if (count($teams) === 1) {
                $this->input['team_id'] = array_key_first($teams);
                $this->save($service);
            }
        } elseif (in_array($action, ['triage', 'start'], true)) {
            $this->save($service);
        }
    }

    public function updatedAction(): void
    {
        foreach (['body', 'reason', 'useful', 'protocol', 'restoration', 'priority', 'user_id'] as $field) {
            unset($this->input[$field]);
        }
    }

    public function updatedInputCategoryId(): void
    {
        $this->input['subcategory_id'] = null;
        $this->input['service_id'] = null;
    }

    public function updatedInputSubcategoryId(): void
    {
        $this->input['service_id'] = null;
    }

    public function updatedInputTeamId(): void
    {
        $this->input['user_id'] = null;
    }

    public function render()
    {
        $actor = auth()->user();
        $service = app(TicketOperations::class);
        $ticket = $service->ticket($actor, $this->ticketId);
        $choices = [];
        foreach (['category_id' => 'categories', 'team_id' => 'teams'] as $field => $table) {
            $choices[$field] = DB::table($table)->where('active', true)->orderBy('name')->pluck('name', 'id');
        }
        $category = filter_var($this->input['category_id'] ?? null, FILTER_VALIDATE_INT) ?: 0;
        $subcategory = filter_var($this->input['subcategory_id'] ?? null, FILTER_VALIDATE_INT) ?: 0;
        $choices['subcategory_id'] = DB::table('subcategories')->where('active', true)->where('category_id', $category)->orderBy('name')->pluck('name', 'id');
        $choices['service_id'] = DB::table('services')->where('active', true)
            ->where(fn ($q) => $q->whereNull('category_id')->orWhere('category_id', $category))
            ->where(fn ($q) => $q->whereNull('subcategory_id')->orWhere('subcategory_id', $subcategory))->orderBy('name')->pluck('name', 'id');
        if ($this->action === 'take_triage') {
            $choices['team_id'] = $service->triageTeams($actor, $ticket);
        }
        $team = in_array($this->action, ['assign', 'forward'], true) ? ($this->input['team_id'] ?? null) : $ticket->team_id;
        $choices['user_id'] = filter_var($team, FILTER_VALIDATE_INT) ? DB::table('users')
            ->join('team_members', 'team_members.user_id', '=', 'users.id')->where('team_id', $team)
            ->where('users.active', true)->where('team_members.active', true)
            ->when($ticket->restricted || TicketOperations::securityCategory($ticket->category_id),
                fn ($q) => $q->whereIn('users.id', DB::table('ticket_access_grants')->where('ticket_id', $ticket->id)->select('user_id')))
            ->orderBy('users.name')->pluck('users.name', 'users.id') : [];

        return view('livewire.ti.detail', [
            'ticket' => $ticket, 'choices' => $choices,
            'actions' => $service->availableActions($actor, $ticket),
            'responsibleName' => $ticket->assignee_id ? DB::table('users')->where('id', $ticket->assignee_id)->value('name') : null,
            'requester' => DB::table('users')->where('id', $ticket->requester_id)->first(['name', 'email']),
            'openingContext' => json_decode($ticket->opening_context, true, 512, JSON_THROW_ON_ERROR),
            'comments' => DB::table('comments')->join('users', 'users.id', '=', 'comments.author_id')
                ->where('ticket_id', $ticket->id)->orderBy('comments.id')->get(['comments.body', 'comments.visibility', 'users.name']),
            'events' => DB::table('ticket_events')->join('users', 'users.id', '=', 'ticket_events.actor_id')
                ->where('ticket_id', $ticket->id)->orderBy('ticket_events.id')->get(['ticket_events.*', 'users.name']),
            'attachments' => DB::table('attachments')->where('ticket_id', $ticket->id)->get(['id', 'download_name']),
        ]);
    }
}
