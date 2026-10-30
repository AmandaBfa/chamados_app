<?php

namespace App\Livewire\Admin;

use App\Enums\RoleName;
use App\Models\User;
use App\Services\AdminUserService;
use Illuminate\Support\Facades\Gate;
use Livewire\Component;
use Livewire\WithPagination;

class UsersPanel extends Component
{
    use WithPagination;

    public string $inviteName = '';

    public string $inviteEmail = '';

    public array $inviteRoles = ['colaborador'];

    public string $inviteReason = '';

    public ?int $selectedId = null;

    public string $name = '';

    public bool $active = true;

    public array $selectedRoles = [];

    public string $reason = '';

    public function createInvite(AdminUserService $service): void
    {
        $service->invite(auth()->user(), ['name' => $this->inviteName, 'email' => $this->inviteEmail,
            'roles' => $this->inviteRoles, 'reason' => $this->inviteReason]);
        $this->reset('inviteName', 'inviteEmail', 'inviteRoles', 'inviteReason');
        session()->flash('status', 'Conta autorizada. Convite enviado; se não chegar, utilize o reenvio.');
    }

    public function edit(int $id): void
    {
        Gate::authorize('users.view');
        $user = User::findOrFail($id);
        $this->selectedId = $user->id;
        $this->name = $user->name;
        $this->active = $user->active;
        $this->selectedRoles = $user->roles()->pluck('name')->all();
        $this->reason = '';
        $this->resetValidation();
    }

    public function saveUser(AdminUserService $service): void
    {
        abort_unless($this->selectedId !== null, 422);
        $service->update(auth()->user(), $this->selectedId, ['name' => $this->name, 'active' => $this->active,
            'roles' => $this->selectedRoles, 'reason' => $this->reason]);
        $actor = auth()->user()->refresh();
        if ($actor->auth_version !== (int) session('auth.version', -1)) {
            auth()->logout();
            session()->invalidate();
            session()->regenerateToken();
            $this->skipRender();
            $this->redirectRoute('login');

            return;
        }
        $this->selectedId = null;
        session()->flash('status', 'Alteração registrada. Mudanças de acesso encerram as sessões anteriores.');
    }

    public function reissueInvite(int $id, AdminUserService $service): void
    {
        $service->reissue(auth()->user(), $id, $this->reason);
        session()->flash('status', 'Novo convite enviado. O link anterior foi invalidado.');
    }

    public function render()
    {
        Gate::authorize('users.view');

        return view('livewire.admin.users-panel', [
            'users' => User::with('roles')->orderBy('name')->paginate(10),
            'availableRoles' => RoleName::cases(),
        ]);
    }
}
