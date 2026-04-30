<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    protected $rootView = 'app';

    public function share(Request $request): array
    {
        $user = $request->user();

        // Count unread notifications
        $notificacoesPendentes = 0;
        if ($user) {
            $notificacoesPendentes = \App\Models\Notificacao::where('usuario_id', $user->id)
                ->where('lida', false)->count();
        }

        // UG ativa + lista de UGs do usuario (para o seletor da topbar)
        $tenant = $this->compartilharTenant($user, $request);

        return array_merge(parent::share($request), [
            'auth' => [
                'user' => $user ? [
                    'id'          => $user->id,
                    'name'        => $user->name,
                    'email'       => $user->email,
                    'super_admin' => (bool) $user->super_admin,
                ] : null,
            ],
            'flash' => [
                'success' => $request->session()->get('success'),
                'error'   => $request->session()->get('error'),
                'warning' => $request->session()->get('warning'),
                'info'    => $request->session()->get('info'),
            ],
            'notificacoes_pendentes' => $notificacoesPendentes,
            'tenant' => $tenant,
        ]);
    }

    private function compartilharTenant($user, Request $request): array
    {
        if (! $user) {
            return ['atual' => null, 'multiplas' => false];
        }

        $ugAtualId = $request->session()->get('ug_id');
        $ugAtual = null;
        if ($ugAtualId) {
            $ug = \App\Models\Ug::find($ugAtualId);
            if ($ug) {
                $ugAtual = [
                    'id'     => $ug->id,
                    'codigo' => $ug->codigo,
                    'nome'   => $ug->nome,
                    'cnpj'   => $ug->cnpj,
                    'cidade' => $ug->cidade,
                    'uf'     => $ug->uf,
                ];
            }
        }

        // Quantas UGs o user tem acesso? Se >1, mostra botao "Trocar UG"
        $quantasUgs = $user->super_admin
            ? \App\Models\Ug::where('ativo', true)->count()
            : $user->ugs()->where('ugs.ativo', true)->count();

        return [
            'atual'     => $ugAtual,
            'multiplas' => $quantasUgs > 1,
        ];
    }
}
