<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class InitialCatalogSeeder extends Seeder
{
    public const CATALOG = [
        'Sistemas corporativos' => ['Não abre', 'erro em operação', 'lentidão', 'indisponibilidade', 'integração'],
        'Microsoft 365' => ['E-mail/calendário', 'Teams', 'arquivos/sincronização', 'aplicativos Office'],
        'Computadores e periféricos' => ['Não liga', 'travamento/lentidão', 'tela', 'teclado/mouse', 'áudio/câmera'],
        'Rede e conectividade' => ['Internet', 'Wi-Fi', 'rede cabeada', 'VPN'],
        'Impressão e digitalização' => ['Não imprime', 'qualidade', 'fila', 'digitalização'],
        'Telefonia' => ['Não realiza/recebe chamadas', 'áudio', 'ramal', 'conexão'],
        'Arquivos e pastas de rede' => ['Acesso negado', 'indisponibilidade', 'arquivo ausente', 'lentidão'],
        'Acesso e autenticação' => ['Conta bloqueada', 'erro de login', 'MFA/Authenticator'],
        'Documentos e assinatura digital' => ['Adobe não abre', 'erro de assinatura', 'validação', 'envio'],
        'Segurança da informação' => ['Mensagem suspeita', 'suspeita de comprometimento', 'perda/exposição'],
        'Não sei identificar' => ['Triagem geral'],
    ];

    public function run(): void
    {
        DB::transaction(function () {
            DB::select('SELECT pg_advisory_xact_lock(73003)');
            foreach (['Suporte', 'Infraestrutura e Redes', 'Sistemas', 'Gestão', 'Desenvolvimento'] as $index => $name) {
                $this->ensure('teams', 'team-'.($index + 1), ['name' => $name,
                    'specific_assignments_only' => $name === 'Desenvolvimento',
                    'critical_alternative' => $name === 'Infraestrutura e Redes']);
            }
            foreach (self::CATALOG as $name => $children) {
                $index = array_search($name, array_keys(self::CATALOG), true) + 1;
                $category = $this->ensure('categories', 'category-'.$index, ['name' => $name]);
                foreach ($children as $childIndex => $child) {
                    $this->ensure('subcategories', 'subcategory-'.$index.'-'.($childIndex + 1), ['name' => $child, 'category_id' => $category]);
                }
            }
            foreach (['Mosia', 'Solus', 'eSolution', 'Teams', 'Outlook', 'OneDrive', 'Word', 'Excel', 'Adobe', 'Zoiper'] as $index => $name) {
                $this->ensure('services', 'service-'.($index + 1), ['name' => $name]);
            }
            // Exemplos técnicos configuráveis por instalação; não são pessoas ou ativos reais.
            foreach (['Internet', 'Wi-Fi', 'Rede cabeada', 'VPN', 'Pastas de rede', 'Impressão'] as $index => $name) {
                $this->ensure('services', 'infrastructure-'.($index + 1), ['name' => $name]);
            }
        });
    }

    private function ensure(string $table, string $key, array $values): int
    {
        $existing = DB::table($table)->where('seed_key', $key)->first();
        if ($existing) {
            return $existing->id;
        }
        $sameName = DB::table($table)->where('name', $values['name']);
        if (isset($values['category_id'])) {
            $sameName->where('category_id', $values['category_id']);
        }
        if ($sameName->exists()) {
            // Não adotar silenciosamente um registro manual como registro do seed.
            throw new \RuntimeException("Conflito no catálogo {$table}: resolva o nome antes de repetir o seed.");
        }

        return DB::table($table)->insertGetId($values + ['seed_key' => $key, 'active' => true, 'version' => 1, 'created_at' => now(), 'updated_at' => now()]);
    }
}
