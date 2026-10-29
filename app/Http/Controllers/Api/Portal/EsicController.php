<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Portal;

use App\Support\PainelOuvidoria;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Estatísticas públicas do e-SIC (TR 4.34; LAI art. 30, III): pedidos recebidos, atendidos,
 * no prazo, por mês, assunto e setor — sem nenhum dado de quem pediu.
 */
class EsicController extends PortalApiController
{
    public function estatisticas(Request $request): JsonResponse
    {
        $ug = $this->ug($request);
        $ano = $request->integer('ano') ?: (int) now()->year;
        $base = PainelOuvidoria::consulta('esic', ['de' => "{$ano}-01-01", 'ate' => "{$ano}-12-31"], $ug->id);
        $i = PainelOuvidoria::indicadores($base);

        return response()->json(['ano' => $ano] + array_intersect_key($i, array_flip([
            'total', 'abertos', 'vencidos', 'respondidos', 'prorrogados', 'pct_no_prazo', 'dias_medio', 'por_mes', 'por_assunto', 'por_setor', 'por_canal',
        ])));
    }
}
