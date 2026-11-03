<?php

namespace App\Livewire\Admin;

use App\Services\CatalogDefinition;
use App\Services\CatalogService;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Locked;
use Livewire\Component;
use Livewire\WithPagination;

class CatalogPanel extends Component
{
    use WithPagination;

    #[Locked]
    public string $kind;

    #[Locked]
    public ?int $selectedId = null;

    #[Locked]
    public ?int $version = null;

    public array $form = [];

    public string $reason = '';

    public string $search = '';

    public string $statusFilter = 'all';

    public function mount(string $kind): void
    {
        $this->kind = $kind;
        CatalogDefinition::get($kind);
        $this->newRecord();
    }

    public function newRecord(): void
    {
        app(CatalogService::class)->authorize(auth()->user());
        $this->selectedId = null;
        $this->version = null;
        $this->reason = '';
        $this->form = [];
        foreach (CatalogDefinition::get($this->kind)['fields'] as $field => $label) {
            $this->form[$field] = in_array($field, CatalogDefinition::booleans(), true) ? false : null;
        }
        if ($this->kind !== 'users') {
            $this->form['active'] = true;
        }
        $this->resetValidation();
    }

    public function edit(int $id): void
    {
        app(CatalogService::class)->authorize(auth()->user());
        $row = DB::table($this->kind)->find($id);
        abort_unless($row, 404);
        $this->newRecord();
        $this->selectedId = $id;
        $this->version = $this->kind === 'users' ? $row->organization_version : $row->version;
        foreach ($this->form as $field => $value) {
            $this->form[$field] = $row->$field;
        }
    }

    public function updatedFormCategoryId(): void
    {
        if ($this->kind === 'services') {
            $this->form['subcategory_id'] = null;
        }
    }

    public function updatedFormTeamId(): void
    {
        if ($this->kind === 'services') {
            $this->form['reference_user_id'] = null;
        }
    }

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function updatedStatusFilter(): void
    {
        $this->resetPage();
    }

    public function save(CatalogService $service): void
    {
        $service->save(auth()->user(), $this->kind, $this->selectedId, $this->form, $this->version, $this->reason);
        $this->newRecord();
        session()->flash('status', 'Cadastro salvo e alteração registrada na auditoria.');
    }

    public function render()
    {
        app(CatalogService::class)->authorize(auth()->user());
        $definition = CatalogDefinition::get($this->kind);
        $options = [];
        foreach ($definition['fields'] as $field => $label) {
            $table = CatalogDefinition::relations()[$field] ?? null;
            if (! $table) {
                continue;
            }
            $query = DB::table($table)->where('active', true);
            if ($table === 'subcategories') {
                $query->where('category_id', filter_var($this->form['category_id'] ?? null, FILTER_VALIDATE_INT) ?: 0)
                    ->whereIn('category_id', DB::table('categories')->select('id')->where('active', true));
            }
            if (in_array($field, ['reference_user_id', 'substitute_user_id'], true)) {
                $team = $this->kind === 'teams' ? $this->selectedId : ($this->form['team_id'] ?? null);
                $query->whereIn('id', DB::table('team_members')->select('user_id')->where('team_id', filter_var($team, FILTER_VALIDATE_INT) ?: 0)->where('active', true));
            }
            // Mostra o vínculo histórico selecionado, sem oferecer outros inativos.
            $current = $this->selectedId ? DB::table($this->kind)->where('id', $this->selectedId)->value($field) : null;
            if ($current) {
                $query = DB::table($table)->whereIn('id', $query->select('id'))->orWhere('id', $current);
            }
            $options[$field] = $query->orderBy('name')->get(['id', 'name', 'active']);
        }
        $rows = DB::table($this->kind);
        if ($this->kind === 'team_members') {
            $rows->join('teams', 'teams.id', '=', 'team_members.team_id')->join('users', 'users.id', '=', 'team_members.user_id')
                ->select('team_members.*', DB::raw("teams.name || ' · ' || users.name AS name"))
                ->where(fn ($q) => $q->where('teams.name', 'ilike', '%'.$this->search.'%')->orWhere('users.name', 'ilike', '%'.$this->search.'%'));
        } else {
            $rows->where('name', 'ilike', '%'.$this->search.'%');
        }
        if (in_array($this->statusFilter, ['active', 'inactive'], true)) {
            $rows->where($this->kind.'.active', $this->statusFilter === 'active');
        }

        return view('livewire.admin.catalog-panel', [
            'definition' => $definition, 'options' => $options,
            'rows' => $rows->orderBy('name')->paginate(10),
        ]);
    }
}
