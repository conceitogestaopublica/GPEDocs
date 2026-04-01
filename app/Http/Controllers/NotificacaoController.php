<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Notificacao;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class NotificacaoController extends Controller
{
    public function index()
    {
        $notificacoes = Notificacao::where('usuario_id', Auth::id())
            ->orderByDesc('created_at')
            ->limit(50)
            ->get();

        return response()->json($notificacoes);
    }

    public function marcarLida($id)
    {
        $notificacao = Notificacao::where('id', $id)
            ->where('usuario_id', Auth::id())
            ->firstOrFail();

        $notificacao->update(['lida' => true]);

        return response()->json(['success' => true]);
    }
}
