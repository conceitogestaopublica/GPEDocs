<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function __invoke(Request $request): Response
    {
        $userId = Auth::id();

        $totalDocumentos = DB::table('ged_documentos')
            ->whereNull('deleted_at')
            ->where('status', '!=', 'excluido')
            ->count();

        $pendentesRevisao = DB::table('ged_documentos')
            ->whereNull('deleted_at')
            ->where('status', 'rascunho')
            ->count();

        $fluxosAtivos = DB::table('ged_fluxo_instancias')
            ->whereIn('status', ['pendente', 'em_andamento'])
            ->count();

        $atividadeRecente = DB::table('ged_audit_logs')
            ->join('users', 'users.id', '=', 'ged_audit_logs.usuario_id')
            ->select(
                'ged_audit_logs.id',
                'ged_audit_logs.documento_id',
                'ged_audit_logs.acao',
                'ged_audit_logs.detalhes',
                'ged_audit_logs.created_at',
                'users.name as usuario_nome'
            )
            ->orderByDesc('ged_audit_logs.created_at')
            ->limit(10)
            ->get();

        $fluxosPendentes = DB::table('ged_fluxo_etapas')
            ->join('ged_fluxo_instancias', 'ged_fluxo_instancias.id', '=', 'ged_fluxo_etapas.instancia_id')
            ->join('ged_documentos', 'ged_documentos.id', '=', 'ged_fluxo_instancias.documento_id')
            ->where('ged_fluxo_etapas.responsavel_id', $userId)
            ->where('ged_fluxo_etapas.status', 'pendente')
            ->select(
                'ged_fluxo_etapas.id',
                'ged_fluxo_etapas.nome as etapa_nome',
                'ged_fluxo_etapas.prazo',
                'ged_fluxo_instancias.id as instancia_id',
                'ged_documentos.id as documento_id',
                'ged_documentos.nome as documento_nome'
            )
            ->get();

        return Inertia::render('GED/Dashboard/Index', [
            'stats' => [
                'total_documentos'  => $totalDocumentos,
                'pendentes_revisao' => $pendentesRevisao,
                'fluxos_ativos'     => $fluxosAtivos,
            ],
            'atividade_recente' => $atividadeRecente,
            'fluxos_pendentes'  => $fluxosPendentes,
        ]);
    }
}
