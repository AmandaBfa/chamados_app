<?php

namespace App\Livewire\Portal;

use App\Services\PortalAccess;
use App\Services\RequesterTickets;
use App\Services\TicketOperations;
use Illuminate\Support\Str;
use Livewire\Attributes\Locked;
use Livewire\Component;

class TicketDetail extends Component
{
    use PortalUploads;

    #[Locked]
    public int $ticketId;

    #[Locked]
    public string $commentKey;

    #[Locked]
    public int $version;

    public string $reopenReason = '';

    public string $body = '';

    public array $files = [];

    public function mount(int $ticketId): void
    {
        $this->version = app(PortalAccess::class)->own(auth()->user(), $ticketId)->lock_version;
        $this->ticketId = $ticketId;
        $this->commentKey = (string) Str::uuid();
    }

    public function solution(string $action, TicketOperations $service): void
    {
        $service->requesterAction(auth()->user(), $this->ticketId, $this->version, $action, $this->reopenReason);
        $this->redirectRoute('portal.show', ['ticket' => $this->ticketId]);
    }

    public function comment(RequesterTickets $service): void
    {
        $service->comment(auth()->user(), $this->ticketId, $this->body, $this->commentKey, $this->files);
        $this->reset('body', 'files');
        $this->commentKey = (string) Str::uuid();
        $this->version = app(PortalAccess::class)->own(auth()->user(), $this->ticketId)->lock_version;
        session()->flash('status', 'Comentário público registrado.');
    }

    public function render()
    {
        return view('livewire.portal.ticket-detail', app(RequesterTickets::class)->detail(auth()->user(), $this->ticketId));
    }
}
