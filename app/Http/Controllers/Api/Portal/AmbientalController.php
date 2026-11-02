<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Portal;

use App\Models\Portal\Cidadao;
use App\Models\Portal\Servico;
use App\Models\Processo\Processo;
use App\Services\Portal\AnexosSolicitacaoService;
use App\Services\Portal\CidadaoPortalException;
use App\Services\Portal\RegistrarSolicitacaoService;
use App\Support\LicenciamentoAmbiental as Lic;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Licenciamento ambiental pelo portal: atividades e modalidades, enquadramento (classe e documentos
 * exigidos), requerimento de licença — e de renovação — que vira solicitação e processo no GED, com o
 * acompanhamento pela própria solicitação.
 */
class AmbientalController extends PortalApiController
{
    /** Atividades licenciáveis e modalidades. */
    public function atividades(Request $request): JsonResponse
    {
        $this->ug($request);

        return response()->json([
            'modalidades' => Lic::MODALIDADES,
            'atividades' => DB::table('amb_atividades')->where('ativo', true)->orderBy('codigo')->get(['id', 'codigo', 'descricao', 'unidade', 'potencial']),
            'disponivel' => (bool) DB::table('amb_config')->value('servico_id'),
        ]);
    }

    /** Enquadramento: porte, potencial, classe e documentos a enviar (índice = posição na lista). */
    public function enquadramento(Request $request): JsonResponse
    {
        $this->ug($request);
        $d = $request->validate(['atividade_id' => ['required', 'integer'], 'porte_valor' => ['required', 'numeric', 'min:0'], 'modalidade' => ['required', 'string']]);
        try {
            return response()->json(Lic::enquadrar((int) $d['atividade_id'], (float) $d['porte_valor'], $d['modalidade']));
        } catch (RuntimeException $e) {
            return response()->json(['erro' => $e->getMessage()], 422);
        }
    }

    /**
     * Requerimento de licença (ou de renovação, com `licenca_anterior`): enquadra, confere os documentos da
     * classe, registra a solicitação no serviço do licenciamento (abre o processo) e liga a licença a ele.
     */
    public function requerer(Request $request, RegistrarSolicitacaoService $registrar, AnexosSolicitacaoService $anexos): JsonResponse
    {
        $ug = $this->ug($request);
        $cidadao = Cidadao::where('id', (int) $request->input('cidadao_id'))->where('ativo', true)->first();
        abort_unless($cidadao, 404, 'Cidadão não encontrado.');
        $d = $request->validate([
            'modalidade' => ['required', 'string'], 'atividade_id' => ['required', 'integer'], 'porte_valor' => ['required', 'numeric', 'min:0'],
            'empreendedor_nome' => ['nullable', 'string', 'max:200'], 'empreendedor_documento' => ['nullable', 'string', 'max:20'],
            'empreendimento_nome' => ['required', 'string', 'max:200'], 'empreendimento_endereco' => ['required', 'string', 'max:300'],
            'descricao' => ['required', 'string', 'max:5000'], 'licenca_anterior' => ['nullable', 'string', 'max:20'],
            'documentos' => ['nullable', 'array', 'max:30'], 'documentos.*.indice' => ['required', 'integer', 'min:0'],
            'documentos.*.nome' => ['nullable', 'string', 'max:200'], 'documentos.*.conteudo' => ['required', 'string'],
        ]);
        $servico = Servico::query()->withoutGlobalScope('ug')->where('id', DB::table('amb_config')->value('servico_id'))->where('ug_id', $ug->id)->first();
        if (! $servico || ! $servico->tipo_processo_id || ! $servico->setor_responsavel_id) {
            return response()->json(['erro' => 'O licenciamento ambiental pelo portal ainda não está disponível neste município.'], 422);
        }
        $anterior = null;
        if (! empty($d['licenca_anterior'])) {
            $anterior = DB::table('amb_licencas')->where('numero', $d['licenca_anterior'])->where('ug_id', $ug->id)->first();
            $doc = preg_replace('/\D/', '', (string) $cidadao->cpf);
            if (! $anterior || ! in_array($anterior->status, ['emitida', 'vencida'], true)
                || ($anterior->empreendedor_documento && preg_replace('/\D/', '', $anterior->empreendedor_documento) !== $doc && preg_replace('/\D/', '', (string) ($d['empreendedor_documento'] ?? '')) !== preg_replace('/\D/', '', $anterior->empreendedor_documento))) {
                return response()->json(['erro' => 'Licença anterior não encontrada para este empreendedor.'], 422);
            }
            $d['modalidade'] = 'RENOVACAO';
        }
        try {
            $enq = Lic::enquadrar((int) $d['atividade_id'], (float) $d['porte_valor'], $d['modalidade']);
            $prontos = $anexos->prepararExigidos($enq['documentos'], $d['documentos'] ?? []);
            $anexos->conferirTotal($prontos);
        } catch (RuntimeException|CidadaoPortalException $e) {
            return response()->json(['erro' => $e->getMessage()], 422);
        }

        $descricao = "{$enq['modalidade_rotulo']} — {$enq['atividade']['codigo']} {$enq['atividade']['descricao']} (classe {$enq['classe']}). "
            . "Empreendimento: {$d['empreendimento_nome']}, {$d['empreendimento_endereco']}." . ($anterior ? " Renovação da licença {$anterior->numero}." : '') . "\n\n{$d['descricao']}";
        $solicitacao = $registrar->registrar($ug, $servico, $cidadao, ['descricao' => $descricao, 'email_contato' => $cidadao->email]);
        if (! $solicitacao->processo_id) {
            return response()->json(['erro' => 'Não foi possível abrir o processo do licenciamento.'], 422);
        }
        $processo = Processo::query()->withoutGlobalScope('ug')->find($solicitacao->processo_id);
        Lic::registrar($processo, $enq, (float) $d['porte_valor'], [
            'empreendedor_nome' => ($d['empreendedor_nome'] ?? null) ?: $cidadao->nome, 'empreendedor_documento' => ($d['empreendedor_documento'] ?? null) ?: $cidadao->cpf,
            'empreendedor_email' => $cidadao->email, 'empreendimento_nome' => $d['empreendimento_nome'], 'empreendimento_endereco' => $d['empreendimento_endereco'],
        ], $solicitacao->id, $anterior?->id);
        if ($prontos) {
            $anexos->gravar($solicitacao, $cidadao, $prontos);
        }

        return response()->json(['solicitacao' => ['codigo' => $solicitacao->codigo, 'processo' => $processo->numero_protocolo, 'classe' => $enq['classe'],
            'comprovante_url' => url("/api/portal/solicitacoes/{$solicitacao->codigo}/comprovante")]], 201);
    }

    /** Licenças do cidadão (pelo CPF/CNPJ do empreendedor ou pela solicitação dele), para acompanhar e renovar. */
    public function minhas(Request $request): JsonResponse
    {
        $ug = $this->ug($request);
        $cidadao = Cidadao::where('id', (int) $request->input('cidadao_id'))->where('ativo', true)->first();
        abort_unless($cidadao, 404, 'Cidadão não encontrado.');
        $doc = preg_replace('/\D/', '', (string) $cidadao->cpf);
        $dias = (int) (DB::table('amb_config')->value('dias_aviso_vencimento') ?? 120);

        return response()->json(DB::table('amb_licencas as l')->where('l.ug_id', $ug->id)
            ->where(fn ($w) => $w->whereRaw("regexp_replace(coalesce(l.empreendedor_documento, ''), '[^0-9]', '', 'g') = ?", [$doc])
                ->orWhereIn('l.solicitacao_id', DB::table('portal_solicitacoes')->where('cidadao_id', $cidadao->id)->select('id')))
            ->orderByDesc('l.id')->limit(100)->get()
            ->map(fn ($l) => ['numero' => $l->numero, 'modalidade' => Lic::MODALIDADES[$l->modalidade], 'situacao' => Lic::STATUS[$l->status] ?? $l->status,
                'valida_ate' => $l->valida_ate, 'empreendimento' => $l->empreendimento_nome, 'pode_renovar' => in_array($l->status, ['emitida', 'vencida'], true)
                    && $l->valida_ate && $l->valida_ate <= now('America/Sao_Paulo')->addDays($dias + 60)->toDateString(),
                'verificacao_url' => $l->codigo_verificacao ? url("/ambiental/licencas/{$l->codigo_verificacao}") : null]));
    }
}
