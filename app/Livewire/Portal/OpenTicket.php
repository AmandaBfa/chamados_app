<?php

namespace App\Livewire\Portal;

use App\Services\PortalAccess;
use App\Services\RequesterTickets;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Livewire\Attributes\Locked;
use Livewire\Component;

class OpenTicket extends Component
{
    use PortalUploads;

    #[Locked]
    public string $submissionKey;

    public array $form = ['subject' => '', 'description' => '', 'category_id' => null, 'subcategory_id' => null,
        'service_id' => null, 'equipment_reference_id' => null, 'impact_scope' => '', 'affected_process' => '',
        'work_condition' => '', 'started_unknown' => true, 'started_at' => null];

    public bool $identifyResource = false;

    public array $files = [];

    public function mount(): void
    {
        app(PortalAccess::class)->authorize(auth()->user());
        $this->submissionKey = (string) Str::uuid();
    }

    public function updatedFormCategoryId(): void
    {
        $this->form['subcategory_id'] = null;
        $this->form['service_id'] = null;
    }

    public function updatedFormSubcategoryId(): void
    {
        $this->form['service_id'] = null;
    }

    public function updatedIdentifyResource(): void
    {
        if (! $this->identifyResource) {
            $this->form['service_id'] = null;
            $this->form['equipment_reference_id'] = null;
        }
    }

    public function updatedFormStartedUnknown(): void
    {
        if ($this->form['started_unknown']) {
            $this->form['started_at'] = null;
        }
    }

    public function submit(RequesterTickets $service): void
    {
        $ticket = $service->open(auth()->user(), $this->form, $this->submissionKey, $this->files);
        session()->flash('status', 'Chamado registrado. Seu protocolo é '.$ticket->protocol.'.');
        $this->redirectRoute('portal.show', ['ticket' => $ticket->id]);
    }

    public function render()
    {
        app(PortalAccess::class)->authorize(auth()->user());
        $category = filter_var($this->form['category_id'] ?? null, FILTER_VALIDATE_INT) ?: 0;
        $sub = filter_var($this->form['subcategory_id'] ?? null, FILTER_VALIDATE_INT) ?: 0;

        return view('livewire.portal.open-ticket', [
            'categories' => DB::table('categories')->where('active', true)->orderBy('name')->get(['id', 'name']),
            'subcategories' => DB::table('subcategories')->where('active', true)->where('category_id', $category)
                ->whereIn('category_id', DB::table('categories')->select('id')->where('active', true))->orderBy('name')->get(['id', 'name']),
            'services' => DB::table('services')->where('active', true)->where(fn ($q) => $q->whereNull('category_id')->orWhere('category_id', $category))
                ->where(fn ($q) => $q->whereNull('subcategory_id')->orWhere('subcategory_id', $sub))->orderBy('name')->get(['id', 'name']),
            'equipment' => DB::table('equipment_references')->where('active', true)->orderBy('name')->get(['id', 'name']),
            'unit' => auth()->user()->unit?->name, 'department' => auth()->user()->department?->name,
        ]);
    }
}
