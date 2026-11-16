<?php

declare(strict_types=1);

namespace App\Console\Commands\Tenant;

use App\Http\Middleware\ResolveTenant;
use App\Models\Tenant;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

/**
 * Cadastra um município do GPEDocs no landlord compartilhado com o gpe2 (alternativa ao painel).
 *
 * Só grava o registro em `tenants`: o banco é criado depois pelo docs:template-restore. Toda opção
 * não informada na linha de comando é perguntada, com o padrão deste ambiente sugerido (domínio do
 * TENANT_DOMINIO_BASE, servidor e login do landlord). Com --no-interaction valem os padrões, e o
 * nome do município passa a ser obrigatório.
 */
class TenantCreate extends Command
{
    protected $signature = 'tenant:create
        {--domain= : Domínio base, um dos de TENANT_DOMINIO_BASE (ex: gpedocs.com.br ou :8090)}
        {--subdomain= : Subdomínio (ex: paraguacu). Em dev (domínio :porta) é sempre localhost}
        {--nome= : Nome do município}
        {--cnpj= : CNPJ (opcional)}
        {--uf= : UF (opcional)}
        {--db-host= : Servidor PostgreSQL (padrão: o do landlord)}
        {--db-port= : Porta (padrão: a do landlord)}
        {--db-name= : Nome do banco (padrão: docs_<subdomínio>)}
        {--db-username= : Usuário do banco (padrão: o do landlord)}
        {--db-password= : Senha do banco (padrão: a do landlord)}';

    protected $description = 'Cadastra um município do GPEDocs no landlord (pergunta o que não for informado)';

    public function handle(): int
    {
        // Sem terminal (docker exec sem -it) vale como -n: o Symfony não detecta a falta de TTY e a
        // primeira pergunta leria EOF e abortaria. SHELL_INTERACTIVE força as perguntas (respostas por pipe).
        if (! stream_isatty(STDIN) && getenv('SHELL_INTERACTIVE') === false) {
            $this->input->setInteractive(false);
        }

        $dominios = Tenant::dominiosBase();
        if ($dominios === []) {
            $this->error('TENANT_DOMINIO_BASE está vazio no .env: não há domínio do GPEDocs para cadastrar.');

            return self::FAILURE;
        }

        // Fora de TENANT_DOMINIO_BASE o tenant ficaria invisível para este projeto (escopo global do model).
        $domain = $this->option('domain')
            ?: (count($dominios) === 1 ? $dominios[0] : $this->choice('Domínio base', $dominios, 0));
        if (! in_array($domain, $dominios, true)) {
            $this->error("Domínio '{$domain}' não está em TENANT_DOMINIO_BASE (".implode(', ', $dominios).').');

            return self::FAILURE;
        }
        $dev = str_starts_with($domain, ':');

        // Em dev o tenant é achado pela porta, com subdomain fixo 'localhost' (ResolveTenant).
        $subdomain = $dev ? 'localhost' : Str::lower(trim((string) ($this->option('subdomain')
            ?: $this->ask('Subdomínio (ex: paraguacu → paraguacu.'.$domain.')'))));
        if ($dev && $this->option('subdomain') && $this->option('subdomain') !== 'localhost') {
            $this->warn("Domínio de dev ({$domain}): o subdomínio é sempre 'localhost'; '{$this->option('subdomain')}' foi ignorado.");
        }
        if (! $dev && (! preg_match('/^[a-z0-9]([a-z0-9-]{0,98}[a-z0-9])?$/', $subdomain)
            || in_array($subdomain, ResolveTenant::SUBDOMAINS_RESERVADOS, true))) {
            $this->error("Subdomínio inválido: '{$subdomain}' (letras minúsculas, dígitos e hífen; reservados: "
                .implode(', ', ResolveTenant::SUBDOMAINS_RESERVADOS).').');

            return self::FAILURE;
        }

        $existente = Tenant::where('domain', $domain)->where('subdomain', $subdomain)->first();
        if ($existente) {
            $this->error("Já existe o tenant #{$existente->id} ({$existente->nome}) em {$this->endereco($subdomain, $domain)}.");

            return self::FAILURE;
        }

        $nome = trim((string) ($this->option('nome') ?: $this->ask('Nome do município')));
        if ($nome === '' || mb_strlen($nome) > 200) {
            $this->error('Informe o nome do município (até 200 caracteres).');

            return self::FAILURE;
        }

        $cnpj = preg_replace('/\D/', '', (string) ($this->option('cnpj') ?? $this->ask('CNPJ (opcional)')));
        if ($cnpj !== '' && strlen($cnpj) !== 14) {
            $this->error("CNPJ inválido: precisa de 14 dígitos (veio '{$cnpj}').");

            return self::FAILURE;
        }

        $uf = Str::upper(trim((string) ($this->option('uf') ?? $this->ask('UF (opcional)'))));
        if ($uf !== '' && ! preg_match('/^[A-Z]{2}$/', $uf)) {
            $this->error("UF inválida: '{$uf}' (duas letras, ex: MG).");

            return self::FAILURE;
        }

        // O banco fica, por padrão, no mesmo PostgreSQL e com o mesmo login do landlord.
        $landlord = config('database.connections.landlord');
        $dbHost = (string) ($this->option('db-host') ?: $this->ask('Servidor PostgreSQL', (string) $landlord['host']));
        $dbPort = (string) ($this->option('db-port') ?: $this->ask('Porta', (string) $landlord['port']));
        $padraoBanco = 'docs_'.Str::of($dev ? $nome : $subdomain)->ascii()->lower()->replaceMatches('/[^a-z0-9]+/', '_')->trim('_');
        $dbName = (string) ($this->option('db-name') ?: $this->ask('Nome do banco', $padraoBanco));
        $dbUser = (string) ($this->option('db-username') ?: $this->ask('Usuário do banco', (string) $landlord['username']));
        $dbPass = $this->option('db-password') ?: ($this->secret('Senha do banco (Enter = a mesma do landlord)') ?: $landlord['password']);

        if (! ctype_digit($dbPort) || (int) $dbPort < 1 || (int) $dbPort > 65535) {
            $this->error("Porta inválida: '{$dbPort}'.");

            return self::FAILURE;
        }
        // Mesma regra do docs:template-restore, que cria o banco com este nome.
        if (! preg_match('/^[a-z_][a-z0-9_]{0,62}$/', $dbName)) {
            $this->error("Nome de banco inválido: '{$dbName}' (minúsculas, dígitos e _, começando por letra).");

            return self::FAILURE;
        }
        if ($dbHost === '' || $dbUser === '') {
            $this->error('Informe o servidor e o usuário do banco.');

            return self::FAILURE;
        }

        $this->table(['Campo', 'Valor'], [
            ['Endereço', $this->endereco($subdomain, $domain)],
            ['Município', $nome.($uf !== '' ? " / {$uf}" : '')],
            ['CNPJ', $cnpj !== '' ? $cnpj : '—'],
            ['Banco', "{$dbHost}:{$dbPort}/{$dbName}"],
            ['Login', $dbUser],
        ]);
        if (! $this->confirm('Cadastrar este tenant no landlord?', true)) {
            $this->line('Nada foi gravado.');

            return self::FAILURE;
        }

        $tenant = Tenant::create([
            'subdomain'   => $subdomain,
            'domain'      => $domain,
            'nome'        => $nome,
            'cnpj'        => $cnpj !== '' ? $cnpj : null,
            'uf'          => $uf !== '' ? $uf : null,
            'driver'      => 'pgsql', // único driver de tenant (ADR-0001)
            'db_host'     => $dbHost,
            'db_port'     => $dbPort,
            'db_name'     => $dbName,
            'db_username' => $dbUser,
            'db_password' => $dbPass,
            'db_schema'   => 'public',
            'active'      => true,
        ]);

        // O ResolveTenant guarda "não existe" por 60 s: sem isto o endereço demora a responder.
        try {
            Cache::store('file')->forget("tenant:id:{$domain}.{$subdomain}");
        } catch (\Throwable) {
        }

        $this->info("Tenant #{$tenant->id} cadastrado.");
        $this->newLine();
        $this->line('Próximo passo: criar o banco com migrations, estrutura e admins do legado:');
        $this->line("  <fg=cyan>php artisan docs:template-restore --tenant={$tenant->id}</>");
        $this->line('Depois acesse: <fg=cyan>'.$this->endereco($subdomain, $domain).'</>');

        return self::SUCCESS;
    }

    private function endereco(string $subdomain, string $domain): string
    {
        return str_starts_with($domain, ':') ? "http://localhost{$domain}" : "https://{$subdomain}.{$domain}";
    }
}
