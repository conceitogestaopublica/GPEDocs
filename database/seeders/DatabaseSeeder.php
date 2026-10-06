<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // Só ESTRUTURA — a mesma lista, na mesma ordem, do docs:template-restore (que é o
        // caminho normal de montar um tenant). Usuários vêm do legado (super admins), não de
        // seeder. Os seeders de conteúdo demo (Documentos/Processos/MemorandosOficios/
        // PortalSolicitacao) não entram: dependiam dos usuários demo, que foram removidos.
        $this->call(GedSeeder::class);                 // tipos documentais, roles, permissões, tags, pastas-modelo
        $this->call(EnderecosDemoSeeder::class);       // uf + municipio + bairro + logradouro (antes da UG modelo)
        $this->call(UgModeloSeeder::class);            // UG modelo + organograma 3 níveis
        $this->call(PortalServicosSeeder::class);      // portal: categorias + serviços
    }
}
