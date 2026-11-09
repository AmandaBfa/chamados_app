<?php

namespace App\Livewire\Portal;

use App\Services\PortalAccess;
use App\Services\RequesterTickets;
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

    public string $body = '';

    public array $files = [];

    public function mount(int $ticketId): void
    {
        app(PortalAccess::class)->own(auth()->user(), $ticketId);
        $this->ticketId = $ticketId;
        $this->commentKey = (string) Str::uuid();
    }

    public function comment(RequesterTickets $service): void
    {
        $service->comment(auth()->user(), $this->ticketId, $this->body, $this->commentKey, $this->files);
        $this->reset('body', 'files');
        $this->commentKey = (string) Str::uuid();
        session()->flash('status', 'Comentário público registrado.');
    }

    public function render()
    {
        return view('livewire.portal.ticket-detail', app(RequesterTickets::class)->detail(auth()->user(), $this->ticketId));
    }
}
