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

        return array_merge(parent::share($request), [
            'auth' => [
                'user' => $user ? [
                    'id'    => $user->id,
                    'name'  => $user->name,
                    'email' => $user->email,
                ] : null,
            ],
            'flash' => [
                'success' => $request->session()->get('success'),
                'error'   => $request->session()->get('error'),
                'warning' => $request->session()->get('warning'),
            ],
            'notificacoes_pendentes' => $notificacoesPendentes,
        ]);
    }
}
