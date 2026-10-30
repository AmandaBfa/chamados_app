<?php

namespace App\Livewire;

use Illuminate\Contracts\View\View;
use Livewire\Component;

class FoundationCheck extends Component
{
    public bool $verified = false;

    public function verify(): void
    {
        $this->verified = true;
    }

    public function render(): View
    {
        return view('livewire.foundation-check');
    }
}
