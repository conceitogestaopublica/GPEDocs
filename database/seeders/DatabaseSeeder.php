<?php

namespace Database\Seeders;

use App\Models\User;
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
        // Estrutura base (ordem importa por dependências de FK)
        $this->call(GedSeeder::class);                 // tipos documentais, roles, permissões, tags, admin@ged.local, pastas-modelo
        $this->call(EnderecosDemoSeeder::class);       // uf + municipio + bairro + logradouro (precisa vir antes da UG modelo)
        $this->call(UgModeloSeeder::class);            // UG modelo + organograma 3 níveis

        // Usuários demo + vínculos UG/roles
        $this->call(UsuariosDemoSeeder::class);

        // Conteúdo demo
        $this->call(DocumentosDemoSeeder::class);      // documentos + versões + metadados + tags
        $this->call(ProcessosDemoSeeder::class);       // tipos de processo + processos + tramitações
        $this->call(MemorandosOficiosDemoSeeder::class);

        // Portal do cidadão
        $this->call(PortalServicosSeeder::class);      // categorias + serviços
        $this->call(PortalSolicitacaoDemoSeeder::class); // cidadão + 1 solicitação
    }
}
