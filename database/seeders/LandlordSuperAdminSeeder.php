<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Tenant;
use App\Tenant\TenantContext;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;

/**
 * Garante o super-admin padrão de três pontas do sistema multi-tenant:
 *
 *   1. landlord.super_admins                 → identidade + credencial do super-admin
 *   2. <tenant>.usuario                      → espelho no tenant (mesma senha) p/ que o
 *                                              SSO jump consiga logar Auth::user() lá
 *   3. landlord.super_admin_tenant_links     → vínculo explícito entre (1) e (2),
 *                                              flag default=true (1 por tenant no seed)
 *
 * Idempotente: pula tenant onde o `usuario` já existe (não sobrescreve senha
 * de quem já está cadastrado) e atualiza a senha do super-admin apenas se
 * a coluna estiver vazia (estado pós-migration do passo 1).
 *
 * Rode isolado:
 *   php artisan db:seed --class=LandlordSuperAdminSeeder
 */
class LandlordSuperAdminSeeder extends Seeder
{
    private const EMAIL = 'joeljardim@gmail.com';
    private const NOME = 'Joel Jardim';
    private const SENHA_PADRAO = '57111603';

    public function run(): void
    {
        $superAdminId = $this->garantirNoLandlord();
        $this->garantirEmTodosTenants($superAdminId);
    }

    /** 1. Insere/atualiza a identidade do super-admin no landlord. */
    private function garantirNoLandlord(): int
    {
        $row = DB::connection('landlord')->table('super_admins')
            ->where('email', self::EMAIL)
            ->first();

        if ($row) {
            // Atualiza dados não-críticos. Só toca password se estiver vazio
            // (estado herdado da migration que adicionou a coluna).
            $update = [
                'nome' => self::NOME,
                'active' => true,
                'updated_at' => now(),
            ];
            if (empty($row->password)) {
                $update['password'] = Hash::make(self::SENHA_PADRAO);
                $this->command?->info('Senha padrão definida em super_admins.');
            }
            DB::connection('landlord')->table('super_admins')
                ->where('id', $row->id)->update($update);

            $this->command?->info('Super-admin atualizado em landlord.super_admins: ' . self::EMAIL);
            return (int)$row->id;
        }

        $id = DB::connection('landlord')->table('super_admins')->insertGetId([
            'email' => self::EMAIL,
            'nome' => self::NOME,
            'password' => Hash::make(self::SENHA_PADRAO),
            'active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->command?->info('Super-admin criado em landlord.super_admins: ' . self::EMAIL);
        return (int)$id;
    }

    /** 2 + 3. Para cada tenant ativo: garante usuario espelho e o link no landlord. */
    private function garantirEmTodosTenants(int $superAdminId): void
    {
        $tenants = Tenant::active()->orderBy('domain')->get();

        if ($tenants->isEmpty()) {
            $this->command?->warn('Nenhum tenant ativo encontrado — pulando criação de usuário e links.');
            return;
        }

        /** @var TenantContext $ctx */
        $ctx = app(TenantContext::class);

        foreach ($tenants as $tenant) {
            try {
                $ctx->set($tenant);
                $this->garantirUsuarioNoTenant($tenant->domain);
                $this->garantirLinkSuperAdmin($superAdminId, $tenant);
            } catch (\Throwable $e) {
                $this->command?->error("  ✗ {$tenant->domain}: " . $e->getMessage());
            }
        }

        $ctx->clear();
    }

    /** 2. Cria o usuário no tenant se ainda não existir (não sobrescreve). */
    private function garantirUsuarioNoTenant(string $dominio): void
    {
        if (!Schema::connection('tenant')->hasTable('usuario')) {
            $this->command?->warn("  · {$dominio}: tabela 'usuario' não existe — pulado.");
            return;
        }

        $jaExiste = DB::connection('tenant')->table('usuario')
            ->where('email', self::EMAIL)
            ->exists();

        if ($jaExiste) {
            $this->command?->line("  · {$dominio}: usuário já existe — não alterado.");
            return;
        }

        // Pega a primeira gestora se a coluna gestora_id existir e for obrigatória
        $gestoraId = Schema::connection('tenant')->hasTable('gestora')
            ? DB::connection('tenant')->table('gestora')->orderBy('id')->value('id')
            : null;

        DB::connection('tenant')->table('usuario')->insert([
            'login' => self::EMAIL,
            'email' => self::EMAIL,
            'password' => Hash::make(self::SENHA_PADRAO),
            'ativo' => 1,
            'isAdmin' => 1,
            'isSuperAdmin' => 1,
            'gestora_id' => $gestoraId,
            'acesso_dom' => 1,
            'acesso_seg' => 1,
            'acesso_ter' => 1,
            'acesso_qua' => 1,
            'acesso_qui' => 1,
            'acesso_sex' => 1,
            'acesso_sab' => 1,
        ]);

        $this->command?->info("  ✓ {$dominio}: usuário criado com senha padrão.");
    }

    /** 3. Garante o vínculo super-admin → usuario do tenant no landlord. */
    private function garantirLinkSuperAdmin(int $superAdminId, Tenant $tenant): void
    {
        DB::connection('landlord')->table('super_admin_tenant_links')->updateOrInsert(
            [
                'super_admin_id' => $superAdminId,
                'tenant_id' => $tenant->id,
                'tenant_user_email' => self::EMAIL,
            ],
            [
                'default' => true,
                'updated_at' => now(),
                'created_at' => now(),
            ],
        );

        $this->command?->info("  ↳ {$tenant->domain}: vínculo super-admin → usuário garantido (default).");
    }
}
