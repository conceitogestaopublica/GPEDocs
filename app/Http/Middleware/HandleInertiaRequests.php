<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    protected $rootView = 'app';

    public function share(Request $request): array
    {
        $user = $request->user();

        // Count unread notifications
        $notificacoesPendentes = 0;
        if ($user)
            $notificacoesPendentes = \App\Models\Notificacao::where('usuario_id', $user->id)->where('lida', false)->count();

        // UG ativa + lista de UGs do usuario (para o seletor da topbar)
        $tenant = $this->compartilharTenant($user, $request);

        return array_merge(parent::share($request), [
            'auth' => [
                'user' => $user ? [
                    'id'          => $user->id,
                    'name'        => $user->name,
                    'email'       => $user->email,
                    'super_admin' => (bool) $user->super_admin,
                    // Subtítulo do menu do usuário na topbar (como no gpe2).
                    'perfil_nome' => $user->super_admin ? 'Super Admin' : ($user->roles()->orderBy('nome')->value('nome') ?? 'Usuário'),
                    // Só para esconder menu — quem decide é o Gate em cada rota.
                    'permissoes'  => $user->super_admin ? ['*'] : $user->permissoes(),
                ] : null,
            ],
            'flash' => [
                'success'       => $request->session()->get('success'),
                'error'         => $request->session()->get('error'),
                'warning'       => $request->session()->get('warning'),
                'info'          => $request->session()->get('info'),
                'token_gerado'  => $request->session()->get('token_gerado'),
            ],
            'notificacoes_pendentes' => $notificacoesPendentes,
            // Prévia do sino (topbar): as 6 mais recentes, sem precisar abrir a tela.
            // Lazy (closure): só consulta quando a página de fato recebe a prop — um reload
            // parcial (`only: [...]`) que não a pede não paga a query.
            'notificacoes_recentes' => fn () => $user
                ? \App\Models\Notificacao::where('usuario_id', $user->id)
                    ->orderByDesc('created_at')->limit(6)->get()
                    ->map(fn ($n) => \App\Http\Presenters\NotificacaoPresenter::resumo($n))->all()
                : [],
            // Rotinas favoritas do usuário (Ctrl+K → estrela) — pessoais e por UG da sessão.
            'favoritos' => fn () => $this->favoritos($user, $request),
            'tenant' => $tenant,
            'cidadao' => Auth::guard('cidadao')->check() ? [
                'id'       => Auth::guard('cidadao')->user()->id,
                'nome'     => Auth::guard('cidadao')->user()->nome,
                'email'    => Auth::guard('cidadao')->user()->email,
                'telefone' => Auth::guard('cidadao')->user()->telefone,
            ] : null,
        ]);
    }

    /**
     * Banco de tenant ainda sem a migration de favoritos não derruba TODA tela (a prop vai em
     * todo request): só a tabela ausente (42P01) vira lista vazia — qualquer outra falha de
     * banco continua aparecendo.
     */
    private function favoritos($user, Request $request): array
    {
        if (! $user) {
            return [];
        }

        try {
            return \App\Models\UsuarioFavorito::listar($user->id, $request->session()->get('ug_id'));
        } catch (\Illuminate\Database\QueryException $e) {
            if ($e->getCode() === '42P01') {
                return [];
            }
            throw $e;
        }
    }

    private function compartilharTenant($user, Request $request): array
    {
        if (! $user) {
            return ['atual' => null, 'multiplas' => false];
        }

        $ugAtualId = $request->session()->get('ug_id');
        $ugAtual = null;
        if ($ugAtualId) {
            $ug = \App\Models\Ug::with('logradouro.bairro.municipio.uf')->find($ugAtualId);
            if ($ug) {
                $ugAtual = [
                    'id'     => $ug->id,
                    'codigo' => $ug->codigo,
                    'nome'   => $ug->nome,
                    'cnpj'   => $ug->cnpj,
                    'cidade' => $ug->logradouro?->bairro?->municipio?->nome,
                    'uf'     => $ug->logradouro?->bairro?->municipio?->uf?->nome,
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
