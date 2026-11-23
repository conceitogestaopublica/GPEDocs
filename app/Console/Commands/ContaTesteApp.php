<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Portal\Cidadao;
use App\Models\Tenant;
use App\Models\User;
use App\Services\Portal\CidadaoPortalService;
use App\Tenant\TenantContext;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use RuntimeException;

/**
 * A conta de demonstração do app (GPE Serviços Digitais) para a revisão da App Store / Google Play:
 * o LOGIN do app é este. O app entra só pela conta do Portal do Cidadão, e o cadastro dela é daqui —
 * e-mail, CPF e senha. Os dados que ela mostra (painéis e holerites) são do GPE2, achados pelo CPF.
 *
 * Rode DEPOIS do seeder do GPE2 (PerfilAppTesteSeeder), que cria o servidor fictício com este CPF e
 * mostra no fim o id do funcionário dele:
 *
 *   APP_TESTE_SENHA='...' php artisan docs:conta-teste-app --tenant=sistema-apresentacao --funcionario=ID
 *
 * Perfis da conta (CidadaoPortalService::perfisPara): cidadão; servidor, pelo funcionário fictício do
 * GPE2 (a matrícula dele é encerrada, então o docs:importar-servidores não o traz); e prefeito, pela
 * marca acesso_painel_gestor. Contribuinte não sai daqui: é o vínculo fiscal, provado no portal.
 *
 * Sem APP_TESTE_SENHA a senha atual fica; conta nova exige. A conta só serve ao portal e ao app
 * (somente_portal) e não tem perfil do GED. Tudo numa transação: se o CPF ou o e-mail forem de outra
 * conta, ou se a conferência do fim falhar (entra pelo e-mail e pelo CPF, com os três perfis), nada é
 * gravado. --simular faz tudo e desfaz.
 */
class ContaTesteApp extends Command
{
    protected $signature = 'docs:conta-teste-app
        {--tenant= : Tenant alvo (id ou subdomínio)}
        {--funcionario= : Id do funcionário fictício no GPE2 (rh_funcionario), mostrado pelo seeder de lá}
        {--simular : Faz e confere tudo, sem gravar}';

    protected $description = 'Cria ou atualiza a conta de demonstração do app (revisão da Apple e do Google)';

    /** Os mesmos do PerfilAppTesteSeeder do GPE2. */
    public const EMAIL = 'app.teste@gpecloud.com.br';

    public const CPF = '91315042053';

    public const NOME = 'Servidor de Demonstração';

    public function handle(CidadaoPortalService $portal): int
    {
        $tenant = Tenant::active()->get()->first(fn (Tenant $t) => (string) $t->id === (string) $this->option('tenant')
            || $t->subdomain === $this->option('tenant'));
        if (! $tenant) {
            $this->error('Informe --tenant (id ou subdomínio) de um tenant ativo do GPEDocs.');

            return self::FAILURE;
        }
        $funcionarioId = (int) $this->option('funcionario');
        if ($funcionarioId <= 0) {
            $this->error('Informe --funcionario com o id que o PerfilAppTesteSeeder do GPE2 mostrou.');

            return self::FAILURE;
        }

        $ctx = app(TenantContext::class);
        $ctx->set($tenant);

        DB::beginTransaction();
        try {
            $senha = (string) getenv('APP_TESTE_SENHA');
            $usuario = $this->usuario($funcionarioId, $senha);
            $cidadao = $this->cidadao($usuario, $portal);
            $perfis = $this->conferir($cidadao, $senha, $portal);
            $this->option('simular') ? DB::rollBack() : DB::commit();
        } catch (RuntimeException $e) {
            DB::rollBack();
            $this->error($e->getMessage().' Nada foi gravado.');

            return self::FAILURE;
        } catch (\Throwable $e) {
            DB::rollBack();
            throw $e;
        } finally {
            $ctx->clear();
        }

        $this->table(['', ''], [
            ['Tenant', "#{$tenant->id} {$tenant->nome}"],
            ['Login no app', self::EMAIL.' ou CPF '.$this->mascara()],
            ['Senha', $senha !== '' ? 'a de APP_TESTE_SENHA' : 'mantida a atual'],
            ['Usuário / cidadão', "#{$usuario->id} / #{$cidadao->id}"],
            ['Perfis no app', implode(', ', $perfis)],
        ]);
        $this->option('simular') ? $this->warn('Simulação: nada foi gravado.') : $this->info('Conta pronta.');

        return self::SUCCESS;
    }

    private function usuario(int $funcionarioId, string $senha): User
    {
        if (! Schema::hasColumn('users', 'acesso_painel_gestor')) {
            throw new RuntimeException('Falta a migration users_acesso_painel_gestor: rode antes o tenant:migrate.');
        }

        $usuario = User::whereRaw('lower(trim(email)) = ?', [self::EMAIL])->first();
        if ($usuario?->super_admin) {
            throw new RuntimeException("O usuário #{$usuario->id} com o e-mail da conta é super admin.");
        }
        if (User::documentoEmOutraConta(self::CPF, $usuario?->id)) {
            throw new RuntimeException('O CPF da conta de teste já é de outro usuário.');
        }
        $outro = User::where('legado_funcionario_id', $funcionarioId)->when($usuario, fn ($q) => $q->whereKeyNot($usuario->id))->value('id');
        if ($outro) {
            throw new RuntimeException("O funcionário fictício do GPE2 (#{$funcionarioId}) está ligado a outro usuário (#{$outro}).");
        }
        if (! $usuario && $senha === '') {
            throw new RuntimeException('A conta ainda não existe: informe a senha em APP_TESTE_SENHA.');
        }

        $ugId = $usuario?->ug_id
            ?? DB::table('ugs')->whereNotNull('portal_slug')->orderBy('id')->value('id');
        if (! $ugId) {
            throw new RuntimeException('O tenant não tem UG com portal.');
        }

        $usuario ??= new User;
        $usuario->forceFill([
            'name'                  => self::NOME,
            'email'                 => self::EMAIL,
            'email_verified_at'     => $usuario->email_verified_at ?? now(),
            'cpf'                   => $this->mascara(),
            'tipo'                  => 'interno',
            'acesso_geral_ug'       => false,
            'somente_portal'        => true,
            'acesso_painel_gestor'  => true,
            'ug_id'                 => $ugId,
            'legado_funcionario_id' => $funcionarioId,
            'suspenso_em'           => null,
            'suspenso_por'          => null,
            'suspenso_motivo'       => null,
            'senha_trocar'          => false,
        ] + ($senha !== '' ? ['password' => Hash::make($senha), 'senha_alterada_em' => now()] : []));
        $usuario->saveQuietly();
        DB::table('user_ugs')->updateOrInsert(['user_id' => $usuario->id, 'ug_id' => $ugId],
            ['principal' => true, 'created_at' => now(), 'updated_at' => now()]);

        return $usuario;
    }

    /** O cadastro do portal ligado ao usuário. O e-mail ou o CPF em outro cadastro impediria a entrada. */
    private function cidadao(User $usuario, CidadaoPortalService $portal): Cidadao
    {
        $cidadao = Cidadao::naoExcluido()->where('user_id', $usuario->id)->first();
        $outros = Cidadao::naoExcluido()
            ->when($cidadao, fn ($q) => $q->whereKeyNot($cidadao->id))
            ->where(fn ($q) => $q->whereRaw('lower(email) = ?', [self::EMAIL])
                ->orWhereRaw("regexp_replace(coalesce(cpf, ''), '[^0-9]', '', 'g') = ?", [self::CPF]))
            ->get();

        // Cadastro aberto com o e-mail da conta, ainda sem usuário: é o da própria conta.
        if (! $cidadao && $outros->count() === 1 && ! $outros[0]->user_id && mb_strtolower((string) $outros[0]->email) === self::EMAIL) {
            $cidadao = $outros->pop();
        }
        if ($outros->isNotEmpty()) {
            throw new RuntimeException('O e-mail ou o CPF da conta está em outro cadastro do portal (#'.$outros->pluck('id')->implode(', #').').');
        }

        if (! $cidadao) {
            return $portal->porIdentidadeComprovada(self::EMAIL)
                ?? throw new RuntimeException('Não foi possível criar o cadastro do portal.');
        }
        $cidadao->forceFill([
            'user_id'             => $usuario->id,
            'nome'                => self::NOME,
            'email'               => self::EMAIL,
            'cpf'                 => self::CPF,
            'ativo'               => true,
            'email_verificado_em' => $cidadao->email_verificado_em ?? now(),
        ])->save();

        return $cidadao;
    }

    /**
     * O que o app vai receber, pelo mesmo serviço do login do portal.
     *
     * @return list<string> os perfis
     */
    private function conferir(Cidadao $cidadao, string $senha, CidadaoPortalService $portal): array
    {
        foreach ([self::EMAIL, self::CPF] as $login) {
            $entrou = $senha !== '' ? $portal->autenticar($login, $senha) : $portal->porLogin($login);
            if ($entrou?->id !== $cidadao->id) {
                throw new RuntimeException("Conferência: o login por {$login} não leva à conta de teste.");
            }
        }

        $conta = CidadaoPortalService::paraApi($cidadao->fresh());
        $faltam = array_diff(['cidadao', 'servidor', 'prefeito'], $conta['perfis']);
        if ($conta['documento'] !== self::CPF || $faltam !== []) {
            throw new RuntimeException('Conferência: a conta não ficou com o CPF e os perfis esperados (faltam: '.implode(', ', $faltam).').');
        }

        return $conta['perfis'];
    }

    private function mascara(): string
    {
        return preg_replace('/^(\d{3})(\d{3})(\d{3})(\d{2})$/', '$1.$2.$3-$4', self::CPF);
    }
}
