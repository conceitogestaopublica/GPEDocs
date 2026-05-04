<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Portal\Servico;
use App\Models\Portal\Solicitacao;
use App\Models\Processo\Processo;
use App\Models\Processo\Tramitacao;
use App\Models\Processo\TipoEtapa;
use App\Models\Processo\TipoProcesso;
use App\Models\UgOrganograma;
use Illuminate\Support\Facades\DB;

/**
 * Cria um Processo no GPE Flow a partir de uma Solicitacao do Portal Cidadao.
 *
 * O Processo entra na inbox-setor da unidade responsavel (configurada no Servico).
 * Atendentes daquele setor conseguem ver, despachar e concluir pelo GPE Flow normalmente.
 */
class AbrirProcessoDoPortalService
{
    public function abrir(Solicitacao $solicitacao, Servico $servico): ?Processo
    {
        if (! $servico->tipo_processo_id || ! $servico->setor_responsavel_id) {
            return null;
        }

        return DB::transaction(function () use ($solicitacao, $servico) {
            $tipo = TipoProcesso::find($servico->tipo_processo_id);
            $unidadeDestino = UgOrganograma::find($servico->setor_responsavel_id);
            if (! $tipo || ! $unidadeDestino) {
                return null;
            }

            $protocolo = $this->gerarProtocolo($tipo);

            $primeiraEtapa = TipoEtapa::where('tipo_processo_id', $tipo->id)
                ->orderBy('ordem')
                ->first();

            $dadosFormulario = [
                'Codigo da solicitacao'   => $solicitacao->codigo,
                'Origem'                  => 'Portal do Cidadao',
                'Tipo de solicitacao'     => $solicitacao->anonima ? 'Anonima' : 'Identificada',
                'Descricao do solicitante' => $solicitacao->descricao,
            ];

            $processo = Processo::query()->withoutGlobalScope('ug')->create([
                'ug_id'             => $solicitacao->ug_id,
                'numero_protocolo'  => $protocolo,
                'tipo_processo_id'  => $tipo->id,
                'assunto'           => "[Portal] {$servico->titulo}",
                'descricao'         => $solicitacao->descricao,
                'dados_formulario'  => $dadosFormulario,
                'requerente_nome'   => $solicitacao->anonima ? 'Anonimo' : ($solicitacao->cidadao?->nome ?? 'Cidadao'),
                'requerente_cpf'    => $solicitacao->anonima ? null : $solicitacao->cidadao?->cpf,
                'requerente_email'  => $solicitacao->anonima ? null : ($solicitacao->email_contato ?? $solicitacao->cidadao?->email),
                'requerente_telefone'=> $solicitacao->anonima ? null : ($solicitacao->telefone_contato ?? $solicitacao->cidadao?->telefone),
                'setor_origem'      => 'Portal do Cidadao',
                'status'            => 'aberto',
                'prioridade'        => 'normal',
                'aberto_por'        => null,
            ]);

            $tramitacao = Tramitacao::create([
                'processo_id'        => $processo->id,
                'tipo_etapa_id'      => $primeiraEtapa?->id,
                'ordem'              => 1,
                'setor_origem'       => 'Portal do Cidadao',
                'setor_destino'      => $unidadeDestino->nome,
                'destino_unidade_id' => $unidadeDestino->id,
                'remetente_id'       => null,
                'destinatario_id'    => $primeiraEtapa?->responsavel_id,
                'status'             => 'pendente',
                'despachado_em'      => now(),
                'sla_horas'          => $primeiraEtapa?->sla_horas ?? $tipo->sla_padrao_horas,
                'prazo'              => now()->addHours($primeiraEtapa?->sla_horas ?? $tipo->sla_padrao_horas ?? 72),
            ]);

            $processo->update(['etapa_atual_id' => $tramitacao->id]);

            return $processo;
        });
    }

    private function gerarProtocolo(TipoProcesso $tipo): string
    {
        $year = date('Y');
        $lastNum = Processo::query()->withoutGlobalScope('ug')
            ->where('tipo_processo_id', $tipo->id)
            ->whereYear('created_at', $year)
            ->max(DB::raw("CAST(SPLIT_PART(numero_protocolo, '/', 2) AS INTEGER)"));
        $next = ($lastNum ?? 0) + 1;
        return $tipo->sigla.'-'.$year.'/'.str_pad((string) $next, 6, '0', STR_PAD_LEFT);
    }
}
