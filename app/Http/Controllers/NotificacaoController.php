<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Notificacao;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

class NotificacaoController extends Controller
{
    /** Tela de notificações (antes devolvia JSON bruto no "Ver todas" do sino). */
    public function index(Request $request): Response
    {
        $somenteNaoLidas = $request->boolean('nao_lidas');
        $categoria = array_key_exists((string) $request->input('categoria'), \App\Support\Notificacoes::CATEGORIAS) || $request->input('categoria') === 'outros'
            ? $request->input('categoria') : null;

        $notificacoes = Notificacao::where('usuario_id', Auth::id())
            ->when($somenteNaoLidas, fn ($q) => $q->where('lida', false))
            ->when($categoria, fn ($q) => $q->where('categoria', $categoria))
            ->orderByDesc('created_at')
            ->paginate(30)
            ->withQueryString()
            ->through(fn (Notificacao $n) => [
                'id'         => $n->id,
                'titulo'     => $n->titulo,
                'mensagem'   => $n->mensagem,
                'tipo'       => $n->tipo,
                'categoria'  => $n->categoria,
                'urgente'    => (bool) $n->urgente,
                'setor'      => $n->alcance === 'setor',
                'lida'       => (bool) $n->lida,
                'created_at' => $n->created_at,
                'tem_link'   => self::destino($n) !== null,
            ]);

        return Inertia::render('Notificacoes/Index', [
            'notificacoes' => $notificacoes,
            'filtros'      => ['nao_lidas' => $somenteNaoLidas, 'categoria' => $categoria],
            // Categorias com o total e as não lidas de cada uma (TR 4.11.3)
            'categorias'   => $this->categorias(),
        ]);
    }

    private function categorias(): array
    {
        $contas = Notificacao::where('usuario_id', Auth::id())->selectRaw('categoria, count(*) as total, sum(case when lida then 0 else 1 end) as nao_lidas')
            ->groupBy('categoria')->get()->keyBy('categoria');

        return collect(\App\Support\Notificacoes::CATEGORIAS)->map(fn ($c, $k) => [
            'chave' => $k, 'rotulo' => $c[0], 'icone' => $c[1],
            'total' => (int) ($contas[$k]->total ?? 0), 'nao_lidas' => (int) ($contas[$k]->nao_lidas ?? 0),
        ])->filter(fn ($c) => $c['total'] > 0)->values()->all();
    }

    /** Últimas notificações para a lista do sino (TR 4.11.2). */
    public function recentes()
    {
        return response()->json(Notificacao::where('usuario_id', Auth::id())->orderBy('lida')->orderByDesc('created_at')->limit(8)
            ->get(['id', 'titulo', 'mensagem', 'lida', 'categoria', 'urgente', 'referencia_tipo', 'referencia_id', 'created_at'])
            ->map(fn (Notificacao $n) => [
                'id' => $n->id, 'titulo' => $n->titulo, 'mensagem' => $n->mensagem, 'lida' => (bool) $n->lida, 'urgente' => (bool) $n->urgente,
                'icone' => \App\Support\Notificacoes::CATEGORIAS[$n->categoria][1] ?? 'fa-bell', 'em' => $n->created_at?->toIso8601String(),
                'tem_link' => self::destino($n) !== null,
            ]));
    }

    /** Marca como lida e leva ao que a notificação se refere. */
    public function abrir($id)
    {
        $n = Notificacao::where('usuario_id', Auth::id())->findOrFail($id);
        $n->update(['lida' => true, 'lida_em' => now()]);

        return redirect(self::destino($n) ?? '/notificacoes');
    }

    public function marcarLida($id)
    {
        Notificacao::where('id', $id)->where('usuario_id', Auth::id())->firstOrFail()->update(['lida' => true, 'lida_em' => now()]);

        return back();
    }

    public function marcarTodas()
    {
        Notificacao::where('usuario_id', Auth::id())->where('lida', false)->update(['lida' => true, 'lida_em' => now()]);

        return back()->with('success', 'Todas as notificações foram marcadas como lidas.');
    }

    private static function destino(Notificacao $n): ?string
    {
        return $n->destino();
    }
}
