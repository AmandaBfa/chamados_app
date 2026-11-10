<?php

namespace App\Livewire\Ti;

use App\Services\TicketOperations;
use Illuminate\Support\Facades\DB;
use Livewire\Component;
use Livewire\WithPagination;

class Queue extends Component
{
    use WithPagination;

    public string $search = '';

    public array $filters = [];

    public function updated(): void
    {
        $this->resetPage();
    }

    public function render()
    {
        $query = app(TicketOperations::class)->queue(auth()->user());
        $choices = ['status' => TicketOperations::STATES, 'priority' => array_combine(['P1', 'P2', 'P3', 'P4'], ['P1', 'P2', 'P3', 'P4'])];
        foreach (['team_id' => 'teams', 'assignee_id' => 'users', 'category_id' => 'categories', 'service_id' => 'services', 'unit_id' => 'units'] as $field => $table) {
            $choices[$field] = DB::table($table)->whereIn('id', (clone $query)->select($field))->orderBy('name')->pluck('name', 'id')->all();
        }
        foreach ($choices as $field => $options) {
            $value = $this->filters[$field] ?? '';
            if (is_scalar($value) && array_key_exists($value, $options)) {
                $query->where($field, $value);
            }
        }
        $term = trim(mb_substr($this->search, 0, 160));
        if ($term !== '') {
            $pattern = '%'.addcslashes($term, '%_\\').'%';
            $query->where(fn ($q) => $q->where('protocol', 'ilike', $pattern)->orWhere('subject', 'ilike', $pattern));
        }

        return view('livewire.ti.queue', [
            'choices' => $choices,
            'tickets' => $query->select('tickets.*')->selectRaw('exists (select 1 from users where users.id = tickets.assignee_id and not users.active) as inactive_assignee')
                ->orderByRaw('priority asc nulls last')->orderBy('created_at')->orderBy('id')->paginate(20),
        ]);
    }
}
