<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Processo\Processo;
use App\Models\Processo\Tramitacao;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class ProcessoDashboardController extends Controller
{
    public function __invoke(): Response
    {
        $totalAbertos = Processo::where('status', 'aberto')->count();
        $totalEmTramitacao = Processo::where('status', 'em_tramitacao')->count();

        $concluidosMes = Processo::where('status', 'concluido')
            ->whereMonth('concluido_em', now()->month)
            ->whereYear('concluido_em', now()->year)
            ->count();

        $atrasados = Tramitacao::whereIn('status', ['pendente', 'recebido', 'em_analise'])
            ->where('prazo', '<', now())
            ->count();

        $inboxCount = Tramitacao::where('destinatario_id', Auth::id())
            ->whereIn('status', ['pendente', 'recebido', 'em_analise'])
            ->count();

        $processosRecentes = Processo::with(['tipoProcesso', 'abertoPor'])
            ->orderByDesc('created_at')
            ->limit(10)
            ->get();

        return Inertia::render('GED/Processos/Dashboard', [
            'stats' => [
                'total_abertos'      => $totalAbertos,
                'em_tramitacao'      => $totalEmTramitacao,
                'concluidos_mes'     => $concluidosMes,
                'atrasados'          => $atrasados,
                'inbox_count'        => $inboxCount,
            ],
            'processos_recentes' => $processosRecentes,
        ]);
    }
}
