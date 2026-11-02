<?php

declare(strict_types=1);

namespace App\Http\Controllers\Flow;

use App\Http\Controllers\Controller;
use App\Http\Controllers\ProcessoController;
use App\Http\Controllers\TramitacaoController;
use App\Models\Processo\Processo;
use App\Services\AcaoNoProcesso;
use App\Support\RequerimentoServidor;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Throwable;

/**
 * Despacho em lote a partir da caixa de trabalho: receber, encaminhar (o mesmo despacho, para o mesmo
 * setor) ou arquivar vários processos numa operação. Cada processo passa pela MESMA ação da tela do
 * processo (TramitacaoController / ProcessoController::concluir) — mesmas regras de quem pode, de fluxo e
 * de etapa obrigatória, cada um na sua transação e com o seu histórico. Volta a relação dos que não
 * puderam ser despachados e o motivo.
 */
class DespachoLoteController extends Controller
{
    public function __invoke(Request $request)
    {
        $d = $request->validate([
            'acao' => ['required', 'in:receber,encaminhar,arquivar'],
            'processos' => ['required', 'array', 'min:1', 'max:100'],
            'processos.*' => ['integer', 'distinct'],
            'despacho' => ['required_unless:acao,receber', 'nullable', 'string', 'max:20000'],
            'setor_destino' => ['required_if:acao,encaminhar', 'nullable', 'integer', 'exists:ug_organograma,id'],
            'destinatario_id' => ['nullable', 'integer', 'exists:users,id'],
        ], ['despacho.required_unless' => 'Escreva o despacho.', 'setor_destino.required_if' => 'Escolha o setor de destino.']);

        $ok = [];
        $falhas = [];
        $q = Processo::whereIn('id', $d['processos']);
        RequerimentoServidor::restringir($q, Auth::user());
        $processos = $q->get()->keyBy('id');
        foreach ($d['processos'] as $id) {
            $p = $processos->get($id);
            if (! $p) {
                $falhas[] = ['id' => $id, 'numero' => "#{$id}", 'motivo' => 'Processo não encontrado ou sem acesso.'];

                continue;
            }
            $erro = $this->umProcesso($request, $d, $p);
            $erro === null ? $ok[] = $p->numero_protocolo : $falhas[] = ['id' => $p->id, 'numero' => $p->numero_protocolo, 'motivo' => $erro];
        }

        $verbo = ['receber' => 'recebido(s)', 'encaminhar' => 'encaminhado(s)', 'arquivar' => 'arquivado(s)'][$d['acao']];
        session()->forget(['success', 'error', 'errors']);

        return back()->with($ok ? 'success' : 'error', count($ok) . " processo(s) {$verbo}." . ($falhas ? ' ' . count($falhas) . ' não puderam: veja a relação.' : ''))
            ->with('lote', ['acao' => $d['acao'], 'ok' => $ok, 'falhas' => $falhas]);
    }

    /** Executa a ação num processo; devolve null (deu certo) ou o motivo da falha. */
    private function umProcesso(Request $request, array $d, Processo $p): ?string
    {
        $u = Auth::user();
        $acoes = AcaoNoProcesso::avaliar($p, $u);
        $etapa = $acoes['etapa'];
        if ($motivo = AcaoNoProcesso::parado($p)) {
            return "Está {$motivo}.";
        }
        $permitido = match ($d['acao']) {
            'receber' => $acoes['receber'],
            'encaminhar' => $acoes['despachar'],
            'arquivar' => $acoes['concluir'],
        };
        if (! $permitido) {
            return match ($d['acao']) {
                'receber' => $etapa?->status === 'recebido' && AcaoNoProcesso::ativoNaEtapa($etapa, $u) ? 'Já estava recebido.' : 'Você não está na etapa ativa.',
                'arquivar' => $acoes['aguardando'] ? 'Ainda falta a parte de: ' . implode(', ', $acoes['aguardando']) . '.' : 'Só quem está na etapa ativa principal arquiva.',
                default => 'Você não está na etapa ativa.',
            };
        }

        session()->forget(['success', 'error', 'errors']);
        try {
            match ($d['acao']) {
                'receber' => app(TramitacaoController::class)->receber($request, $etapa->id),
                'encaminhar' => app(TramitacaoController::class)->despachar($this->requisicao($request, [
                    'despacho' => $d['despacho'], 'setor_destino' => $d['setor_destino'], 'destinatario_id' => $d['destinatario_id'] ?? null,
                ]), $etapa->id),
                'arquivar' => app(ProcessoController::class)->concluir($this->requisicao($request, [
                    'decisao' => 'arquivado', 'observacao_conclusao' => $d['despacho'],
                ]), $p->id),
            };
        } catch (ValidationException $e) {
            return collect($e->errors())->flatten()->first();
        } catch (HttpExceptionInterface $e) {
            return $e->getMessage() ?: 'Ação não permitida.';
        } catch (Throwable $e) {
            return 'Erro: ' . mb_substr($e->getMessage(), 0, 200);
        }
        // as ações da tela respondem com aviso na sessão: o de erro é o motivo da falha
        $erro = session()->pull('error');
        $erros = session()->pull('errors');
        session()->forget('success');

        return $erro ?: ($erros ? collect($erros->getBag('default')->all())->first() : null);
    }

    /** Requisição só com os campos da ação, com o mesmo usuário, sessão, IP e navegador (vão para o histórico). */
    private function requisicao(Request $original, array $campos): Request
    {
        $server = $original->server->all();
        $server['CONTENT_TYPE'] = 'application/x-www-form-urlencoded';
        unset($server['HTTP_CONTENT_TYPE']);
        $r = Request::create($original->url(), 'POST', array_filter($campos, fn ($v) => $v !== null), $original->cookies->all(), [], $server);
        $r->setUserResolver($original->getUserResolver());
        $r->setRouteResolver($original->getRouteResolver());
        $r->setLaravelSession($original->session());

        return $r;
    }
}
