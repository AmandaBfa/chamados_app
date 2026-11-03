<?php

namespace App\Services;

class CatalogDefinition
{
    public static function all(): array
    {
        // Lista fechada: nomes de tabelas/campos nunca vêm diretamente do cliente.
        return [
            'units' => ['label' => 'Unidades e cidades', 'fields' => ['name' => 'Nome', 'city' => 'Cidade']],
            'departments' => ['label' => 'Setores', 'fields' => ['name' => 'Nome']],
            'teams' => ['label' => 'Equipes', 'fields' => ['name' => 'Nome', 'reference_user_id' => 'Referência', 'substitute_user_id' => 'Substituto', 'specific_assignments_only' => 'Somente encaminhamentos específicos', 'triage_coverage' => 'Cobertura manual de triagem', 'critical_alternative' => 'Alternativa para críticos']],
            'team_members' => ['label' => 'Membros das equipes', 'fields' => ['team_id' => 'Equipe', 'user_id' => 'Usuário']],
            'categories' => ['label' => 'Categorias', 'fields' => ['name' => 'Nome']],
            'subcategories' => ['label' => 'Subcategorias', 'fields' => ['name' => 'Nome', 'category_id' => 'Categoria']],
            'services' => ['label' => 'Serviços', 'fields' => ['name' => 'Nome', 'category_id' => 'Categoria', 'subcategory_id' => 'Subcategoria', 'team_id' => 'Equipe padrão', 'reference_user_id' => 'Referência', 'proposed_criticality' => 'Criticidade proposta (piloto)', 'supplier' => 'Fornecedor']],
            'equipment_references' => ['label' => 'Referências de equipamentos', 'fields' => ['name' => 'Descrição', 'identifier' => 'Identificador simples', 'unit_id' => 'Unidade']],
            'users' => ['label' => 'Organização dos usuários', 'fields' => ['unit_id' => 'Unidade', 'department_id' => 'Setor', 'job_title' => 'Cargo', 'support_level' => 'Classificação interna (opcional)']],
        ];
    }

    public static function get(string $kind): array
    {
        abort_unless(isset(self::all()[$kind]), 404);

        return self::all()[$kind];
    }

    public static function booleans(): array
    {
        return ['specific_assignments_only', 'triage_coverage', 'critical_alternative'];
    }

    public static function relations(): array
    {
        return ['unit_id' => 'units', 'department_id' => 'departments', 'team_id' => 'teams', 'category_id' => 'categories', 'subcategory_id' => 'subcategories', 'user_id' => 'users', 'reference_user_id' => 'users', 'substitute_user_id' => 'users'];
    }
}
