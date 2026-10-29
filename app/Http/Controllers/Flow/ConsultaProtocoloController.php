<?php

declare(strict_types=1);

namespace App\Http\Controllers\Flow;

use App\Http\Controllers\Controller;
use App\Models\Portal\Solicitacao;
use App\Models\Processo\Processo;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Atendimento › Consultar Protocolo: para responder quem liga ou vai ao balcão
 * perguntar "como está o meu pedido?". Busca, na UG ativa, pelo número do
 * protocolo, pelo CPF/CNPJ ou pelo nome do requerente — nos processos e nas
 * solicitações do Portal do Cidadão (que ainda podem não ter virado processo).
 */
class ConsultaProtocoloController extends Controller
{
    private const LIMITE = 50;

    public function __invoke(Request $request): Response
    {
        $q = trim((string) $request->input('q', ''));
        $digitos = preg_replace('/\D/', '', $q);
        // CPF/CNPJ: só quando o texto é (quase) só número e tem tamanho de documento.
        $porDocumento = strlen($digitos) >= 11 && strlen($digitos) >= strlen(preg_replace('/[\s.\-\/]/', '', $q));

        $processos = collect();
        $solicitacoes = collect();

        if (mb_strlen($q) >= 3) {
            $sigilo = ! (auth()->user()->super_admin || auth()->user()->can('ouvidoria.gerir'));
            // Processo já é escopado pela UG da sessão (BelongsToUg).
            $processos = Processo::query()
                ->with(['tipoProcesso:id,nome,sigla', 'etapaAtual:id,setor_destino'])
                ->where(function ($w) use ($q, $digitos, $porDocumento, $sigilo) {
                    $w->where('numero_protocolo', 'ilike', "%{$q}%")
                        // Manifestante sigiloso: pelo nome/CPF só a ouvidoria acha (TR 4.33.27)
                        ->orWhere(fn ($r) => $r->where('requerente_nome', 'ilike', "%{$q}%")->when($sigilo, fn ($s) => $s->where('identificacao', '<>', 'sigilosa')));
                    if ($porDocumento) {
                        $w->orWhere(fn ($r) => $r->whereRaw("regexp_replace(coalesce(requerente_cpf, ''), '[^0-9]', '', 'g') = ?", [$digitos])
                            ->when($sigilo, fn ($s) => $s->where('identificacao', '<>', 'sigilosa')));
                    }
                })
                ->orderByDesc('created_at')
                ->limit(self::LIMITE)
                ->get()
                ->map(fn (Processo $p) => [
                    'id'         => $p->id,
                    'protocolo'  => $p->numero_protocolo,
                    'assunto'    => $p->assunto,
                    'tipo'       => $p->tipoProcesso?->nome,
                    'requerente' => $sigilo && $p->identificacao === 'sigilosa' ? 'Manifestante sigiloso' : $p->requerente_nome,
                    'documento'  => $sigilo && $p->identificacao === 'sigilosa' ? null : $p->requerente_cpf,
                    'status'     => $p->status,
                    'setor'      => $p->etapaAtual?->setor_destino,
                    'aberto_em'  => $p->created_at?->toIso8601String(),
                ]);

            // Solicitações do portal: só para quem atende o portal.
            if (Auth::user()?->can('portal.atendimento')) {
                $solicitacoes = Solicitacao::query()
                    ->with(['servico:id,titulo', 'cidadao:id,nome,cpf'])
                    ->where(function ($w) use ($q, $digitos, $porDocumento) {
                        $w->where('codigo', 'ilike', "%{$q}%")
                            ->orWhereHas('cidadao', function ($c) use ($q, $digitos, $porDocumento) {
                                $c->where('nome', 'ilike', "%{$q}%");
                                if ($porDocumento) {
                                    $c->orWhereRaw("regexp_replace(coalesce(cpf, ''), '[^0-9]', '', 'g') = ?", [$digitos]);
                                }
                            });
                    })
                    ->orderByDesc('id')
                    ->limit(self::LIMITE)
                    ->get()
                    ->map(fn (Solicitacao $s) => [
                        'id'          => $s->id,
                        'codigo'      => $s->codigo,
                        'servico'     => $s->servico?->titulo,
                        'requerente'  => $s->anonima ? 'Anônima' : $s->cidadao?->nome,
                        'documento'   => $s->anonima ? null : $s->cidadao?->cpf,
                        'status'      => $s->status,
                        'processo_id' => $s->processo_id,
                        'aberta_em'   => $s->created_at?->toIso8601String(),
                    ]);
            }
        }

        return Inertia::render('Flow/ConsultaProtocolo', [
            'q'                 => $q,
            'processos'         => $processos->values(),
            'solicitacoes'      => $solicitacoes->values(),
            'statusSolicitacao' => Solicitacao::STATUS,
            'limite'            => self::LIMITE,
        ]);
    }
}
