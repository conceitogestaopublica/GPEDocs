<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Http\Middleware\RegistrarEvento;
use App\Http\Middleware\RequireTenant;
use App\Http\Middleware\ResolveTenant;
use App\Mail\DecisaoManifestacao;
use App\Mail\ProtocoloTransparencia;
use App\Models\Processo\Processo;
use App\Models\SistemaIntegrado;
use App\Support\ExternoDoProcesso;
use App\Support\RespostaManifestante;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Schema;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

/**
 * e-SIC e Ouvidoria do Portal da Transparência (sqlite em memória). O que falha calado aqui é sério: manifestante
 * identificado sem querer, pedido na conta de outra pessoa ou aberto por quem não tem a chave.
 */
class AtendimentoTransparenciaTest extends TestCase
{
    private const CPF = '529.982.247-25';

    private string $token;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutMiddleware([ResolveTenant::class, RequireTenant::class, RegistrarEvento::class]);
        $pdo = DB::connection()->getPdo();
        $pdo->sqliteCreateFunction('SPLIT_PART', fn ($t, $s, $n) => explode($s, (string) $t)[$n - 1] ?? '');
        $pdo->sqliteCreateFunction('regexp_replace', fn ($t) => preg_replace('/\D/', '', (string) $t));
        Mail::fake();

        $this->tabela('ged_sistemas_integrados', 'codigo nome descricao api_token_hash api_token_prefix webhook_secret eventos_assinatura ativo:b ultimo_uso_em');
        $this->tabela('ugs', 'codigo nome ativo:b portal_slug');
        $this->tabela('proc_tipos_processo', 'nome sigla modalidade ativo:b schema_formulario prazo_dias_uteis:b sla_padrao_horas:i classificacao_id:i');
        $this->tabela('proc_tipo_etapas', 'tipo_processo_id:i ordem:i ativo:b sla_horas:i responsavel_id:i');
        $this->tabela('ug_organograma', 'nome sigla');
        $this->tabela('portal_servicos', 'ug_id:i categoria_id:i titulo slug descricao_curta publicado:b permite_anonimo:b setor_responsavel_id:i tipo_processo_id:i ordem:i');
        $this->tabela('portal_solicitacoes', 'codigo ug_id:i servico_id:i cidadao_id:i procurador_cidadao_id:i anonima:b status descricao telefone_contato email_contato manifestacao_tipo identificacao chave_acesso atendente_id:i processo_id:i resposta respondida_em dados_formulario');
        $this->tabela('portal_solicitacao_eventos', 'solicitacao_id:i tipo autor_tipo autor_nome autor_user_id:i autor_cidadao_id:i status_anterior status_novo mensagem setor_nome setor_sigla');
        $this->tabela('portal_solicitacao_anexos', 'solicitacao_id:i nome arquivo_path tamanho:i mime_type hash_sha256');
        $this->tabela('proc_processos', 'ug_id:i numero_protocolo tipo_processo_id:i assunto descricao dados_formulario requerente_nome requerente_cpf requerente_email requerente_telefone setor_origem status prioridade aberto_por:i canal manifestacao_tipo identificacao etapa_atual_id:i prazo_legal_em prorrogado_em prorrogacao_motivo decisao_final decisao_final_em concluido_em contato_id:i classificacao_id:i');
        $this->tabela('proc_tramitacoes', 'processo_id:i tipo_etapa_id:i ordem:i frente:i setor_origem setor_destino destino_unidade_id:i remetente_id:i destinatario_id:i status despachado_em sla_horas:i prazo');
        $this->tabela('proc_historico', 'processo_id:i usuario_id:i acao detalhes ip');
        $this->tabela('proc_participantes', 'processo_id:i cpf_cnpj');
        $this->tabela('esic_recursos', 'processo_id:i instancia:i motivo origem cidadao_id:i registrado_por:i interposto_em decidir_ate decisao fundamentacao decidido_por:i decidido_em');
        $this->tabela('ged_classificacoes', 'ativo:b');
        $this->tabela('ged_classificacao_modulos', 'modulo classificacao_id:i');
        $this->tabela('expediente', 'dias_semana');
        $this->tabela('expediente_feriados', 'data tipo');
        $this->tabela('ged_emails_rastreados', 'token modulo referencia_tipo referencia_id:i remetente_id:i destinatario assunto status enviado_em entregue_em aberto_em clicado_em erro');
        foreach (['ged_user_roles' => 'role_id:i user_id:i', 'ged_role_permissions' => 'role_id:i permission_id:i', 'ged_permissions' => 'nome', 'users' => 'suspenso_em'] as $t => $c) {
            $this->tabela($t, $c);
        }

        DB::table('ugs')->insert([['id' => 1, 'codigo' => 'UG-001', 'nome' => 'Prefeitura', 'ativo' => true], ['id' => 2, 'codigo' => 'UG-002', 'nome' => 'Câmara', 'ativo' => true]]);
        DB::table('proc_tipos_processo')->insert([
            ['id' => 1, 'nome' => 'e-SIC', 'sigla' => 'LAI', 'modalidade' => 'esic', 'ativo' => true, 'prazo_dias_uteis' => false],
            ['id' => 2, 'nome' => 'Ouvidoria', 'sigla' => 'OUV', 'modalidade' => 'ouvidoria', 'ativo' => true, 'prazo_dias_uteis' => true],
        ]);
        DB::table('ug_organograma')->insert(['id' => 5, 'nome' => 'Protocolo', 'sigla' => 'PRO']);
        $servico = fn (int $id, int $ug, string $slug, int $tipo, array $mais = []) => $mais + ['id' => $id, 'ug_id' => $ug, 'titulo' => $slug, 'slug' => $slug, 'publicado' => true,
            'permite_anonimo' => true, 'setor_responsavel_id' => 5, 'tipo_processo_id' => $tipo, 'ordem' => $id];
        DB::table('portal_servicos')->insert([$servico(1, 1, 'pedido', 1), $servico(2, 1, 'manifestacao', 2), $servico(3, 2, 'da-camara', 1),
            $servico(4, 1, 'sem-setor', 1, ['setor_responsavel_id' => null]), $servico(5, 1, 'nao-publicado', 1, ['publicado' => false])]);

        $sistema = new SistemaIntegrado(['codigo' => 'gpe2', 'nome' => 'GPE2', 'ativo' => true]);
        $this->token = $sistema->gerarToken();
        $sistema->save();
    }

    public function test_so_oferece_servico_publicado_da_ug_que_abre_processo(): void
    {
        $r = $this->pedir('GET', '')->assertOk();

        $this->assertSame(['pedido'], array_column($r->json('esic.servicos'), 'slug'));
        $this->assertFalse($r->json('esic.servicos.0.permite_anonimo'), 'pedido de informação não é anônimo');
        $this->assertTrue($r->json('ouvidoria.servicos.0.permite_anonimo'));
        $this->assertSame(['da-camara'], array_column($this->pedir('GET', '', ['ug_codigo' => 'UG-002'])->json('esic.servicos'), 'slug'));
    }

    public function test_outro_sistema_integrado_nao_entra(): void
    {
        SistemaIntegrado::query()->update(['codigo' => 'tributario']);
        $this->pedir('GET', '')->assertForbidden();
        $this->json('GET', '/api/integracoes/atendimento?ug_codigo=UG-001')->assertUnauthorized();
    }

    public function test_pedido_de_informacao_abre_processo_com_prazo_e_se_acompanha_pela_chave(): void
    {
        $r = $this->pedir('POST', '/esic', $this->pedido())->assertCreated();
        $p = Processo::query()->first();

        $this->assertSame(['transparencia', 'MARIA', '52998224725', 'maria@exemplo.test'], [$p->canal, $p->requerente_nome, $p->requerente_cpf, $p->requerente_email]);
        $this->assertNotNull($r->json('prazo'));
        $this->assertNull($p->contato_id, 'documento não conferido não entra na base de contatos');
        $this->assertNull(DB::table('portal_solicitacoes')->value('email_contato'));
        Mail::assertSent(ProtocoloTransparencia::class, fn ($m) => $m->hasTo('maria@exemplo.test') && $m->chave === $r->json('chave'));

        $codigo = $r->json('codigo');
        $this->pedir('POST', "/solicitacoes/{$codigo}", ['chave' => 'ERRADA00'])->assertNotFound();
        $this->pedir('POST', "/solicitacoes/{$codigo}", ['chave' => ''])->assertNotFound();
        $this->pedir('POST', "/solicitacoes/{$codigo}", ['chave' => $r->json('chave'), 'ug_codigo' => 'UG-002'])->assertNotFound();
        $a = $this->pedir('POST', "/solicitacoes/{$codigo}", ['chave' => strtolower($r->json('chave'))])->assertOk();
        $this->assertSame(['esic', 'Quero ver as notas.', false], [$a->json('modalidade'), $a->json('descricao'), $a->json('recurso.pode_recorrer')]);
        foreach (['MARIA', '52998224725', 'maria@exemplo.test'] as $dado) {
            $this->assertStringNotContainsString($dado, $a->getContent(), 'o acompanhamento não mostra quem pediu');
        }
    }

    public function test_muitas_chaves_erradas_travam_o_codigo(): void
    {
        $r = $this->pedir('POST', '/esic', $this->pedido());
        for ($i = 0; $i < 10; $i++) {
            $this->pedir('POST', "/solicitacoes/{$r->json('codigo')}", ['chave' => "X{$i}"])->assertNotFound();
        }
        $this->pedir('POST', "/solicitacoes/{$r->json('codigo')}", ['chave' => $r->json('chave')])->assertStatus(429);
    }

    public function test_pedido_de_informacao_exige_identificacao_e_contato(): void
    {
        foreach (['nome', 'documento', 'descricao', 'servico'] as $campo) {
            $this->pedir('POST', '/esic', [$campo => null] + $this->pedido())->assertJsonValidationErrors($campo);
        }
        $this->pedir('POST', '/esic', ['documento' => '111.111.111-11'] + $this->pedido())->assertJsonValidationErrors('documento');
        $this->pedir('POST', '/esic', ['email' => null, 'telefone' => null] + $this->pedido())->assertJsonValidationErrors('email');
        $this->pedir('POST', '/esic', ['identificacao' => 'anonima'] + $this->pedido())->assertJsonValidationErrors('identificacao');
        $this->pedir('POST', '/esic', ['servico' => 'da-camara'] + $this->pedido())->assertNotFound();
        $this->pedir('POST', '/esic', ['servico' => 'manifestacao'] + $this->pedido())->assertNotFound();
        $this->assertSame(0, Processo::query()->count());

        $this->pedir('POST', '/esic', ['email' => null, 'telefone' => '3599999999'] + $this->pedido())->assertCreated();
    }

    public function test_manifestacao_anonima_nao_guarda_quem_e_e_a_sigilosa_fica_so_no_processo(): void
    {
        $base = ['servico' => 'manifestacao', 'descricao' => 'Buraco na rua.', 'manifestacao_tipo' => 'reclamacao'];
        $this->pedir('POST', '/ouvidoria', ['identificacao' => 'anonima', 'nome' => 'MARIA'] + $base)->assertJsonValidationErrors('nome');
        $this->pedir('POST', '/ouvidoria', ['identificacao' => 'anonima'] + $base)->assertCreated();
        $p = Processo::query()->first();
        $this->assertSame(['anonima', null, null, null], [$p->identificacao, $p->requerente_nome, $p->requerente_cpf, $p->requerente_email]);

        $r = $this->pedir('POST', '/ouvidoria', ['identificacao' => 'sigilosa', 'nome' => 'MARIA', 'documento' => self::CPF, 'email' => 'maria@exemplo.test'] + $base)->assertCreated();
        $p = Processo::query()->orderByDesc('id')->first();
        $this->assertSame(['sigilosa', 'MARIA', 'reclamacao'], [$p->identificacao, $p->requerente_nome, $p->manifestacao_tipo]);
        $fora = json_encode([DB::table('portal_solicitacoes')->get(), DB::table('portal_solicitacao_eventos')->get(), DB::table('ged_emails_rastreados')->get()]);
        $this->assertStringNotContainsString('MARIA', $fora);
        $this->assertStringNotContainsString('maria@', $fora);

        DB::table('portal_servicos')->where('id', 2)->update(['permite_anonimo' => false]);
        $this->pedir('POST', '/ouvidoria', ['identificacao' => 'anonima'] + $base)->assertUnprocessable();
    }

    public function test_documento_digitado_sem_login_nao_entra_na_conta_de_ninguem(): void
    {
        $this->pedir('POST', '/esic', $this->pedido())->assertCreated();
        $this->assertSame(0, ExternoDoProcesso::doDocumento(Processo::query(), self::CPF)->count());

        Processo::query()->update(['canal' => 'balcao']);
        $this->assertSame(1, ExternoDoProcesso::doDocumento(Processo::query(), self::CPF)->count());
    }

    public function test_resposta_vai_por_email_e_cabe_recurso_pela_chave(): void
    {
        $r = $this->pedir('POST', '/esic', $this->pedido());
        $url = "/solicitacoes/{$r->json('codigo')}";
        $this->pedir('POST', "{$url}/recurso", ['chave' => $r->json('chave'), 'motivo' => 'x'])->assertUnprocessable();

        $p = Processo::query()->first();
        $p->update(['status' => 'concluido', 'concluido_em' => now(), 'decisao_final' => 'Negado.', 'decisao_final_em' => now()]);
        $this->assertTrue(RespostaManifestante::enviarDecisao($p->fresh(), 'Negado.'));
        Mail::assertSent(DecisaoManifestacao::class, fn ($m) => $m->hasTo('maria@exemplo.test'));

        $a = $this->pedir('POST', $url, ['chave' => $r->json('chave')]);
        $this->assertSame(['Negado.', true, 1], [$a->json('resposta'), $a->json('recurso.pode_recorrer'), $a->json('recurso.instancia')]);
        $this->pedir('POST', "{$url}/recurso", ['chave' => 'ERRADA00', 'motivo' => 'x'])->assertNotFound();
        $this->pedir('POST', "{$url}/recurso", ['chave' => $r->json('chave'), 'motivo' => 'Faltou a nota 2.'])->assertCreated();
        $this->assertSame(['transparencia', 'Faltou a nota 2.'], [DB::table('esic_recursos')->value('origem'), DB::table('esic_recursos')->value('motivo')]);
    }

    private function pedido(): array
    {
        return ['servico' => 'pedido', 'descricao' => 'Quero ver as notas.', 'nome' => 'MARIA', 'documento' => self::CPF, 'email' => 'maria@exemplo.test'];
    }

    private function pedir(string $metodo, string $caminho, array $dados = []): TestResponse
    {
        return $this->json($metodo, "/api/integracoes/atendimento{$caminho}", $dados + ['ug_codigo' => 'UG-001'], ['Authorization' => "Bearer {$this->token}"]);
    }

    private function tabela(string $nome, string $colunas): void
    {
        Schema::create($nome, function ($t) use ($colunas) {
            $t->id();
            foreach (explode(' ', $colunas) as $c) {
                [$col, $tipo] = explode(':', $c) + [1 => 't'];
                match ($tipo) {
                    'i' => $t->integer($col)->nullable(),
                    'b' => $t->boolean($col)->nullable(),
                    default => $t->text($col)->nullable(),
                };
            }
            $t->timestamps();
            $t->softDeletes();
        });
    }
}
