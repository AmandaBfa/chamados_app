<?php

namespace Tests\Feature;

use App\Enums\RoleName;
use App\Livewire\Admin\UsersPanel;
use App\Models\Invitation;
use App\Models\Role;
use App\Models\User;
use App\Notifications\UserInvitation;
use App\Services\AdminUserService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;
use Laravel\Fortify\Fortify;
use Livewire\Livewire;
use Livewire\Mechanisms\HandleRequests\HandleRequests;
use PragmaRX\Google2FA\Google2FA;
use Tests\TestCase;

class AccessControlTest extends TestCase
{
    use DatabaseTransactions {
        beginDatabaseTransaction as private beginDedicatedTransaction;
    }

    public function beginDatabaseTransaction(): void
    {
        // Antes de QUALQUER escrita de teste, recusar outro banco.
        $this->assertSame('pgsql', config('database.default'));
        $this->assertSame('chamados_test', config('database.connections.pgsql.database'));
        $this->assertEmpty(config('database.connections.pgsql.url'));
        $identity = DB::selectOne('SELECT current_database() AS database, current_user AS username');
        $this->assertSame('chamados_test', $identity->database);
        $this->assertSame('chamados_test', $identity->username);
        $this->beginDedicatedTransaction();
    }

    protected function setUp(): void
    {
        parent::setUp();
        Notification::fake();
    }

    private function user(array $attributes = []): User
    {
        return User::factory()->create($attributes)->fresh();
    }

    private function login(User $user): void
    {
        $this->post('/login', ['email' => $user->email, 'password' => 'Somente-testes-2026!'])->assertRedirect('/inicio');
    }

    private function admin(bool $mfa = true): User
    {
        $user = $this->user();
        $user->roles()->sync([Role::where('name', RoleName::Administrator->value)->firstOrFail()->id]);
        if ($mfa) {
            $secret = (new Google2FA)->generateSecretKey();
            $user->forceFill([
                'two_factor_secret' => Fortify::currentEncrypter()->encrypt($secret),
                'two_factor_confirmed_at' => now(),
                'two_factor_recovery_codes' => Fortify::currentEncrypter()->encrypt(json_encode(['fixture-recovery-code'])),
            ])->save();
        }

        return $user;
    }

    private function asAdmin(User $admin): void
    {
        $this->actingAs($admin)->withSession([
            'auth.version' => $admin->auth_version, 'mfa.user_id' => $admin->id,
            'auth.password_confirmed_at' => time(),
        ]);
    }

    private function invitation(User $user, array $attributes = []): string
    {
        $token = Str::random(64);
        Invitation::create(array_merge([
            'user_id' => $user->id, 'created_by' => null, 'token_hash' => hash('sha256', $token),
            'expires_at' => now()->addHours(72),
        ], $attributes));

        return $token;
    }

    public function test_login_logout_and_guest_protection(): void
    {
        $this->get('/inicio')->assertRedirect('/login');
        $user = $this->user();
        $this->post('/login', ['email' => $user->email, 'password' => 'incorreta'])->assertSessionHasErrors('email');
        $this->assertGuest();
        $this->login($user);
        $this->get('/inicio')->assertOk()->assertSee($user->name);
        $this->post('/logout')->assertRedirect('/login');
        $this->assertGuest();
        $this->get('/inicio')->assertRedirect('/login');
    }

    public function test_unverified_user_cannot_enter_and_signed_confirmation_is_required(): void
    {
        $user = $this->user(['email_verified_at' => null]);
        $this->login($user);
        $this->get('/inicio')->assertRedirect('/email/verify');
        $this->post('/email/verification-notification')->assertRedirect();
        Notification::assertSentTo($user, VerifyEmail::class);
        $unsigned = '/email/verify/'.$user->id.'/'.sha1($user->email);
        $this->get($unsigned)->assertForbidden();
        $signed = URL::temporarySignedRoute('verification.verify', now()->addMinutes(60), [
            'id' => $user->id, 'hash' => sha1($user->email),
        ]);
        $this->get($signed)->assertRedirect();
        $this->assertTrue($user->fresh()->hasVerifiedEmail());
        $this->get('/inicio')->assertOk();
    }

    public function test_inactive_or_unassigned_users_cannot_login(): void
    {
        $inactive = $this->user(['active' => false]);
        $this->post('/login', ['email' => $inactive->email, 'password' => 'Somente-testes-2026!'])->assertSessionHasErrors('email');
        $unassigned = $this->user();
        $unassigned->roles()->detach();
        $this->post('/login', ['email' => $unassigned->email, 'password' => 'Somente-testes-2026!'])->assertSessionHasErrors('email');
        $this->assertGuest();
    }

    public function test_login_is_rate_limited(): void
    {
        for ($attempt = 0; $attempt < 5; $attempt++) {
            $this->post('/login', ['email' => 'ausente@example.test', 'password' => 'incorreta'])->assertSessionHasErrors('email');
        }
        $this->post('/login', ['email' => 'ausente@example.test', 'password' => 'incorreta'])->assertStatus(429);
    }

    public function test_public_registration_and_mass_assignment_cannot_grant_privileges(): void
    {
        $this->get('/register')->assertNotFound();
        $this->post('/register', ['email' => 'intruso@example.test', 'password' => 'Somente-testes-2026!', 'roles' => ['administrador']])->assertNotFound();
        $user = $this->user();
        $user->fill(['active' => false, 'auth_version' => 100, 'roles' => ['administrador']])->save();
        $this->assertTrue($user->fresh()->active);
        $this->assertFalse($user->hasRole(RoleName::Administrator));
    }

    public function test_invitation_is_single_use_and_confirms_authorized_email(): void
    {
        $user = $this->user(['password' => null, 'email_verified_at' => null]);
        $token = $this->invitation($user);
        $this->get('/convites/'.$token)->assertOk();
        $this->post('/convites/'.$token, ['password' => 'Nova-senha-segura-2026!', 'password_confirmation' => 'Nova-senha-segura-2026!'])->assertRedirect('/login');
        $this->assertTrue(Hash::check('Nova-senha-segura-2026!', $user->fresh()->password));
        $this->assertTrue($user->fresh()->hasVerifiedEmail());
        $this->post('/convites/'.$token, ['password' => 'Outra-senha-segura-2026!', 'password_confirmation' => 'Outra-senha-segura-2026!'])->assertStatus(410);
        $this->assertTrue(Hash::check('Nova-senha-segura-2026!', $user->fresh()->password));
    }

    public function test_expired_or_fabricated_invitation_cannot_be_used(): void
    {
        $user = $this->user(['password' => null, 'email_verified_at' => null]);
        $token = $this->invitation($user, ['expires_at' => now()->subMinute()]);
        $this->get('/convites/'.$token)->assertStatus(410);
        $this->post('/convites/'.$token, ['password' => 'Nova-senha-segura-2026!', 'password_confirmation' => 'Nova-senha-segura-2026!'])->assertStatus(410);
        $this->get('/convites/'.Str::random(64))->assertStatus(410);
        $this->assertNull($user->fresh()->password);
    }

    public function test_reset_password_is_single_use_and_revokes_old_sessions(): void
    {
        $user = $this->user();
        $this->post('/forgot-password', ['email' => $user->email])->assertRedirect();
        Notification::assertSentTo($user, ResetPassword::class);
        $notification = Notification::sent($user, ResetPassword::class)->first();
        $body = ['email' => $user->email, 'token' => $notification->token,
            'password' => 'Senha-redefinida-2026!', 'password_confirmation' => 'Senha-redefinida-2026!'];
        $this->post('/reset-password', $body)->assertRedirect('/login');
        $this->assertTrue(Hash::check($body['password'], $user->fresh()->password));
        $this->assertSame(1, $user->fresh()->auth_version);
        $this->assertGuest();
        $this->post('/reset-password', $body)->assertSessionHasErrors('email');
    }

    public function test_expired_reset_and_inactive_recovery_are_rejected(): void
    {
        $user = $this->user();
        $token = Password::broker()->createToken($user);
        DB::table('password_reset_tokens')->where('email', $user->email)->update(['created_at' => now()->subMinutes(61)]);
        $this->post('/reset-password', ['email' => $user->email, 'token' => $token,
            'password' => 'Senha-redefinida-2026!', 'password_confirmation' => 'Senha-redefinida-2026!'])->assertSessionHasErrors('email');
        $user->forceFill(['active' => false])->save();
        $this->post('/forgot-password', ['email' => $user->email])->assertRedirect();
        Notification::assertNothingSent();
    }

    public function test_recovery_does_not_reveal_whether_an_account_exists(): void
    {
        $user = $this->user();
        $first = $this->post('/forgot-password', ['email' => $user->email]);
        $status = session('status');
        $second = $this->post('/forgot-password', ['email' => 'inexistente@example.test']);
        $first->assertRedirect();
        $second->assertRedirect()->assertSessionHas('status', $status);
    }

    public function test_administrator_must_enroll_mfa_before_admin_actions(): void
    {
        $admin = $this->admin(false);
        $this->login($admin);
        $this->get('/inicio')->assertRedirect('/seguranca');
        $this->get('/administracao/usuarios')->assertRedirect('/seguranca');
        Livewire::test(UsersPanel::class)->assertForbidden();
        $this->get('/seguranca')->assertRedirect('/user/confirm-password');
        $this->post('/user/confirm-password', ['password' => 'Somente-testes-2026!'])->assertRedirect();
        $this->post('/seguranca/ativar')->assertRedirect('/seguranca');
        $admin->refresh();
        $this->assertNotNull($admin->two_factor_secret);
        $this->assertNull($admin->two_factor_confirmed_at);
        $this->get('/seguranca')->assertOk()->assertSee('QR code');
        $code = (new Google2FA)->getCurrentOtp(Fortify::currentEncrypter()->decrypt($admin->two_factor_secret));
        $this->post('/seguranca/confirmar', ['code' => $code])->assertRedirect('/seguranca');
        $this->assertNotNull($admin->fresh()->two_factor_confirmed_at);
        $this->get('/administracao/usuarios')->assertOk();
    }

    public function test_mfa_challenge_rejects_bad_code_accepts_recovery_once_and_reset_cannot_bypass_it(): void
    {
        $admin = $this->admin();
        $this->post('/login', ['email' => $admin->email, 'password' => 'Somente-testes-2026!'])->assertRedirect('/two-factor-challenge');
        $this->assertGuest();
        $this->post('/two-factor-challenge', ['code' => 'invalid'])->assertSessionHasErrors();
        $this->post('/two-factor-challenge', ['recovery_code' => 'fixture-recovery-code'])->assertRedirect('/inicio');
        $this->get('/inicio')->assertOk();
        $this->post('/logout')->assertRedirect();
        $this->post('/login', ['email' => $admin->email, 'password' => 'Somente-testes-2026!'])->assertRedirect('/two-factor-challenge');
        $this->post('/two-factor-challenge', ['recovery_code' => 'fixture-recovery-code'])->assertSessionHasErrors();
        $this->assertGuest();
        $this->flushSession();
        $token = Password::broker()->createToken($admin);
        $this->post('/reset-password', ['email' => $admin->email, 'token' => $token, 'password' => 'Senha-redefinida-2026!', 'password_confirmation' => 'Senha-redefinida-2026!'])->assertRedirect('/login');
        $this->post('/login', ['email' => $admin->email, 'password' => 'Senha-redefinida-2026!'])->assertRedirect('/two-factor-challenge');
        $this->assertGuest();
    }

    public function test_enabled_mfa_requires_challenge_not_only_password_or_seed(): void
    {
        $admin = $this->admin();
        $this->actingAs($admin)->withSession(['auth.version' => $admin->auth_version]);
        $this->get('/administracao/usuarios')->assertRedirect('/seguranca');
        $this->withSession(['auth.password_confirmed_at' => time()]);
        $this->get('/seguranca')->assertForbidden();
        $this->post('/seguranca/codigos')->assertForbidden();
        $this->delete('/user/two-factor-authentication')->assertNotFound();
    }

    public function test_ordinary_roles_and_job_labels_never_grant_administration(): void
    {
        foreach (RoleName::cases() as $role) {
            if ($role === RoleName::Administrator) {
                continue;
            }
            $user = $this->user(['job_title' => 'Administrador', 'support_level' => 'N3']);
            $user->roles()->sync([Role::where('name', $role->value)->firstOrFail()->id]);
            $this->actingAs($user)->withSession(['auth.version' => $user->auth_version, 'auth.password_confirmed_at' => time()]);
            $this->get('/administracao/usuarios')->assertForbidden();
            Livewire::test(UsersPanel::class)->assertForbidden();
        }
    }

    public function test_admin_can_invite_update_roles_and_audit_without_exposing_secrets(): void
    {
        $admin = $this->admin();
        $this->asAdmin($admin);
        Livewire::test(UsersPanel::class)
            ->set('inviteName', 'Colaborador fictício')->set('inviteEmail', 'colaborador@example.test')
            ->set('inviteRoles', ['colaborador'])->set('inviteReason', 'Acesso aprovado para o piloto')
            ->call('createInvite')->assertHasNoErrors();
        $user = User::where('email', 'colaborador@example.test')->firstOrFail();
        Notification::assertSentTo($user, UserInvitation::class);
        $this->assertNull($user->password);
        $this->assertTrue($user->hasRole(RoleName::Collaborator));
        $invitation = Invitation::where('user_id', $user->id)->firstOrFail();
        $this->assertSame(64, strlen($invitation->token_hash));
        Livewire::test(UsersPanel::class)->call('edit', $user->id)
            ->set('selectedRoles', ['desenvolvedor'])->set('reason', 'Encaminhamentos técnicos autorizados')
            ->call('saveUser')->assertHasNoErrors();
        $this->assertTrue($user->hasRole(RoleName::Developer));
        $this->assertFalse($user->hasRole(RoleName::Administrator));
        $this->assertSame(1, $user->fresh()->auth_version);
        $audit = DB::table('audit_events')->where('subject_id', $user->id)->pluck('details')->join(' ');
        $this->assertStringNotContainsString('password', $audit);
        $this->assertStringNotContainsString('token', $audit);
        $this->assertStringNotContainsString('two_factor_secret', $audit);
    }

    public function test_tampered_role_or_target_cannot_elevate_regular_user(): void
    {
        $user = $this->user();
        $admin = $this->admin();
        $this->actingAs($user)->withSession(['auth.version' => $user->auth_version, 'mfa.user_id' => $user->id, 'auth.password_confirmed_at' => time()]);
        try {
            app(AdminUserService::class)->update($user, $admin->id, ['name' => 'Alterado', 'active' => false, 'roles' => ['colaborador'], 'reason' => 'Tentativa de alteração']);
            $this->fail('Alteração indevida não foi bloqueada.');
        } catch (AuthorizationException $exception) {
            $this->assertTrue($admin->fresh()->active);
            $this->assertTrue($admin->hasRole(RoleName::Administrator));
        }
    }

    public function test_last_administrator_cannot_be_disabled_or_demoted(): void
    {
        $admin = $this->admin();
        $this->asAdmin($admin);
        Livewire::test(UsersPanel::class)->call('edit', $admin->id)->set('active', false)
            ->set('reason', 'Tentativa de desativar último administrador')->call('saveUser')->assertHasErrors('active');
        Livewire::test(UsersPanel::class)->call('edit', $admin->id)->set('selectedRoles', ['colaborador'])
            ->set('reason', 'Tentativa de remover último administrador')->call('saveUser')->assertHasErrors('active');
        $this->assertTrue($admin->fresh()->active);
        $this->assertTrue($admin->hasRole(RoleName::Administrator));
    }

    public function test_deactivation_revokes_database_sessions_and_old_session_cannot_return_after_reactivation(): void
    {
        $admin = $this->admin();
        $user = $this->user();
        DB::table('sessions')->insert(['id' => 'fixture-session', 'user_id' => $user->id, 'payload' => '', 'last_activity' => time()]);
        $this->asAdmin($admin);
        $service = app(AdminUserService::class);
        $service->update($admin, $user->id, ['name' => $user->name, 'active' => false, 'roles' => ['colaborador'], 'reason' => 'Desativação autorizada para teste']);
        $this->assertDatabaseMissing('sessions', ['id' => 'fixture-session']);
        $this->actingAs($user->fresh())->withSession(['auth.version' => 0]);
        $this->getJson('/inicio')->assertUnauthorized();
        $this->asAdmin($admin);
        $service->update($admin, $user->id, ['name' => $user->name, 'active' => true, 'roles' => ['colaborador'], 'reason' => 'Reativação autorizada para teste']);
        $this->actingAs($user->fresh())->withSession(['auth.version' => 0]);
        $this->getJson('/inicio')->assertUnauthorized();
        $this->assertDatabaseHas('users', ['id' => $user->id, 'active' => true, 'auth_version' => 2]);
    }

    public function test_deactivated_session_is_blocked_on_livewire_update(): void
    {
        $user = $this->user();
        $this->login($user);
        $user->forceFill(['active' => false, 'auth_version' => 1])->save();
        $url = app(HandleRequests::class)->getUpdateUri();
        $this->postJson($url, ['components' => []], ['X-Livewire' => 'true'])->assertUnauthorized();
        $this->assertGuest();
    }

    public function test_pending_mfa_is_invalidated_when_account_is_deactivated(): void
    {
        $admin = $this->admin();
        $this->post('/login', ['email' => $admin->email, 'password' => 'Somente-testes-2026!'])->assertRedirect('/two-factor-challenge');
        $admin->forceFill(['active' => false, 'auth_version' => 1])->save();
        $this->post('/two-factor-challenge', ['recovery_code' => 'fixture-recovery-code'])->assertRedirect('/login');
        $this->assertGuest();
    }

    public function test_admin_changes_require_recent_password_confirmation(): void
    {
        $admin = $this->admin();
        $this->asAdmin($admin);
        $this->withSession(['auth.password_confirmed_at' => time() - 901]);
        $this->get('/administracao/usuarios')->assertRedirect('/user/confirm-password');
        Livewire::test(UsersPanel::class)->set('inviteName', 'Teste')->set('inviteEmail', 'teste@example.test')
            ->set('inviteReason', 'Concessão sem confirmação')->call('createInvite')->assertForbidden();
        $this->assertDatabaseMissing('users', ['email' => 'teste@example.test']);
    }

    public function test_bootstrap_is_opt_in_and_cannot_run_in_production(): void
    {
        $this->artisan('auth:bootstrap')->assertExitCode(1);
        config(['security.bootstrap_enabled' => true]);
        app()->instance('env', 'production');
        $this->artisan('auth:bootstrap')->assertExitCode(1);
    }

    public function test_bootstrap_uses_hidden_external_password_and_requires_email_and_mfa(): void
    {
        config(['security.bootstrap_enabled' => true]);
        $this->artisan('auth:bootstrap')
            ->expectsQuestion('Nome fictício do administrador', 'Administrador fictício')
            ->expectsQuestion('E-mail fictício (domínio .test)', 'admin@example.test')
            ->expectsQuestion('Senha (mínimo 12 caracteres, maiúsculas, minúsculas e números)', 'Senha-inicial-2026!')
            ->expectsQuestion('Repita a senha', 'Senha-inicial-2026!')
            ->assertExitCode(0);
        $admin = User::where('email', 'admin@example.test')->firstOrFail();
        $this->assertTrue(Hash::check('Senha-inicial-2026!', $admin->password));
        $this->assertFalse($admin->hasVerifiedEmail());
        $this->assertFalse($admin->hasEnabledTwoFactorAuthentication());
        Notification::assertSentTo($admin, VerifyEmail::class);
        $this->artisan('auth:bootstrap')->assertExitCode(1);
    }

    public function test_recovery_code_rotation_requires_mfa_and_invalidates_old_codes(): void
    {
        $admin = $this->admin();
        $this->asAdmin($admin);
        $this->post('/seguranca/codigos')->assertRedirect('/seguranca');
        $this->assertNotContains('fixture-recovery-code', $admin->fresh()->recoveryCodes());
        $this->assertCount(8, $admin->fresh()->recoveryCodes());
        $this->assertDatabaseHas('audit_events', ['subject_id' => $admin->id, 'action' => 'mfa.recovery_codes_regenerated']);
    }

    public function test_valid_totp_login_and_mfa_rate_limit(): void
    {
        $admin = $this->admin();
        $secret = Fortify::currentEncrypter()->decrypt($admin->two_factor_secret);
        $this->post('/login', ['email' => $admin->email, 'password' => 'Somente-testes-2026!'])->assertRedirect('/two-factor-challenge');
        $code = (new Google2FA)->getCurrentOtp($secret);
        $this->post('/two-factor-challenge', ['code' => $code])->assertRedirect('/inicio');
        $this->get('/administracao/usuarios')->assertRedirect('/user/confirm-password');
        $this->post('/logout');
        $this->post('/login', ['email' => $admin->email, 'password' => 'Somente-testes-2026!'])->assertRedirect('/two-factor-challenge');
        // Uma tentativa válida anterior também conta no limite da janela.
        for ($attempt = 0; $attempt < 4; $attempt++) {
            $this->post('/two-factor-challenge', ['code' => 'invalid'])->assertSessionHasErrors();
        }
        $this->post('/two-factor-challenge', ['code' => 'invalid'])->assertStatus(429);
    }

    public function test_reissued_invitation_revokes_previous_link(): void
    {
        $admin = $this->admin();
        $this->asAdmin($admin);
        $user = $this->user(['password' => null, 'email_verified_at' => null]);
        $oldToken = $this->invitation($user);
        app(AdminUserService::class)->reissue($admin, $user->id, 'Reenvio autorizado para o piloto');
        Notification::assertSentTo($user, UserInvitation::class);
        $this->assertNotNull(Invitation::where('token_hash', hash('sha256', $oldToken))->firstOrFail()->consumed_at);
        $this->assertSame(1, Invitation::where('user_id', $user->id)->whereNull('consumed_at')->count());
        $this->post('/logout');
        $this->get('/convites/'.$oldToken)->assertStatus(410);
    }

    public function test_admin_can_disable_another_admin_when_one_usable_admin_remains(): void
    {
        $actor = $this->admin();
        $target = $this->admin();
        $this->asAdmin($actor);
        app(AdminUserService::class)->update($actor, $target->id, [
            'name' => $target->name, 'active' => false, 'roles' => ['administrador'], 'reason' => 'Desativação autorizada de outro administrador',
        ]);
        $this->assertFalse($target->fresh()->active);
        $this->assertTrue($actor->fresh()->active);
        $this->assertDatabaseHas('audit_events', ['subject_id' => $target->id, 'action' => 'user.access_updated']);
    }

    public function test_mfa_secrets_are_encrypted_and_hidden_from_serialized_user(): void
    {
        $admin = $this->admin();
        $secret = Fortify::currentEncrypter()->decrypt($admin->two_factor_secret);
        $this->assertNotSame($secret, DB::table('users')->where('id', $admin->id)->value('two_factor_secret'));
        $serialized = $admin->toArray();
        foreach (['password', 'remember_token', 'two_factor_secret', 'two_factor_recovery_codes'] as $field) {
            $this->assertArrayNotHasKey($field, $serialized);
        }
    }

    public function test_unknown_profile_and_missing_justification_are_rejected(): void
    {
        $admin = $this->admin();
        $this->asAdmin($admin);
        Livewire::test(UsersPanel::class)->set('inviteName', 'Usuário fictício')->set('inviteEmail', 'perfil@example.test')
            ->set('inviteRoles', ['superadmin'])->set('inviteReason', 'Concessão de perfil inexistente')
            ->call('createInvite')->assertHasErrors('roles.0');
        Livewire::test(UsersPanel::class)->set('inviteName', 'Usuário fictício')->set('inviteEmail', 'perfil@example.test')
            ->set('inviteRoles', ['colaborador'])->set('inviteReason', '')
            ->call('createInvite')->assertHasErrors('reason');
        $this->assertDatabaseMissing('users', ['email' => 'perfil@example.test']);
    }
}
