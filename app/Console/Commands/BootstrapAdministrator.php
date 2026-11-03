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
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;

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
        ], [
            'email.ends_with' => 'O e-mail fictício precisa terminar em .test, por exemplo admin@example.test.',
            'email.unique' => 'Esse e-mail já está cadastrado. Use outro e-mail fictício ou revise a conta existente.',
            'password.required' => 'A senha ficou vazia. Digite-a mesmo que os caracteres não apareçam na tela.',
            'password.confirmed' => 'As duas senhas digitadas não são iguais. Digite a mesma senha nas duas perguntas.',
        ]);
        if ($validator->fails()) {
            $this->error('Não foi possível criar a conta. Corrija os itens abaixo:');
            foreach ($validator->errors()->all() as $message) {
                $this->line(' - '.$message);
            }
            $this->warn('Nenhuma conta criada. Execute php artisan auth:bootstrap para tentar novamente.');

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
        try {
            $user->sendEmailVerificationNotification();
        } catch (TransportExceptionInterface $exception) {
            $this->warn('A conta foi criada, mas não foi possível enviar o e-mail de confirmação.');
            $this->line('Inicie a captura local com: & .\scripts\iniciar-mailpit.ps1');
            $this->line('Depois entre no sistema e use Reenviar confirmação. Não repita o cadastro.');
            $this->line('Desative AUTH_BOOTSTRAP_ENABLED após a inicialização.');

            return self::FAILURE;
        }
        $this->info('Conta criada sem privilégios operacionais até confirmar e-mail e MFA. Confira a captura local.');
        $this->info('Desative AUTH_BOOTSTRAP_ENABLED após a inicialização.');

        return self::SUCCESS;
    }
}
