<?php

declare(strict_types=1);

namespace App\Support;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Trilha de eventos (TR 4.25.12–14): acessos, cadastros, configurações e operações de cada usuário,
 * com código do evento, setor no momento, IP, SO, dispositivo e navegador. Falha ao gravar nunca
 * interrompe a operação do usuário — é auditoria, não condição.
 */
class Eventos
{
    public const CATEGORIAS = ['acesso' => 'Acesso', 'cadastro' => 'Cadastro', 'configuracao' => 'Configuração', 'processo' => 'Processo',
        'documento' => 'Documento', 'comunicacao' => 'Comunicação', 'operacao' => 'Operação'];

    private const SIGLA = ['acesso' => 'ACE', 'cadastro' => 'CAD', 'configuracao' => 'CFG', 'processo' => 'PRO', 'documento' => 'DOC',
        'comunicacao' => 'COM', 'operacao' => 'OPE'];

    /** Códigos fixos (os demais são SIGLA.INC/ALT/EXC/EXE, ver deRota). */
    public const CODIGOS = [
        'ACE.ENTRADA' => 'Entrada no sistema', 'ACE.RECUSA' => 'Entrada recusada', 'ACE.SAIDA' => 'Saída do sistema', 'ACE.UG' => 'Troca de unidade gestora',
        'INC' => 'Inclusão', 'ALT' => 'Alteração', 'EXC' => 'Exclusão', 'EXE' => 'Operação',
    ];

    /** Primeiro trecho do nome da rota → [categoria, rótulo]. Ausente = não registra. */
    private const ROTAS = [
        'configuracoes' => ['configuracao', 'Configurações'], 'admin' => ['configuracao', 'Administração'], 'modelos-texto' => ['configuracao', 'Modelos de texto'],
        'perfil' => ['cadastro', 'Meu perfil'], 'organograma' => ['cadastro', 'Organograma'],
        'processos' => ['processo', 'Processos'], 'tramitacoes' => ['processo', 'Tramitação'], 'flow' => ['processo', 'Fluxos e processos'],
        'prazos' => ['processo', 'Prazos'], 'participantes' => ['processo', 'Participantes'], 'fluxos' => ['processo', 'Fluxos'], 'fiscal' => ['processo', 'App da fiscalização'],
        'documentos' => ['documento', 'Documentos'], 'pastas' => ['documento', 'Pastas'], 'lixeira' => ['documento', 'Lixeira'], 'capturar' => ['documento', 'Captura'],
        'assinaturas' => ['documento', 'Assinaturas'], 'assinatura-pacote' => ['documento', 'Assinatura em pacote'], 'certificados' => ['documento', 'Certificados'],
        'memorandos' => ['comunicacao', 'Memorandos'], 'circulares' => ['comunicacao', 'Circulares'], 'oficios' => ['comunicacao', 'Ofícios'],
        'pareceres' => ['comunicacao', 'Pareceres'], 'atos' => ['comunicacao', 'Atos oficiais'], 'diario' => ['comunicacao', 'Diário Oficial'],
    ];

    /** Rótulos dos trechos de rota mais comuns (o resto vira texto com espaços). */
    private const TERMOS = [
        'usuarios' => 'usuários', 'perfis' => 'perfis', 'formularios' => 'formulários', 'fiscalizacao' => 'fiscalização', 'fiscalizacao-interna' => 'gerar fiscalização',
        'diligencias' => 'diligências', 'urbanismo' => 'urbanismo', 'zonas' => 'zonas', 'camadas' => 'camadas', 'lotes' => 'lotes', 'config' => 'configuração',
        'expediente' => 'expediente e feriados', 'identidade-visual' => 'identidade visual', 'certificado-prefeitura' => 'certificado da prefeitura',
        'carta-servicos' => 'carta de serviços', 'portal-textos' => 'textos do portal', 'integracao-tributaria' => 'integração tributária',
        'sistemas-integrados' => 'sistemas integrados', 'ugs' => 'unidades gestoras', 'tipos-processo' => 'tipos de processo', 'tipos-documentais' => 'tipos documentais',
        'classificacao' => 'plano de classificação', 'oficios-modelos' => 'modelos de texto', 'despachar' => 'despacho', 'receber' => 'recebimento',
        'concluir' => 'conclusão', 'arquivar' => 'arquivamento', 'anexos' => 'anexos', 'reativar' => 'reativação', 'suspender' => 'suspensão',
        'senha' => 'senha', 'dois-fatores' => 'verificação em duas etapas', 'contatos' => 'contatos', 'assinar' => 'assinatura', 'publicar' => 'publicação',
    ];

    /** Trechos do nome que fazem a operação ser cadastro (pessoas e estrutura), mesmo dentro de configurações. */
    private const CADASTROS = ['usuarios', 'perfis', 'roles', 'contatos', 'ugs', 'vereadores', 'organograma', 'unidades'];

    public static function registrar(string $codigo, string $categoria, string $descricao, array $extra = []): void
    {
        try {
            $req = request();
            $u = $extra['usuario'] ?? Auth::user();
            unset($extra['usuario']);
            [$so, $disp, $nav] = AgenteUsuario::analisar($req->userAgent());
            DB::table('ged_eventos')->insert([
                'codigo' => $codigo, 'categoria' => $categoria, 'descricao' => mb_substr($descricao, 0, 500),
                'usuario_id' => $u?->id, 'unidade_id' => $u?->unidade_id, 'ug_id' => $req->hasSession() ? $req->session()->get('ug_id', $u?->ug_id) : $u?->ug_id,
                'login' => $extra['login'] ?? null, 'rota' => $extra['rota'] ?? $req->route()?->getName(),
                'entidade_tipo' => $extra['entidade_tipo'] ?? null, 'entidade_id' => $extra['entidade_id'] ?? null,
                'sucesso' => $extra['sucesso'] ?? true,
                'detalhes' => isset($extra['detalhes']) ? json_encode($extra['detalhes'], JSON_UNESCAPED_UNICODE) : null,
                'ip' => $req->ip(), 'so' => $so, 'dispositivo' => $disp, 'navegador' => $nav,
                'user_agent' => mb_substr((string) $req->userAgent(), 0, 500), 'created_at' => now(),
            ]);
        } catch (\Throwable $e) {
            Log::warning('Evento não registrado', ['codigo' => $codigo, 'erro' => $e->getMessage()]);
        }
    }

    /** Evento de uma requisição que altera dados (middleware RegistrarEvento). */
    public static function deRota(Request $request, bool $sucesso, array $erros = []): void
    {
        $nome = (string) $request->route()?->getName();
        $partes = explode('.', $nome);
        if (in_array($partes[0], ['selecionar-ug', 'trocar-ug'], true)) {
            self::registrar('ACE.UG', 'acesso', 'Troca de unidade gestora', ['sucesso' => $sucesso]);

            return;
        }
        if (! isset(self::ROTAS[$partes[0]])) {
            return;
        }
        [$categoria, $modulo] = self::ROTAS[$partes[0]];
        if (array_intersect($partes, self::CADASTROS) || str_contains($nome, 'contatos')) {
            $categoria = 'cadastro';
        }
        $ultimo = end($partes);
        $acao = match (true) {
            $request->isMethod('DELETE') || in_array($ultimo, ['destroy', 'excluir', 'remover'], true) => 'EXC',
            $request->isMethod('PUT') || $request->isMethod('PATCH') || in_array($ultimo, ['update', 'atualizar', 'salvar'], true) && count($request->route()->parameters()) > 0 => 'ALT',
            in_array($ultimo, ['store', 'salvar', 'criar', 'importar'], true) => 'INC',
            default => 'EXE',
        };
        $verbo = ['INC' => 'Incluiu', 'ALT' => 'Alterou', 'EXC' => 'Excluiu', 'EXE' => 'Executou'][$acao];
        $trecho = collect(array_slice($partes, 1))->reject(fn ($p) => in_array($p, ['store', 'update', 'destroy'], true))
            ->map(fn ($p) => self::TERMOS[$p] ?? str_replace(['-', '_'], ' ', $p))->implode(' › ');
        $params = collect($request->route()->parameters())->filter(fn ($v) => is_scalar($v))->all();
        $entidadeId = collect($params)->first(fn ($v) => is_numeric($v));

        self::registrar(self::SIGLA[$categoria] . '.' . $acao, $categoria,
            "{$verbo}: {$modulo}" . ($trecho !== '' ? " › {$trecho}" : '') . ($entidadeId ? " #{$entidadeId}" : '') . ($sucesso ? '' : ' (não concluído)'),
            ['entidade_tipo' => $partes[0], 'entidade_id' => $entidadeId ? (int) $entidadeId : null, 'sucesso' => $sucesso,
                'detalhes' => array_filter(['metodo' => $request->method(), 'parametros' => $params ?: null, 'campos_com_erro' => $erros ?: null])]);
    }
}
