<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\ChatMensagem;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class ChatController extends Controller
{
    /**
     * Lista contatos disponiveis (usuarios da mesma UG) + ultima mensagem trocada.
     */
    public function contatos(Request $request)
    {
        $ugId = (int) ($request->session()->get('ug_id') ?? 0);
        $userId = (int) Auth::id();

        // Usuarios da mesma UG (exceto o proprio)
        $usuarios = User::query()
            ->select('users.id', 'users.name', 'users.email')
            ->where('users.id', '!=', $userId)
            ->when($ugId, function ($q) use ($ugId) {
                $q->whereExists(function ($sub) use ($ugId) {
                    $sub->select(DB::raw(1))
                        ->from('user_ugs')
                        ->whereColumn('user_ugs.user_id', 'users.id')
                        ->where('user_ugs.ug_id', $ugId);
                });
            })
            ->orderBy('users.name')
            ->get();

        // Ultima mensagem e nao lidas por contato
        $ultimas = ChatMensagem::query()
            ->select('remetente_id', 'destinatario_id', 'conteudo', 'created_at')
            ->where(function ($q) use ($userId) {
                $q->where('remetente_id', $userId)->orWhere('destinatario_id', $userId);
            })
            ->orderByDesc('created_at')
            ->get();

        $byContact = [];
        foreach ($ultimas as $m) {
            $outroId = $m->remetente_id === $userId ? $m->destinatario_id : $m->remetente_id;
            if (!isset($byContact[$outroId])) {
                $byContact[$outroId] = ['ultima' => $m->conteudo, 'em' => $m->created_at];
            }
        }

        $naoLidasPorContato = ChatMensagem::query()
            ->select('remetente_id', DB::raw('count(*) as total'))
            ->where('destinatario_id', $userId)
            ->whereNull('lida_em')
            ->groupBy('remetente_id')
            ->pluck('total', 'remetente_id');

        $contatos = $usuarios->map(function ($u) use ($byContact, $naoLidasPorContato) {
            $em = $byContact[$u->id]['em'] ?? null;
            return [
                'id'        => $u->id,
                'nome'      => $u->name,
                'email'     => $u->email,
                'ultima'    => $byContact[$u->id]['ultima'] ?? null,
                'em'        => $em ? (string) $em : null,
                'nao_lidas' => (int) ($naoLidasPorContato[$u->id] ?? 0),
            ];
        })
        ->sortByDesc(fn ($c) => $c['em'] ? strtotime($c['em']) : 0)
        ->values();

        $totalNaoLidas = $naoLidasPorContato->sum();

        return response()->json([
            'contatos'        => $contatos,
            'total_nao_lidas' => $totalNaoLidas,
        ]);
    }

    /**
     * Apenas o numero total de nao lidas (para polling rapido).
     */
    public function naoLidas(Request $request)
    {
        $userId = (int) Auth::id();
        $total = ChatMensagem::where('destinatario_id', $userId)
            ->whereNull('lida_em')
            ->count();

        return response()->json(['total' => $total]);
    }

    /**
     * Mensagens trocadas com um contato.
     */
    public function mensagens(Request $request, int $contatoId)
    {
        $userId = (int) Auth::id();
        $apos   = $request->input('apos'); // id da ultima msg ja recebida (polling)

        $query = ChatMensagem::query()
            ->where(function ($q) use ($userId, $contatoId) {
                $q->where(function ($w) use ($userId, $contatoId) {
                    $w->where('remetente_id', $userId)->where('destinatario_id', $contatoId);
                })->orWhere(function ($w) use ($userId, $contatoId) {
                    $w->where('remetente_id', $contatoId)->where('destinatario_id', $userId);
                });
            });

        if ($apos) {
            $query->where('id', '>', (int) $apos);
        }

        $msgs = $query->orderBy('id')->limit(500)->get([
            'id', 'remetente_id', 'destinatario_id', 'conteudo', 'created_at', 'lida_em',
        ]);

        // Marca as recebidas como lidas
        ChatMensagem::where('destinatario_id', $userId)
            ->where('remetente_id', $contatoId)
            ->whereNull('lida_em')
            ->update(['lida_em' => now()]);

        return response()->json(['mensagens' => $msgs]);
    }

    /**
     * Envia mensagem para um contato.
     */
    public function enviar(Request $request, int $contatoId)
    {
        $request->validate([
            'conteudo' => ['required', 'string', 'max:2000'],
        ]);

        $userId = (int) Auth::id();
        $ugId   = (int) ($request->session()->get('ug_id') ?? 0) ?: null;

        $msg = ChatMensagem::create([
            'ug_id'           => $ugId,
            'remetente_id'    => $userId,
            'destinatario_id' => $contatoId,
            'conteudo'        => $request->input('conteudo'),
        ]);

        return response()->json([
            'mensagem' => $msg->only(['id', 'remetente_id', 'destinatario_id', 'conteudo', 'created_at']),
        ]);
    }
}
