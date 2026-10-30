<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Presenters\NotificacaoPresenter;
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

        $notificacoes = Notificacao::where('usuario_id', Auth::id())
            ->when($somenteNaoLidas, fn ($q) => $q->where('lida', false))
            ->orderByDesc('created_at')
            ->paginate(30)
            ->withQueryString()
            ->through(fn (Notificacao $n) => NotificacaoPresenter::resumo($n));

        return Inertia::render('Notificacoes/Index', [
            'notificacoes' => $notificacoes,
            'filtros'      => ['nao_lidas' => $somenteNaoLidas],
        ]);
    }

    /** Marca como lida e leva ao que a notificação se refere. */
    public function abrir($id)
    {
        $n = Notificacao::where('usuario_id', Auth::id())->findOrFail($id);
        $n->update(['lida' => true]);

        return redirect(NotificacaoPresenter::destino($n) ?? '/notificacoes');
    }

    public function marcarLida($id)
    {
        Notificacao::where('id', $id)->where('usuario_id', Auth::id())->firstOrFail()->update(['lida' => true]);

        return back();
    }

    public function marcarTodas()
    {
        Notificacao::where('usuario_id', Auth::id())->where('lida', false)->update(['lida' => true]);

        return back()->with('success', 'Todas as notificações foram marcadas como lidas.');
    }
}
