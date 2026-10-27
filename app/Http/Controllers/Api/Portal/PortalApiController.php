<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Portal;

use App\Http\Controllers\Controller;
use App\Models\Ug;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\Request;

/**
 * Base da API do Portal do Cidadão (Next.js). Sem sessão: o município vem do
 * host (ResolveTenant) e a UG do parâmetro `ug` (= `ugs.portal_slug`), que o
 * portal guarda na configuração de cada município.
 */
abstract class PortalApiController extends Controller
{
    protected function ug(Request $request): Ug
    {
        $slug = (string) $request->input('ug', '');
        $ug = $slug === '' ? null : Ug::query()
            ->where('portal_slug', $slug)
            ->where('ativo', true)
            ->first();

        if (! $ug) {
            throw new HttpResponseException(response()->json(['erro' => 'Unidade gestora não encontrada.'], 404));
        }

        return $ug;
    }
}
