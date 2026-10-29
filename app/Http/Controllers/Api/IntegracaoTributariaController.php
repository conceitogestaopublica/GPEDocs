<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Integracoes\Tributario\Vinculos;
use App\Models\Processo\Processo;
use App\Models\Processo\VinculoTributario;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

/**
 * Eventos do sistema tributário para o GED (qualquer fornecedor), autenticados pelo token de
 * "sistema integrado" (Authorization: Bearer). Formato:
 *
 * POST /api/integracoes/tributario/eventos
 * {
 *   "evento": "guia.paga | guia.cancelada | guia.vencida | documento.emitido | documento.cancelado | fiscalizacao.atualizada",
 *   "tipo": "guia | alvara_construcao | habitese | certidao | fiscalizacao | licenca",
 *   "id_externo": "id no tributário",          // acha o vínculo criado pelo GED
 *   "processo": "LIC-2026/000123",             // opcional: cria o vínculo se ainda não existe
 *   "numero": "…", "situacao": "…", "valor": 0, "vencimento": "AAAA-MM-DD",
 *   "pdf_base64": "…" ou "pdf_url": "https://…", // opcional: vira anexo do processo
 *   "dados": { … }                              // opcional, guardado no vínculo
 * }
 */
class IntegracaoTributariaController extends Controller
{
    public const EVENTOS = ['guia.paga' => 'paga', 'guia.cancelada' => 'cancelada', 'guia.vencida' => 'vencida', 'documento.emitido' => 'emitido',
        'documento.cancelado' => 'cancelado', 'fiscalizacao.atualizada' => null];

    public function eventos(Request $request): JsonResponse
    {
        $sistema = $request->attributes->get('sistema_integrado');
        $d = $request->validate([
            'evento' => ['required', 'string', 'in:' . implode(',', array_keys(self::EVENTOS))],
            'tipo' => ['required', 'string', 'in:' . implode(',', array_keys(VinculoTributario::TIPOS))],
            'id_externo' => ['required', 'string', 'max:100'], 'processo' => ['nullable', 'string', 'max:60'],
            'numero' => ['nullable', 'string', 'max:100'], 'situacao' => ['nullable', 'string', 'max:30'], 'valor' => ['nullable', 'numeric'],
            'vencimento' => ['nullable', 'date'], 'pdf_base64' => ['nullable', 'string'], 'pdf_url' => ['nullable', 'url', 'max:1000'], 'dados' => ['nullable', 'array'],
        ]);
        $log = fn (string $res, ?string $msg, ?int $vid = null) => DB::table('ged_eventos_tributarios')->insert([
            'evento' => $d['evento'], 'sistema' => $sistema?->codigo, 'payload' => json_encode(array_diff_key($d, ['pdf_base64' => 1]), JSON_UNESCAPED_UNICODE),
            'resultado' => $res, 'mensagem' => $msg, 'vinculo_id' => $vid, 'created_at' => now()]);

        $v = VinculoTributario::where('tipo', $d['tipo'])->where('id_externo', $d['id_externo'])->latest('id')->first();
        if (! $v && ! empty($d['processo'])) {
            $p = Processo::withoutGlobalScopes()->where('numero_protocolo', $d['processo'])->first();
            $v = $p ? VinculoTributario::create(['processo_id' => $p->id, 'tipo' => $d['tipo'], 'id_externo' => $d['id_externo'], 'numero' => $d['numero'] ?? null]) : null;
        }
        if (! $v) {
            $log('ignorado', 'Vínculo não encontrado (id_externo sem processo).');

            return response()->json(['resultado' => 'ignorado', 'motivo' => 'Nenhum processo ligado a este id_externo; envie "processo".'], 404);
        }

        $situacao = $d['situacao'] ?? self::EVENTOS[$d['evento']] ?? $v->situacao;
        $antes = $v->situacao;
        $v->update(array_filter(['situacao' => $situacao, 'numero' => $d['numero'] ?? null, 'valor' => $d['valor'] ?? null, 'vencimento' => $d['vencimento'] ?? null,
            'dados' => array_merge($v->dados ?? [], $d['dados'] ?? [])], fn ($x) => $x !== null));
        $pdf = ! empty($d['pdf_base64']) ? base64_decode($d['pdf_base64'], true) : null;
        if (! $pdf && ! empty($d['pdf_url'])) {
            $r = Http::timeout(20)->get($d['pdf_url']);
            $pdf = $r->successful() ? $r->body() : null;
        }
        if ($pdf && str_starts_with($pdf, '%PDF') && ! $v->anexo_id) {
            Vinculos::anexar($v, $pdf, (VinculoTributario::TIPOS[$v->tipo] ?? $v->tipo) . ' ' . ($v->numero ?: $v->id_externo), null);
        }
        DB::table('proc_historico')->insert(['processo_id' => $v->processo_id, 'usuario_id' => null, 'acao' => 'tributario_evento',
            'detalhes' => json_encode(['evento' => $d['evento'], 'tipo' => VinculoTributario::TIPOS[$v->tipo] ?? $v->tipo, 'numero' => $v->numero,
                'de' => $antes, 'para' => $v->situacao, 'sistema' => $sistema?->nome], JSON_UNESCAPED_UNICODE), 'ip' => $request->ip(), 'created_at' => now()]);
        $log('aplicado', null, $v->id);

        return response()->json(['resultado' => 'aplicado', 'processo' => $v->processo?->numero_protocolo, 'situacao' => $v->situacao, 'anexo' => (bool) $v->fresh()->anexo_id]);
    }
}