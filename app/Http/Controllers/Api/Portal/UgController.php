<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Portal;

use App\Models\Ug;
use Illuminate\Http\JsonResponse;

/** Entidades do portal (prefeitura, câmara…), para o filtro do Diário Oficial e dos atos. */
class UgController extends PortalApiController
{
    public function index(): JsonResponse
    {
        return response()->json(['ugs' => Ug::doPortal()->orderBy('id')->get(['portal_slug', 'nome'])
            ->map(fn (Ug $ug) => ['slug' => $ug->portal_slug, 'nome' => $ug->nome])]);
    }
}
