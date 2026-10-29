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
 *
 * Dois caminhos: automático na chegada (tipo + setor configurados no Serviço) ou
 * pelo atendente, no botão "Abrir processo" (escolhe tipo e setor na hora).
 */
class AbrirProcessoDoPortalService
{
    public function abrir(Solicitacao $solicitacao, Servico $servico): ?Processo
    {
        if (! $servico->tipo_processo_id || ! $servico->setor_responsavel_id) {
            return null;
        }

        $tipo = TipoProcesso::find($servico->tipo_processo_id);
        $unidadeDestino = UgOrganograma::find($servico->setor_responsavel_id);
        if (! $tipo || ! $unidadeDestino) {
            return null;
        }

        return $this->criar($solicitacao, $servico, $tipo, $unidadeDestino, null);
    }

    /** Aberto pelo atendente na tela da solicitação (tipo e setor escolhidos na hora). */
    public function abrirPeloAtendente(Solicitacao $solicitacao, TipoProcesso $tipo, UgOrganograma $unidadeDestino, int $atendenteId): Processo
    {
        return $this->criar($solicitacao, $solicitacao->servico, $tipo, $unidadeDestino, $atendenteId);
    }

    private function criar(Solicitacao $solicitacao, Servico $servico, TipoProcesso $tipo, UgOrganograma $unidadeDestino, ?int $abertoPor): Processo
    {
        return DB::transaction(function () use ($solicitacao, $servico, $tipo, $unidadeDestino, $abertoPor) {

            $protocolo = $this->gerarProtocolo($tipo);

            $primeiraEtapa = TipoEtapa::where('tipo_processo_id', $tipo->id)
                ->orderBy('ordem')
                ->first();

            $dadosFormulario = [
                'Codigo da solicitacao'   => $solicitacao->codigo,
                'Origem'                  => 'Portal do Cidadao',
                'Tipo de solicitacao'     => $solicitacao->anonima ? 'Anonima' : 'Identificada',
                'Descricao do solicitante' => $solicitacao->descricao,
                // Formulário do assunto preenchido no portal (chaves = identificadores do esquema)
                ...($solicitacao->dados_formulario ?? []),
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
                'aberto_por'        => $abertoPor,
                // Ouvidoria: canal, tipo e identificação vêm do portal (TR 4.33.3, 4.33.8, 4.33.27)
                'canal'             => 'portal',
                'manifestacao_tipo' => $tipo->modalidade === 'ouvidoria' ? $solicitacao->manifestacao_tipo : null,
                'identificacao'     => $tipo->modalidade === 'ouvidoria' ? ($solicitacao->identificacao ?: ($solicitacao->anonima ? 'anonima' : 'aberta')) : 'aberta',
            ]);

            $tramitacao = Tramitacao::create([
                'processo_id'        => $processo->id,
                'tipo_etapa_id'      => $primeiraEtapa?->id,
                'ordem'              => 1,
                'setor_origem'       => 'Portal do Cidadao',
                'setor_destino'      => $unidadeDestino->nome,
                'destino_unidade_id' => $unidadeDestino->id,
                'remetente_id'       => $abertoPor,
                'destinatario_id'    => $primeiraEtapa?->responsavel_id,
                'status'             => 'pendente',
                'despachado_em'      => now(),
                'sla_horas'          => $primeiraEtapa?->sla_horas ?? $tipo->sla_padrao_horas,
                'prazo'              => \App\Support\Expediente::prazo($tipo, $primeiraEtapa?->sla_horas ?? $tipo->sla_padrao_horas ?? 72),
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
