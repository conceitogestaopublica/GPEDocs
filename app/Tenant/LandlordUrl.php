<?php

declare(strict_types=1);

namespace App\Tenant;

use Illuminate\Http\Request;

/**
 * URL absoluta do painel landlord (gpe2), com esquema garantido.
 *
 * Location sem esquema é interpretado como caminho relativo pelo browser (vira
 * /landlord/admin.exemplo.com.br), então herda o esquema da request quando a config vier só
 * com o host. '' quando não configurada. Função pura de config + request — antes vivia num
 * estático público do ResolveTenant, preenchido só se o middleware já tivesse rodado.
 */
final class LandlordUrl
{
    public static function de(Request $request): string
    {
        $url = (string) config('multitenancy.landlord_url');

        if ($url === '') {
            return '';
        }

        if (! str_contains($url, '://')) {
            $url = $request->getScheme().'://'.$url;
        }

        return rtrim($url, '/');
    }
}
