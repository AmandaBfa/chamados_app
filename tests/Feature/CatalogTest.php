<?php

namespace Tests\Feature;

use App\Enums\RoleName;
use App\Livewire\Admin\CatalogPanel;
use App\Models\Role;
use App\Models\User;
use App\Services\CatalogDefinition;
use App\Services\CatalogService;
use Database\Seeders\InitialCatalogSeeder;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Laravel\Fortify\Fortify;
use Livewire\Features\SupportLockedProperties\CannotUpdateLockedPropertyException;
use Livewire\Livewire;
use Tests\TestCase;

class CatalogTest extends TestCase
{
    use DatabaseTransactions {
        beginDatabaseTransaction as private beginDedicatedTransaction;
    }

    private User $admin;

    public function beginDatabaseTransaction(): void
    {
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
        $this->admin = User::factory()->create();
        $this->admin->roles()->sync([Role::where('name', RoleName::Administrator->value)->value('id')]);
        $this->admin->forceFill(['two_factor_secret' => Fortify::currentEncrypter()->encrypt('JBSWY3DPEHPK3PXP'), 'two_factor_confirmed_at' => now()])->save();
        $this->actingAs($this->admin)->withSession(['auth.version' => $this->admin->auth_version, 'mfa.user_id' => $this->admin->id, 'auth.password_confirmed_at' => time()]);
    }

    private function save(string $kind, array $data, ?int $id = null, ?int $version = null): int
    {
        return app(CatalogService::class)->save($this->admin, $kind, $id, $data + ['active' => true], $version, 'Validação com dados fictícios');
    }

    private function invalid(callable $operation, string $field): void
    {
        try {
            $operation();
            $this->fail('A operação deveria ter sido rejeitada.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey($field, $exception->errors());
        }
    }

    public function test_admin_pages_and_all_catalog_forms_work(): void
    {
        foreach (CatalogDefinition::all() as $kind => $definition) {
            $this->get(route('admin.catalogs', $kind))->assertOk()->assertSee($definition['label']);
        }
        $this->get('/administracao/cadastros/roles')->assertNotFound();
        Livewire::test(CatalogPanel::class, ['kind' => 'units'])
            ->set('form.name', 'Unidade fictícia')->set('form.city', 'Cidade de teste')->set('reason', 'Cadastro fictício de teste')
            ->call('save')->assertHasNoErrors()->assertSee('Unidade fictícia');
        $this->assertDatabaseHas('units', ['name' => 'Unidade fictícia', 'city' => 'Cidade de teste']);
        $department = $this->save('departments', ['name' => 'Setor fictício']);
        $equipment = $this->save('equipment_references', ['name' => 'Computador fictício', 'identifier' => 'TESTE-01']);
        $this->assertGreaterThan(0, $department);
        $this->assertGreaterThan(0, $equipment);
        $this->assertDatabaseHas('audit_events', ['actor_id' => $this->admin->id, 'action' => 'catalog.saved']);
    }

    public function test_every_non_admin_role_is_denied_by_url_livewire_and_service(): void
    {
        foreach (RoleName::cases() as $role) {
            if ($role === RoleName::Administrator) {
                continue;
            }
            $user = User::factory()->create(['job_title' => 'Administrador', 'support_level' => 'N3']);
            $user->roles()->sync([Role::where('name', $role->value)->value('id')]);
            $this->actingAs($user)->withSession(['auth.version' => $user->auth_version, 'mfa.user_id' => $user->id]);
            $this->get('/administracao/cadastros/units')->assertForbidden();
            Livewire::test(CatalogPanel::class, ['kind' => 'units'])->assertForbidden();
            try {
                app(CatalogService::class)->save($user, 'units', null, ['name' => 'Proibido', 'city' => 'Teste', 'active' => true], null, 'Alteração não autorizada');
                $this->fail('Acesso ao serviço deveria ser negado.');
            } catch (AuthorizationException) {
                $this->assertDatabaseMissing('units', ['name' => 'Proibido']);
            }
        }
    }

    public function test_livewire_action_checks_authorization_after_permission_is_removed(): void
    {
        $component = Livewire::test(CatalogPanel::class, ['kind' => 'departments'])->set('form.name', 'Não autorizado')->set('reason', 'Tentativa sem permissão');
        $this->admin->roles()->sync([Role::where('name', 'colaborador')->value('id')]);
        $component->call('save')->assertForbidden();
        $this->assertDatabaseMissing('departments', ['name' => 'Não autorizado']);
    }

    public function test_seed_is_exact_idempotent_and_preserves_customization(): void
    {
        $this->seed(InitialCatalogSeeder::class);
        foreach (InitialCatalogSeeder::CATALOG as $name => $children) {
            $id = DB::table('categories')->where('name', $name)->value('id');
            $this->assertNotNull($id);
            $this->assertSame($children, DB::table('subcategories')->where('category_id', $id)->orderBy('id')->pluck('name')->all());
        }
        $counts = [];
        foreach (['teams', 'categories', 'subcategories', 'services'] as $table) {
            $counts[$table] = DB::table($table)->count();
        }
        $solus = DB::table('services')->where('name', 'Solus')->first();
        $this->assertNull($solus->proposed_criticality);
        $this->save('services', ['name' => 'Solus personalizado', 'supplier' => 'Fornecedor fictício', 'active' => false], $solus->id, $solus->version);
        $this->seed(InitialCatalogSeeder::class);
        foreach ($counts as $table => $count) {
            $this->assertSame($count, DB::table($table)->count());
        }
        $this->assertDatabaseHas('services', ['id' => $solus->id, 'name' => 'Solus personalizado', 'supplier' => 'Fornecedor fictício', 'active' => false]);
        $this->assertDatabaseHas('teams', ['name' => 'Desenvolvimento', 'specific_assignments_only' => true]);
        $this->assertDatabaseHas('teams', ['name' => 'Infraestrutura e Redes', 'critical_alternative' => true]);
    }

    public function test_incompatible_subcategory_rejected_and_category_change_clears_selection(): void
    {
        $a = $this->save('categories', ['name' => 'Categoria A']);
        $b = $this->save('categories', ['name' => 'Categoria B']);
        $sub = $this->save('subcategories', ['name' => 'Filha A', 'category_id' => $a]);
        $this->invalid(fn () => $this->save('services', ['name' => 'Incompatível', 'category_id' => $b, 'subcategory_id' => $sub]), 'subcategory_id');
        Livewire::test(CatalogPanel::class, ['kind' => 'services'])->set('form.category_id', $a)->set('form.subcategory_id', $sub)
            ->set('form.category_id', $b)->assertSet('form.subcategory_id', null);
        $this->save('services', ['name' => 'Compatível', 'category_id' => $a, 'subcategory_id' => $sub]);
        $this->invalid(fn () => $this->save('subcategories', ['name' => 'Filha A', 'category_id' => $b], $sub, 1), 'category_id');
    }

    public function test_inactive_items_preserve_existing_links_but_cannot_be_new_selections(): void
    {
        $unit = $this->save('units', ['name' => 'Unidade histórica', 'city' => 'Cidade fictícia']);
        $this->save('users', ['unit_id' => $unit], $this->admin->id, 1);
        $this->save('units', ['name' => 'Unidade histórica', 'city' => 'Cidade fictícia', 'active' => false], $unit, 1);
        $this->assertSame($unit, $this->admin->fresh()->unit_id);
        $other = User::factory()->create();
        $this->invalid(fn () => $this->save('users', ['unit_id' => $unit], $other->id, 1), 'unit_id');
        $this->save('users', ['unit_id' => $unit, 'job_title' => 'Cargo fictício'], $this->admin->id, 2);
        Livewire::test(CatalogPanel::class, ['kind' => 'users'])->call('edit', $other->id)->assertDontSee('Unidade histórica');
        Livewire::test(CatalogPanel::class, ['kind' => 'users'])->call('edit', $this->admin->id)->assertSee('Unidade histórica');
    }

    public function test_membership_scope_references_and_privilege_separation(): void
    {
        $team = $this->save('teams', ['name' => 'Equipe fictícia', 'specific_assignments_only' => true, 'triage_coverage' => false, 'critical_alternative' => false]);
        $user = User::factory()->create();
        $teamData = ['name' => 'Equipe fictícia', 'specific_assignments_only' => true, 'triage_coverage' => true, 'critical_alternative' => false, 'reference_user_id' => $user->id];
        $this->invalid(fn () => $this->save('teams', $teamData, $team, 1), 'reference_user_id');
        $member = $this->save('team_members', ['team_id' => $team, 'user_id' => $user->id, 'roles' => ['administrador']]);
        $this->save('teams', $teamData, $team, 1);
        $this->assertFalse($user->hasRole(RoleName::Administrator));
        $this->assertSame('colaborador', $user->roles()->value('name'));
        $this->invalid(fn () => $this->save('team_members', ['team_id' => $team, 'user_id' => $user->id, 'active' => false], $member, 1), 'user_id');
        $otherTeam = $this->save('teams', ['name' => 'Outra equipe', 'specific_assignments_only' => false, 'triage_coverage' => false, 'critical_alternative' => false]);
        $this->invalid(fn () => $this->save('services', ['name' => 'Serviço incorreto', 'team_id' => $otherTeam, 'reference_user_id' => $user->id]), 'reference_user_id');
        $this->save('services', ['name' => 'Serviço correto', 'team_id' => $team, 'reference_user_id' => $user->id]);
        $this->invalid(fn () => $this->save('team_members', ['team_id' => $team, 'user_id' => $user->id]), 'user_id');
    }

    public function test_organization_whitelist_cannot_change_roles_credentials_or_status(): void
    {
        $user = User::factory()->create();
        $hash = $user->password;
        $this->save('users', ['job_title' => 'Gerente fictício', 'support_level' => 'N3', 'active' => false, 'roles' => ['administrador'], 'password' => 'adulterada', 'auth_version' => 900], $user->id, 1);
        $user->refresh();
        $this->assertTrue($user->active);
        $this->assertSame($hash, $user->password);
        $this->assertSame(0, $user->auth_version);
        $this->assertFalse($user->hasRole(RoleName::Administrator));
        $this->assertSame('N3', $user->support_level);
    }

    public function test_validation_conflict_search_pagination_and_empty_states(): void
    {
        $id = $this->save('departments', ['name' => 'Setor inicial']);
        $this->save('departments', ['name' => 'Setor alterado'], $id, 1);
        $this->invalid(fn () => $this->save('departments', ['name' => 'Edição atrasada'], $id, 1), 'form');
        $this->invalid(fn () => $this->save('departments', ['name' => 'Setor alterado']), 'name');
        for ($n = 0; $n < 12; $n++) {
            $this->save('departments', ['name' => 'Paginação '.str_pad((string) $n, 2, '0', STR_PAD_LEFT)]);
        }
        Livewire::test(CatalogPanel::class, ['kind' => 'departments'])->set('search', 'Paginação')->assertSee('Paginação 00')->assertDontSee('Paginação 11')
            ->call('gotoPage', 2)->assertSee('Paginação 11')->set('search', 'Não existe')->assertSee('Nenhum registro encontrado.');
        $this->withSession(['auth.password_confirmed_at' => time() - 1000]);
        Livewire::test(CatalogPanel::class, ['kind' => 'departments'])->assertForbidden();
    }

    public function test_database_composite_foreign_key_and_historical_reference_are_enforced(): void
    {
        $a = $this->save('categories', ['name' => 'Pai A']);
        $b = $this->save('categories', ['name' => 'Pai B']);
        $sub = $this->save('subcategories', ['name' => 'Filha', 'category_id' => $a]);
        $this->save('services', ['name' => 'Histórico', 'category_id' => $a, 'subcategory_id' => $sub]);
        foreach ([fn () => DB::table('services')->where('name', 'Histórico')->update(['category_id' => $b]), fn () => DB::table('categories')->where('id', $a)->delete()] as $operation) {
            try {
                DB::transaction($operation);
                $this->fail('A restrição do PostgreSQL deveria impedir a operação.');
            } catch (QueryException $exception) {
                $this->assertContains($exception->errorInfo[0], ['23503', '23001']);
            }
        }
    }

    public function test_livewire_catalog_identity_cannot_be_tampered_with(): void
    {
        $this->expectException(CannotUpdateLockedPropertyException::class);
        Livewire::test(CatalogPanel::class, ['kind' => 'units'])->set('kind', 'roles');
    }

    public function test_seed_name_conflict_rolls_back_without_overwriting_manual_record(): void
    {
        $id = $this->save('categories', ['name' => 'Microsoft 365']);
        try {
            $this->seed(InitialCatalogSeeder::class);
            $this->fail('Conflito manual deveria interromper o seed.');
        } catch (\RuntimeException $exception) {
            $this->assertStringContainsString('Conflito no catálogo', $exception->getMessage());
        }
        $this->assertDatabaseHas('categories', ['id' => $id, 'seed_key' => null, 'name' => 'Microsoft 365']);
        $this->assertDatabaseMissing('teams', ['seed_key' => 'team-1']);
    }

    public function test_reactivation_rejects_inactive_parent_and_blank_optional_fields_are_safe(): void
    {
        $team = $this->save('teams', ['name' => 'Equipe de teste', 'specific_assignments_only' => false, 'triage_coverage' => false, 'critical_alternative' => false]);
        $user = User::factory()->create();
        $member = $this->save('team_members', ['team_id' => $team, 'user_id' => $user->id]);
        $this->save('team_members', ['active' => false], $member, 1);
        $this->save('teams', ['active' => false], $team, 1);
        $this->invalid(fn () => $this->save('team_members', ['active' => true], $member, 2), 'team_id');
        Livewire::test(CatalogPanel::class, ['kind' => 'services'])->set('form.name', 'Sem vínculos')
            ->set('form.team_id', '')->set('form.category_id', '')->set('form.subcategory_id', '')
            ->set('reason', 'Serviço fictício sem vínculos')->call('save')->assertHasNoErrors();
        $this->assertDatabaseHas('services', ['name' => 'Sem vínculos', 'team_id' => null, 'category_id' => null, 'subcategory_id' => null]);
    }

    public function test_livewire_forms_save_members_teams_services_and_user_organization(): void
    {
        $unit = $this->save('units', ['name' => 'Unidade de teste', 'city' => 'Cidade fictícia']);
        $department = $this->save('departments', ['name' => 'Setor de teste']);
        $team = $this->save('teams', ['name' => 'Equipe de teste', 'specific_assignments_only' => false, 'triage_coverage' => false, 'critical_alternative' => false]);
        $user = User::factory()->create();
        Livewire::test(CatalogPanel::class, ['kind' => 'team_members'])->set('form.team_id', $team)->set('form.user_id', $user->id)
            ->set('reason', 'Membro fictício para teste')->call('save')->assertHasNoErrors()->assertSee('Equipe de teste');
        Livewire::test(CatalogPanel::class, ['kind' => 'teams'])->call('edit', $team)->set('form.reference_user_id', $user->id)
            ->set('reason', 'Referência fictícia de teste')->call('save')->assertHasNoErrors();
        $category = $this->save('categories', ['name' => 'Categoria de teste']);
        Livewire::test(CatalogPanel::class, ['kind' => 'subcategories'])->set('form.name', 'Subcategoria de teste')->set('form.category_id', $category)
            ->set('reason', 'Subcategoria fictícia de teste')->call('save')->assertHasNoErrors();
        $sub = DB::table('subcategories')->where('name', 'Subcategoria de teste')->value('id');
        Livewire::test(CatalogPanel::class, ['kind' => 'services'])->set('form.name', 'Serviço de teste')->set('form.category_id', $category)
            ->set('form.subcategory_id', $sub)->set('form.team_id', $team)->set('form.reference_user_id', $user->id)
            ->set('form.proposed_criticality', 'alta')->set('reason', 'Serviço fictício para teste')->call('save')->assertHasNoErrors();
        Livewire::test(CatalogPanel::class, ['kind' => 'users'])->call('edit', $user->id)->set('form.unit_id', $unit)->set('form.department_id', $department)
            ->set('form.job_title', 'Cargo fictício')->set('form.support_level', 'N2')->set('reason', 'Organização fictícia de teste')->call('save')->assertHasNoErrors();
        $this->assertDatabaseHas('users', ['id' => $user->id, 'unit_id' => $unit, 'department_id' => $department, 'support_level' => 'N2']);
        $this->assertDatabaseHas('services', ['name' => 'Serviço de teste', 'team_id' => $team, 'reference_user_id' => $user->id, 'proposed_criticality' => 'alta']);
    }
}
