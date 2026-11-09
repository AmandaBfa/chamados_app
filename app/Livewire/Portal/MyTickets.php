<?php

namespace App\Livewire\Portal;

use App\Services\PortalAccess;
use App\Services\RequesterTickets;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Livewire\Component;
use Livewire\WithPagination;

class MyTickets extends Component
{
    use WithPagination;

    public string $search = '';

    public string $status = '';

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function updatedStatus(): void
    {
        $this->resetPage();
    }

    public function render()
    {
        app(PortalAccess::class)->authorize(auth()->user());
        $this->validate(['search' => ['string', 'max:160'], 'status' => ['nullable', Rule::in(array_keys(RequesterTickets::STATUSES))]]);
        $query = DB::table('tickets')->where('requester_id', auth()->id());
        if ($this->search !== '') {
            $query->where(fn ($q) => $q->where('subject', 'ilike', '%'.$this->search.'%')->orWhere('protocol', 'ilike', '%'.$this->search.'%'));
        }
        if ($this->status !== '') {
            $query->where('status', $this->status);
        }

        return view('livewire.portal.my-tickets', ['tickets' => $query->orderByDesc('created_at')->orderByDesc('id')->paginate(10, ['id', 'protocol', 'subject', 'status', 'created_at']), 'statuses' => RequesterTickets::STATUSES]);
    }
}
