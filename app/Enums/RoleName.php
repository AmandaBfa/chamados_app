<?php

namespace App\Enums;

enum RoleName: string
{
    case Collaborator = 'colaborador';
    case Apprentice = 'jovem_aprendiz';
    case Support = 'suporte';
    case Analyst = 'analistas';
    case Developer = 'desenvolvedor';
    case Manager = 'gerente';
    case Administrator = 'administrador';

    public function label(): string
    {
        return match ($this) {
            self::Collaborator => 'Colaborador',
            self::Apprentice => 'Jovem aprendiz',
            self::Support => 'Suporte técnico',
            self::Analyst => 'Analistas',
            self::Developer => 'Desenvolvedor',
            self::Manager => 'Gerente',
            self::Administrator => 'Administrador',
        };
    }
}
