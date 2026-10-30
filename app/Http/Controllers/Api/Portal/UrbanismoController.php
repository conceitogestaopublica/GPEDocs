<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Portal;

use App\Http\Controllers\UrbanismoController as Urbanismo;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** Consulta prévia no portal (TR 4.50.2): os mesmos dados do mapa público do GED. */
class UrbanismoController extends PortalApiController
{
    public function dados(Request $request): JsonResponse
    {
        $this->ug($request);

        return response()->json(Urbanismo::dadosMapa());
    }

    public function lotes(Request $request): JsonResponse
    {
        $this->ug($request);

        return response()->json(Urbanismo::lotesDaArea((string) $request->query('bbox')));
    }

    public function lote(Request $request): JsonResponse
    {
        $this->ug($request);
        $ficha = Urbanismo::fichaLote($request->integer('id') ?: null, $request->query('inscricao'));

        return response()->json($ficha ?? ['erro' => 'Lote não encontrado.'], $ficha ? 200 : 404);
    }

    public function busca(Request $request): JsonResponse
    {
        $this->ug($request);

        return response()->json(Urbanismo::buscar((string) $request->query('q')));
    }
}