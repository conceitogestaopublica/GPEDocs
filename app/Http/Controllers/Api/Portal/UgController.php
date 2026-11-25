<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Portal;

use App\Models\Ug;
use Illuminate\Http\JsonResponse;

/**
 * Entidades do portal (prefeitura, câmara…): o filtro do Diário Oficial e dos atos e, pela gestora do
 * gpe2 de que cada uma foi importada, a unidade escolhida no app.
 */
class UgController extends PortalApiController
{
    public function index(): JsonResponse
    {
        return response()->json(['ugs' => Ug::doPortal()->orderBy('id')->get(['portal_slug', 'nome', 'legado_orgao_id'])
            ->map(fn (Ug $ug) => ['slug' => $ug->portal_slug, 'nome' => $ug->nome, 'gestora_id' => $ug->legado_orgao_id])]);
    }
}
