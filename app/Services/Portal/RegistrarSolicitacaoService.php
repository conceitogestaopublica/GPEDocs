<?php

declare(strict_types=1);

namespace App\Services\Portal;

use App\Mail\NotificacaoSolicitacaoCidadao;
use App\Models\Portal\Cidadao;
use App\Models\Portal\Servico;
use App\Models\Portal\Solicitacao;
use App\Models\Portal\SolicitacaoEvento;
use App\Models\Ug;
use App\Services\AbrirProcessoDoPortalService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Throwable;

/**
 * Registra a solicitação do cidadão: a solicitação, o evento "criada" e — se o
 * serviço tem tipo de processo e setor — o processo no GPE Flow. Usado pela
 * tela do portal embutido e pela API do Portal do Cidadão (Next.js), para as
 * duas portas gerarem exatamente o mesmo registro.
 */
class RegistrarSolicitacaoService
{
    public function __construct(private readonly AbrirProcessoDoPortalService $abrirProcesso)
    {
    }

    /**
     * @param  array{descricao:string,telefone_contato?:?string,email_contato?:?string}  $dados
     * @param  ?Cidadao  $procurador  quem protocola em nome de $cidadao (procuração)
     */
    public function registrar(Ug $ug, Servico $servico, ?Cidadao $cidadao, array $dados, bool $anonima = false, ?Cidadao $procurador = null): Solicitacao
    {
        $solicitacao = DB::transaction(function () use ($ug, $servico, $cidadao, $dados, $anonima, $procurador) {
            $sol = Solicitacao::query()->withoutGlobalScope('ug')->create([
                'codigo'           => Solicitacao::gerarCodigo($ug->id),
                'ug_id'            => $ug->id,
                'servico_id'       => $servico->id,
                'cidadao_id'       => $anonima ? null : $cidadao?->id,
                'procurador_cidadao_id' => $anonima ? null : $procurador?->id,
                'anonima'          => $anonima,
                'status'           => 'aberta',
                'descricao'        => $dados['descricao'],
                'telefone_contato' => $anonima ? null : (($dados['telefone_contato'] ?? null) ?: $cidadao?->telefone),
                'email_contato'    => $anonima ? null : (($dados['email_contato'] ?? null) ?: $cidadao?->email),
                'dados_formulario' => $dados['dados_formulario'] ?? null,
            ]);

            SolicitacaoEvento::create([
                'solicitacao_id'   => $sol->id,
                'tipo'             => 'criada',
                'autor_tipo'       => 'cidadao',
                'autor_nome'       => $anonima ? 'Anonimo' : (($procurador ?? $cidadao)?->nome ?? 'Cidadao'),
                'autor_cidadao_id' => $anonima ? null : ($procurador ?? $cidadao)?->id,
                'status_novo'      => 'aberta',
                'mensagem'         => match (true) {
                    $anonima            => 'Solicitacao registrada anonimamente.',
                    $procurador !== null => "Solicitação registrada por {$procurador->nome}, procurador(a) de {$cidadao?->nome}.",
                    default             => 'Solicitacao registrada pelo cidadao.',
                },
            ]);

            // Cria Processo no GPE Flow se servico tem setor + tipo configurados
            $processo = $this->abrirProcesso->abrir($sol, $servico);
            if ($processo) {
                $sol->update(['processo_id' => $processo->id]);
            }

            return $sol;
        });

        // Notifica por e-mail apenas quando identificada. Falha de e-mail não
        // desfaz a solicitação já registrada.
        if (! $anonima && $solicitacao->email_contato && ! \App\Support\DescadastroEmail::descadastrado($solicitacao->email_contato)) {
            try {
                Mail::to($solicitacao->email_contato)
                    ->send(new NotificacaoSolicitacaoCidadao($solicitacao, $servico, $ug, 'criada'));
            } catch (Throwable $e) {
                Log::warning('Aviso de solicitação não enviado', ['solicitacao_id' => $solicitacao->id, 'erro' => $e->getMessage()]);
            }
        }

        return $solicitacao;
    }
}
