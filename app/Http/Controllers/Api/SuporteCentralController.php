<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\CentralDemandas\CentralDemandasClient;
use App\Support\Suporte;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Central de Demandas → GED: eventos do atendimento (mensagem da equipe Conceito, situação, resolvida,
 * resposta automática). O tenant vem do endereço (a URL de retorno da integração é a do GED do
 * município); a assinatura HMAC do corpo cru é conferida com o segredo deste tenant ANTES de ler o JSON.
 * A Central reenvia em falha: o mesmo id_evento é aplicado uma vez só.
 */
class SuporteCentralController extends Controller
{
    private const EVENTOS = ['mensagem', 'situacao', 'resolvida', 'resposta_automatica'];

    public function eventos(Request $request)
    {
        $segredo = CentralDemandasClient::segredo();
        $corpo = $request->getContent();
        $assinatura = (string) $request->header('X-GPE-Assinatura');
        if (! $segredo || ! hash_equals('sha256=' . hash_hmac('sha256', $corpo, $segredo), $assinatura)) {
            return response()->json(['erro' => 'Assinatura inválida.'], 401);
        }
        $e = json_decode($corpo, true);
        if (! is_array($e) || ! in_array($e['evento'] ?? null, self::EVENTOS, true) || ! is_numeric($e['id_evento'] ?? null)) {
            return response()->json(['erro' => 'Evento inválido.'], 422);
        }
        $a = DB::table('sup_atendimentos')->where('id', (int) ($e['atendimento_id'] ?? 0))->where('via_central', true)->first();
        if (! $a) {
            return response()->json(['erro' => 'Atendimento não encontrado.'], 404);
        }

        try {
            DB::transaction(function () use ($a, $e) {
                DB::table('sup_central_eventos')->insert(['id_evento' => (int) $e['id_evento'], 'evento' => $e['evento'], 'atendimento_id' => $a->id, 'created_at' => now()]);
                if (! $a->central_numero && ! empty($e['demanda'])) {
                    DB::table('sup_atendimentos')->where('id', $a->id)->update(['central_numero' => mb_substr((string) $e['demanda'], 0, 30)]);
                }
                Suporte::eventoCentral($a, $e);
            });
        } catch (UniqueConstraintViolationException) {
            return response()->json(['ok' => true, 'repetido' => true]);
        }

        return response()->json(['ok' => true]);
    }
}
