<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Mail\ProtocoloTransparencia;
use App\Models\Portal\Servico;
use App\Models\Portal\Solicitacao;
use App\Models\Portal\SolicitacaoEvento;
use App\Models\Processo\Processo;
use App\Models\Ug;
use App\Services\Portal\AnexosSolicitacaoService;
use App\Services\Portal\CidadaoPortalException;
use App\Services\Portal\CidadaoPortalService;
use App\Services\Portal\RegistrarSolicitacaoService;
use App\Support\Ouvidoria;
use App\Support\PainelOuvidoria;
use App\Support\RecursoEsic;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\Rule;

/**
 * e-SIC e Ouvidoria pelo Portal da Transparência, que chega pelo gpe2 (token de sistema integrado). O cidadão não tem
 * conta: acompanha e recorre pelo código e pela chave. É o mesmo processo do Portal do Cidadão e do balcão.
 */
class AtendimentoTransparenciaController extends Controller
{
    /** O que cada modalidade oferece nesta UG; serviço sem assunto ou sem setor não abre processo, então não aparece. */
    public function index(Request $request): JsonResponse
    {
        $ug = $this->ug($request);

        return response()->json(array_map(fn (string $m) => [
            'servicos' => $this->servicos($ug, $m)->map(fn (Servico $s) => ['slug' => $s->slug, 'titulo' => $s->titulo, 'descricao' => $s->descricao_curta,
                'permite_anonimo' => $m === 'ouvidoria' && $s->permite_anonimo])->values(),
        ], ['esic' => 'esic', 'ouvidoria' => 'ouvidoria']) + [
            'manifestacoes' => Ouvidoria::MANIFESTACOES,
            'anexos' => ['quantidade' => AnexosSolicitacaoService::MAX_ARQUIVOS, 'bytes' => AnexosSolicitacaoService::MAX_BYTES],
        ]);
    }

    public function store(Request $request, string $modalidade, RegistrarSolicitacaoService $registrar, AnexosSolicitacaoService $anexos): JsonResponse
    {
        $ug = $this->ug($request);
        $anonima = $modalidade === 'ouvidoria' && $request->input('identificacao') === 'anonima';
        $dados = $request->validate([
            'servico' => 'required|string|max:200',
            'descricao' => 'required|string|max:5000',
            'identificacao' => [$modalidade === 'ouvidoria' ? 'required' : 'prohibited', Rule::in(array_keys(Ouvidoria::IDENTIFICACOES))],
            'manifestacao_tipo' => [$modalidade === 'ouvidoria' ? 'required' : 'prohibited', Rule::in(array_keys(Ouvidoria::MANIFESTACOES))],
            'nome' => [$anonima ? 'prohibited' : 'required', 'string', 'max:200'],
            'documento' => [$anonima ? 'prohibited' : 'required', 'string', 'max:20', function ($a, $v, $falha) {
                if (! CidadaoPortalService::documentoValido((string) $v)) {
                    $falha('CPF ou CNPJ inválido.');
                }
            }],
            'email' => [...($anonima ? ['prohibited'] : ['nullable', 'required_without:telefone']), 'email', 'max:150'],
            'telefone' => [$anonima ? 'prohibited' : 'nullable', 'string', 'max:20'],
            'url_acompanhar' => 'nullable|url:http,https|max:255',
            'anexos' => 'nullable|array|max:'.AnexosSolicitacaoService::MAX_ARQUIVOS,
            'anexos.*.nome' => 'required|string|max:200',
            'anexos.*.conteudo' => 'required|string',
        ]);

        $servico = $this->servicos($ug, $modalidade)->firstWhere('slug', $dados['servico']);
        if (! $servico) {
            return response()->json(['erro' => 'Este atendimento não está disponível.'], 404);
        }
        if ($anonima && ! $servico->permite_anonimo) {
            return response()->json(['erro' => 'Este serviço não aceita manifestação anônima.'], 422);
        }
        try {
            $prontos = $anexos->preparar($dados['anexos'] ?? []);
            $anexos->conferirTotal($prontos);
        } catch (CidadaoPortalException $e) {
            return response()->json(['erro' => $e->getMessage()], 422);
        }

        // Os dados de quem pede ficam só no processo, que guarda o sigilo; a solicitação não os repete.
        $solicitacao = DB::transaction(function () use ($registrar, $anexos, $ug, $servico, $dados, $anonima, $prontos) {
            $s = $registrar->registrar($ug, $servico, null, [
                'descricao' => $dados['descricao'],
                'manifestacao_tipo' => $dados['manifestacao_tipo'] ?? null,
                'identificacao' => $dados['identificacao'] ?? null,
            ], anonima: $anonima, externo: [
                'nome' => $dados['nome'] ?? null,
                'documento' => isset($dados['documento']) ? preg_replace('/\D/', '', $dados['documento']) : null,
                'email' => $dados['email'] ?? null,
                'telefone' => $dados['telefone'] ?? null,
            ]);
            $anexos->gravar($s, null, $prontos);

            return $s;
        });
        $processo = $this->processo($solicitacao);

        if (! empty($dados['email'])) {
            try {   // falha no e-mail não desfaz o pedido: o código e a chave vão também na resposta
                Mail::to($dados['email'])->send(new ProtocoloTransparencia($processo, $solicitacao->codigo, $solicitacao->chave_acesso, $dados['url_acompanhar'] ?? null));
            } catch (\Throwable $e) {
                Log::warning('Protocolo do Portal da Transparência não enviado', ['solicitacao' => $solicitacao->id, 'erro' => $e->getMessage()]);
            }
        }

        return response()->json(['codigo' => $solicitacao->codigo, 'chave' => $solicitacao->chave_acesso,
            'protocolo' => $processo->numero_protocolo, 'prazo' => $processo->prazo_legal_em?->toIso8601String()], 201);
    }

    public function acompanhar(Request $request, string $codigo): JsonResponse
    {
        $s = $this->pelaChave($request, $codigo);
        $p = $this->processo($s);
        $quem = fn (SolicitacaoEvento $e) => $e->autor_tipo === 'cidadao' ? 'Solicitante' : ($e->setor_nome ?: 'Órgão');

        return response()->json([
            'codigo' => $s->codigo,
            'protocolo' => $p->numero_protocolo,
            'modalidade' => Ouvidoria::modalidade($p),
            'servico' => $s->servico?->titulo,
            'status' => Solicitacao::STATUS[$s->status] ?? $s->status,
            'aberta_em' => $s->created_at?->toIso8601String(),
            'descricao' => $s->descricao,
            'anexos' => $s->anexos->pluck('nome'),
            'resposta' => $p->decisao_final ?: $s->resposta,
            'respondida_em' => ($p->decisao_final_em ?? $s->respondida_em)?->toIso8601String(),
            'prazo' => Ouvidoria::situacaoPrazo($p),
            'prorrogacao' => $p->prorrogado_em ? $p->prorrogacao_motivo : null,
            'recurso' => $this->recursoDe($p),
            'eventos' => $s->eventos->map(fn (SolicitacaoEvento $e) => ['em' => $e->created_at?->toIso8601String(), 'quem' => $quem($e), 'mensagem' => $e->mensagem])->values(),
        ]);
    }

    /** Recurso contra a resposta do pedido de informação (LAI art. 15). */
    public function recurso(Request $request, string $codigo): JsonResponse
    {
        $s = $this->pelaChave($request, $codigo);
        $motivo = $request->validate(['motivo' => 'required|string|max:5000'])['motivo'];
        try {
            $r = DB::transaction(fn () => RecursoEsic::interpor($this->processo($s, trava: true), $motivo, 'transparencia', null, null));
        } catch (\RuntimeException $e) {
            return response()->json(['erro' => $e->getMessage()], 422);
        }

        return response()->json(['instancia' => (int) $r->instancia, 'decidir_ate' => Carbon::parse($r->decidir_ate)->toIso8601String()], 201);
    }

    /** Estatísticas públicas (LAI art. 30, III; Lei 13.460), sem dado de quem pediu. O assunto do balcão é texto livre: não sai. */
    public function estatisticas(Request $request, string $modalidade): JsonResponse
    {
        $ug = $this->ug($request);
        $ano = $request->integer('ano') ?: (int) now()->year;
        $i = PainelOuvidoria::indicadores(PainelOuvidoria::consulta($modalidade, ['de' => "{$ano}-01-01", 'ate' => "{$ano}-12-31"], $ug->id));

        return response()->json(['ano' => $ano] + array_intersect_key($i, array_flip([
            'total', 'abertos', 'vencidos', 'respondidos', 'prorrogados', 'pct_no_prazo', 'dias_medio', 'por_mes', 'por_tipo', 'por_canal', 'por_setor',
        ])));
    }

    private function ug(Request $request): Ug
    {
        $sistema = $request->attributes->get('sistema_integrado');
        if (! in_array($sistema?->codigo, (array) config('portal.api_sistemas_atendimento'), true)) {
            throw new HttpResponseException(response()->json(['erro' => 'Sistema sem acesso ao atendimento.'], 403));
        }
        $codigo = (string) $request->input('ug_codigo', '');
        $ug = $codigo === '' ? null : Ug::query()->where('codigo', $codigo)->where('ativo', true)->first();
        if (! $ug) {
            throw new HttpResponseException(response()->json(['erro' => "UG não encontrada: {$codigo}"], 422));
        }

        return $ug;
    }

    /** @return Collection<int, Servico> */
    private function servicos(Ug $ug, string $modalidade)
    {
        $assuntos = DB::table('proc_tipos_processo')->where('modalidade', $modalidade)->where('ativo', true)->pluck('id');

        return Servico::query()->withoutGlobalScope('ug')->where('ug_id', $ug->id)->where('publicado', true)
            ->whereIn('tipo_processo_id', $assuntos)->whereNotNull('setor_responsavel_id')
            ->orderBy('ordem')->orderBy('id')->get();
    }

    private function pelaChave(Request $request, string $codigo): Solicitacao
    {
        $ug = $this->ug($request);
        $tentativas = "atendimento-transparencia:{$ug->id}:{$codigo}";
        if (RateLimiter::tooManyAttempts($tentativas, 10)) {
            throw new HttpResponseException(response()->json(['erro' => 'Muitas tentativas. Aguarde alguns minutos.'], 429));
        }
        $chave = strtoupper(trim((string) $request->input('chave', '')));
        $s = $chave === '' ? null : Solicitacao::query()->withoutGlobalScope('ug')
            ->where('ug_id', $ug->id)->where('codigo', $codigo)->where('chave_acesso', $chave)->whereNotNull('processo_id')
            ->with(['servico', 'eventos', 'anexos'])->first();
        if (! $s || ! Ouvidoria::modalidade($this->processo($s))) {
            RateLimiter::hit($tentativas, 600);
            throw new HttpResponseException(response()->json(['erro' => 'Código ou chave não conferem.'], 404));
        }

        return $s;
    }

    private function processo(Solicitacao $s, bool $trava = false): Processo
    {
        return Processo::query()->withoutGlobalScope('ug')->with('tipoProcesso')->when($trava, fn ($q) => $q->lockForUpdate())->findOrFail($s->processo_id);
    }

    private function recursoDe(Processo $p): ?array
    {
        $r = RecursoEsic::paraPortal($p);

        return $r === null ? null : ['recursos' => collect($r['recursos'])->map(fn ($x) => [
            'instancia' => (int) $x['instancia'], 'motivo' => $x['motivo'], 'decisao' => $x['decisao_rotulo'], 'fundamentacao' => $x['fundamentacao'],
        ])] + array_intersect_key($r, array_flip(['pode_recorrer', 'instancia', 'recorrer_ate', 'motivo']));
    }
}
