<?php

declare(strict_types=1);

namespace App\Console\Commands\Tenant;

use App\Models\Landlord\SuperAdmin;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Validator;

/**
 * Cria um super-admin no banco landlord (login do painel de tenants).
 *
 *   php artisan landlord:criar-super-admin fulano@municipio.gov.br --nome="Fulano" --senha=segredo123
 *   php artisan landlord:criar-super-admin            # pergunta e-mail, nome e senha (sem eco)
 *
 * Cria SÓ a identidade em landlord.super_admins — que e o suficiente para
 * entrar no painel landlord. O espelho em <tenant>.usuario e os vinculos de
 * SSO (super_admin_tenant_links), necessarios para o jump para dentro de um
 * tenant, continuam a cargo do LandlordSuperAdminSeeder.
 */
class CriarSuperAdmin extends Command
{
    protected $signature = 'landlord:criar-super-admin
                            {email? : E-mail usado como login}
                            {--nome= : Nome exibido no painel}
                            {--senha= : Senha (se omitida, e pedida sem eco)}';

    protected $description = 'Cria um super-admin em landlord.super_admins';

    public function handle(): int
    {
        $email = (string) ($this->argument('email') ?: $this->ask('E-mail'));
        $nome = (string) ($this->option('nome') ?: $this->ask('Nome'));

        $senha = (string) $this->option('senha');
        if ($senha === '') {
            $senha = (string) $this->secret('Senha');
            if ($senha !== (string) $this->secret('Confirme a senha')) {
                $this->error('As senhas nao conferem.');

                return self::FAILURE;
            }
        }

        $v = Validator::make(
            ['email' => $email, 'nome' => $nome, 'senha' => $senha],
            [
                'email' => 'required|email|max:150',
                'nome' => 'required|string|max:200',
                'senha' => 'required|string|min:8',
            ],
            [
                'email.email' => 'Informe um e-mail valido.',
                'senha.min' => 'A senha deve ter ao menos :min caracteres.',
            ],
        );

        if ($v->fails()) {
            foreach ($v->errors()->all() as $erro) {
                $this->error($erro);
            }

            return self::FAILURE;
        }

        // O e-mail e UNIQUE na tabela. Falhar aqui (em vez de atualizar) evita
        // sobrescrever a senha de uma conta em uso por um comando de criacao.
        $existente = SuperAdmin::where('email', $email)->first();
        if ($existente) {
            $this->error("Ja existe super-admin com o e-mail {$email} (id {$existente->id}).");

            return self::FAILURE;
        }

        $admin = new SuperAdmin;
        $admin->forceFill([
            'email' => $email,
            'nome' => $nome,
            'password' => $senha,   // cast 'hashed' no model faz o Hash::make
            'active' => true,
        ])->save();

        $this->info("Super-admin criado em landlord.super_admins: {$email} (id {$admin->id}).");

        return self::SUCCESS;
    }
}
