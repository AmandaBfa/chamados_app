<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class CatalogService
{
    public function authorize(User $actor): void
    {
        Gate::forUser($actor)->authorize('catalogs.manage');
        abort_if(time() - (int) session('auth.password_confirmed_at', 0) > config('auth.password_timeout'), 403, 'Confirme sua senha novamente.');
    }

    public function save(User $actor, string $kind, ?int $id, array $input, ?int $version, string $reason): int
    {
        $this->authorize($actor);
        $definition = CatalogDefinition::get($kind);
        abort_if($kind === 'users' && $id === null, 422);
        foreach ($input as $field => $value) {
            if (is_string($value)) {
                $input[$field] = trim($value) === '' ? null : trim($value);
            }
        }

        return DB::transaction(function () use ($actor, $kind, $id, $input, $version, $reason, $definition) {
            // Serializa alterações de relações entre catálogos, inclusive referências de equipe.
            DB::select('SELECT pg_advisory_xact_lock(73003)');
            $before = $id ? DB::table($kind)->lockForUpdate()->find($id) : null;
            abort_if($id && ! $before, 404);
            $versionField = $kind === 'users' ? 'organization_version' : 'version';
            if ($before && $version !== $before->$versionField) {
                throw ValidationException::withMessages(['form' => 'Este registro foi alterado. Abra novamente antes de salvar.']);
            }
            if ($before) {
                $input = array_replace(array_intersect_key((array) $before, array_flip(array_merge(array_keys($definition['fields']), ['active']))), $input);
            }
            $rules = [];
            foreach ($definition['fields'] as $field => $label) {
                if (isset(CatalogDefinition::relations()[$field])) {
                    $required = ($kind === 'subcategories' && $field === 'category_id') || $kind === 'team_members';
                    $rules[$field] = [$required ? 'required' : 'nullable', 'integer', Rule::exists(CatalogDefinition::relations()[$field], 'id')];
                } elseif (in_array($field, CatalogDefinition::booleans(), true)) {
                    $rules[$field] = ['required', 'boolean'];
                } elseif ($field === 'proposed_criticality') {
                    $rules[$field] = ['nullable', Rule::in(['baixa', 'normal', 'alta', 'critica'])];
                } else {
                    $rules[$field] = [in_array($field, ['name', 'city']) ? 'required' : 'nullable', 'string', 'max:'.($field === 'support_level' ? 20 : 160)];
                }
            }
            if ($kind !== 'users') {
                $rules['active'] = ['required', 'boolean'];
            }
            if (isset($rules['name'])) {
                $unique = Rule::unique($kind, 'name')->ignore($id);
                if ($kind === 'subcategories') {
                    $unique->where('category_id', $input['category_id'] ?? null);
                }
                $rules['name'][] = $unique;
            }
            if ($kind === 'team_members') {
                $rules['user_id'][] = Rule::unique('team_members', 'user_id')->where('team_id', $input['team_id'] ?? null)->ignore($id);
            }
            $data = Validator::make($input, $rules, [], $definition['fields'])->validate();
            Validator::make(['reason' => $reason], ['reason' => ['required', 'string', 'min:8', 'max:500']])->validate();
            foreach (CatalogDefinition::relations() as $field => $table) {
                if (! isset($data[$field])) {
                    continue;
                }
                $related = DB::table($table)->lockForUpdate()->find($data[$field]);
                $reactivating = $before && ! $before->active && ($data['active'] ?? false);
                $unchanged = $before && ! $reactivating && ($before->$field ?? null) === (int) $data[$field];
                if (! $unchanged && ! $this->available($table, $related)) {
                    throw ValidationException::withMessages([$field => 'Escolha um registro ativo, com os vínculos superiores ativos.']);
                }
            }
            if ($kind === 'services' && ! empty($data['subcategory_id'])) {
                $sub = DB::table('subcategories')->find($data['subcategory_id']);
                if ((int) ($data['category_id'] ?? 0) !== $sub->category_id) {
                    throw ValidationException::withMessages(['subcategory_id' => 'A subcategoria não pertence à categoria selecionada.']);
                }
            }
            if ($kind === 'subcategories' && $before && (int) $data['category_id'] !== $before->category_id &&
                (DB::table('services')->where('subcategory_id', $id)->exists() || DB::table('tickets')->where('subcategory_id', $id)->exists())) {
                throw ValidationException::withMessages(['category_id' => 'Subcategoria utilizada: preserve a categoria original e crie outra subcategoria.']);
            }
            if ($kind === 'teams') {
                if (! empty($data['reference_user_id']) && $data['reference_user_id'] == ($data['substitute_user_id'] ?? null)) {
                    throw ValidationException::withMessages(['substitute_user_id' => 'A referência e o substituto devem ser pessoas diferentes.']);
                }
                foreach (['reference_user_id', 'substitute_user_id'] as $field) {
                    if (! empty($data[$field]) && (! $id || ! DB::table('team_members')->where('team_id', $id)->where('user_id', $data[$field])->where('active', true)->exists())) {
                        throw ValidationException::withMessages([$field => 'Cadastre primeiro este usuário como membro ativo da equipe.']);
                    }
                }
            }
            if ($kind === 'services' && ! empty($data['reference_user_id'])) {
                if (empty($data['team_id']) || ! DB::table('team_members')->where('team_id', $data['team_id'])->where('user_id', $data['reference_user_id'])->where('active', true)->exists()) {
                    throw ValidationException::withMessages(['reference_user_id' => 'A referência deve ser membro ativo da equipe padrão.']);
                }
            }
            if ($kind === 'team_members' && $before && (! $data['active'] || (int) $data['team_id'] !== $before->team_id || (int) $data['user_id'] !== $before->user_id)) {
                $used = DB::table('teams')->where('id', $before->team_id)->where(fn ($q) => $q->where('reference_user_id', $before->user_id)->orWhere('substitute_user_id', $before->user_id))->exists();
                $used = $used || DB::table('services')->where('team_id', $before->team_id)->where('reference_user_id', $before->user_id)->exists();
                if ($used) {
                    throw ValidationException::withMessages(['user_id' => 'Substitua primeiro as referências deste membro na equipe e nos serviços.']);
                }
            }
            // Somente campos da definição são gravados; perfis/estado/credenciais de users ficam fora.
            $data[$versionField] = ($before?->$versionField ?? 0) + 1;
            $data['updated_at'] = now();
            if ($id) {
                DB::table($kind)->where('id', $id)->update($data);
            } else {
                $id = DB::table($kind)->insertGetId($data + ['created_at' => now()]);
            }
            $auditFields = array_merge(array_keys($definition['fields']), $kind === 'users' ? [] : ['active']);
            DB::table('audit_events')->insert([
                'actor_id' => $actor->id, 'subject_id' => $kind === 'users' ? $id : null,
                'action' => 'catalog.saved', 'occurred_at' => now(),
                'details' => json_encode(['catalog' => $kind, 'id' => $id, 'reason' => $reason,
                    'before' => $before ? array_intersect_key((array) $before, array_flip($auditFields)) : null,
                    'after' => array_intersect_key($data, array_flip($auditFields))], JSON_THROW_ON_ERROR),
            ]);

            return $id;
        });
    }

    private function available(string $table, object $row): bool
    {
        if (! $row->active) {
            return false;
        }
        if ($table === 'subcategories') {
            return DB::table('categories')->where('id', $row->category_id)->where('active', true)->exists();
        }

        return true;
    }
}
