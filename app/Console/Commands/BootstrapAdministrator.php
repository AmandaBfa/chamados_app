<?php

namespace App\Console\Commands;

use App\Enums\RoleName;
use App\Models\Role;
use App\Models\User;
use App\Services\AccessAudit;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rules\Password;

class BootstrapAdministrator extends Command
{
    protected $signature = 'auth:bootstrap';

    protected $description = 'Cria o primeiro administrador local com senha informada de forma oculta.';

    public function handle(): int
    {
        if (! app()->environment(['local', 'testing']) || ! config('security.bootstrap_enabled')) {
            $this->error('Inicialização desabilitada. Permitida somente em local/testing com AUTH_BOOTSTRAP_ENABLED=true.');

            return self::FAILURE;
        }
        if (User::whereHas('roles', fn ($q) => $q->where('name', 'administrador'))->exists()) {
            $this->error('Já existe administrador. Use o fluxo administrativo autenticado.');

            return self::FAILURE;
        }
        $name = $this->ask('Nome fictício do administrador');
        $email = mb_strtolower(trim((string) $this->ask('E-mail fictício (domínio .test)')));
        $password = $this->secret('Senha (mínimo 12 caracteres, maiúsculas, minúsculas e números)');
        $confirmation = $this->secret('Repita a senha');
        $validator = Validator::make(['name' => $name, 'email' => $email, 'password' => $password, 'password_confirmation' => $confirmation], [
            'name' => ['required', 'string', 'max:160'], 'email' => ['required', 'email:rfc', 'max:254', 'ends_with:.test', 'unique:users,email'],
            'password' => ['required', 'confirmed', Password::min(12)->mixedCase()->numbers()],
        ]);
        if ($validator->fails()) {
            $this->error('Dados inválidos. Confira nome, e-mail .test e requisitos da senha. Nenhuma conta criada.');

            return self::FAILURE;
        }
        $user = DB::transaction(function () use ($name, $email, $password) {
            $role = Role::where('name', RoleName::Administrator->value)->lockForUpdate()->firstOrFail();
            abort_if(User::whereHas('roles', fn ($q) => $q->where('name', 'administrador'))->exists(), 409);
            $user = new User(['name' => $name, 'email' => $email]);
            $user->forceFill(['password' => $password])->save();
            $user->roles()->attach($role);
            AccessAudit::record('admin.bootstrapped', null, $user, ['source' => 'local-console']);

            return $user;
        });
        $user->sendEmailVerificationNotification();
        $this->info('Conta criada sem privilégios operacionais até confirmar e-mail e MFA. Confira a captura local.');
        $this->info('Desative AUTH_BOOTSTRAP_ENABLED após a inicialização.');

        return self::SUCCESS;
    }
}
