<?php

namespace App\Services;

use App\Enums\RoleName;
use App\Models\Invitation;
use App\Models\Role;
use App\Models\User;
use App\Notifications\UserInvitation;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class AdminUserService
{
    private function authorize(User $actor, string $ability): void
    {
        Gate::forUser($actor)->authorize($ability);
        abort_if(time() - (int) session('auth.password_confirmed_at', 0) > config('auth.password_timeout'), 403, 'Confirme sua senha novamente.');
    }

    public function invite(User $actor, array $input): User
    {
        $this->authorize($actor, 'users.create');
        $this->authorize($actor, 'roles.assign');
        $input['email'] = mb_strtolower(trim($input['email'] ?? ''));
        $data = Validator::make($input, [
            'name' => ['required', 'string', 'max:160'],
            'email' => ['required', 'email:rfc', 'max:254', 'unique:users,email'],
            'roles' => ['required', 'array', 'min:1'],
            'roles.*' => ['required', 'distinct', Rule::enum(RoleName::class)],
            'reason' => ['required', 'string', 'min:8', 'max:500'],
        ])->validate();

        return DB::transaction(function () use ($actor, $data) {
            Role::where('name', RoleName::Administrator->value)->lockForUpdate()->firstOrFail();
            $user = User::create(['name' => $data['name'], 'email' => $data['email']]);
            $user->roles()->sync(Role::whereIn('name', $data['roles'])->pluck('id'));
            AccessAudit::record('user.invited', $actor, $user, ['reason' => $data['reason'], 'after_roles' => $data['roles']]);
            $this->issueInvitation($actor, $user);

            return $user;
        });
    }

    public function reissue(User $actor, int $userId, string $reason): void
    {
        $this->authorize($actor, 'users.create');
        Validator::make(['reason' => $reason], ['reason' => ['required', 'string', 'min:8', 'max:500']])->validate();
        DB::transaction(function () use ($actor, $userId, $reason) {
            $user = User::lockForUpdate()->findOrFail($userId);
            if (! $user->active || $user->password !== null) {
                throw ValidationException::withMessages(['reason' => 'Só é possível reenviar convite para uma conta ativa ainda não aceita.']);
            }
            Invitation::where('user_id', $user->id)->whereNull('consumed_at')->update(['consumed_at' => now()]);
            $this->issueInvitation($actor, $user);
            AccessAudit::record('invitation.reissued', $actor, $user, ['reason' => $reason]);
        });
    }

    private function issueInvitation(User $actor, User $user): void
    {
        $token = Str::random(64);
        Invitation::create(['user_id' => $user->id, 'created_by' => $actor->id,
            'token_hash' => hash('sha256', $token), 'expires_at' => now()->addHours(config('security.invitation_hours'))]);
        DB::afterCommit(fn () => $user->notify(new UserInvitation($token)));
    }

    public function update(User $actor, int $userId, array $input): void
    {
        $this->authorize($actor, 'users.update');
        $this->authorize($actor, 'roles.assign');
        $data = Validator::make($input, [
            'name' => ['required', 'string', 'max:160'], 'active' => ['required', 'boolean'],
            'roles' => ['required', 'array', 'min:1'],
            'roles.*' => ['required', 'distinct', Rule::enum(RoleName::class)],
            'reason' => ['required', 'string', 'min:8', 'max:500'],
        ])->validate();
        DB::transaction(function () use ($actor, $userId, $data) {
            // Uma mesma linha serializa concessões/remoções para proteger o último administrador.
            Role::where('name', RoleName::Administrator->value)->lockForUpdate()->firstOrFail();
            $user = User::lockForUpdate()->findOrFail($userId);
            $before = $user->roles()->pluck('name')->sort()->values()->all();
            $after = collect($data['roles'])->sort()->values()->all();
            $removingAdministrator = $user->active && in_array('administrador', $before, true) &&
                (! $data['active'] || ! in_array('administrador', $after, true));
            if ($removingAdministrator) {
                $other = User::whereKeyNot($user->id)->where('active', true)->whereNotNull('password')
                    ->whereNotNull('email_verified_at')
                    ->whereHas('roles', fn ($query) => $query->where('name', 'administrador'))->exists();
                if (! $other) {
                    throw ValidationException::withMessages(['active' => 'Mantenha outro administrador ativo, com convite aceito e e-mail confirmado, antes desta alteração.']);
                }
            }
            $beforeActive = $user->active;
            $changedAccess = $before !== $after || $user->active !== (bool) $data['active'];
            $user->name = $data['name'];
            $user->active = (bool) $data['active'];
            if ($changedAccess) {
                $user->auth_version++;
                $user->remember_token = Str::random(60);
            }
            $user->save();
            $user->roles()->sync(Role::whereIn('name', $after)->pluck('id'));
            if ($changedAccess) {
                DB::table('sessions')->where('user_id', $user->id)->delete();
                DB::table('password_reset_tokens')->where('email', $user->email)->delete();
                Invitation::where('user_id', $user->id)->whereNull('consumed_at')->update(['consumed_at' => now()]);
            }
            AccessAudit::record('user.access_updated', $actor, $user, [
                'reason' => $data['reason'], 'before_roles' => $before, 'after_roles' => $after,
                'before_active' => $beforeActive, 'after_active' => $user->active,
            ]);
        });
    }
}
