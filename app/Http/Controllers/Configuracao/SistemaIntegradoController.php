<?php

declare(strict_types=1);

namespace App\Http\Controllers\Configuracao;

use App\Http\Controllers\Controller;
use App\Models\Documento;
use App\Models\SistemaIntegrado;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * CRUD de Sistemas Integrados — sistemas externos autorizados a usar a
 * API REST do GPE Docs (POST /api/integracoes/documentos).
 *
 * Importante: o token e exibido APENAS uma vez (no cadastro ou ao
 * regenerar). Apos isso, so o hash fica armazenado.
 */
class SistemaIntegradoController extends Controller
{
    public function index(Request $request): Response
    {
        $sistemas = SistemaIntegrado::orderByDesc('ativo')
            ->orderBy('codigo')
            ->get()
            ->map(function ($s) {
                $s->total_documentos = Documento::where('sistema_origem', $s->codigo)->count();
                $s->token_mascarado  = $s->token_mascarado;
                $s->total_webhooks   = \App\Models\WebhookLog::where('sistema_origem', $s->codigo)->count();
                $s->webhooks_falha   = \App\Models\WebhookLog::where('sistema_origem', $s->codigo)
                    ->where('sucesso', false)->count();
                return $s;
            });

        // Logs recentes (todos sistemas, ultimos 50)
        $logs = \App\Models\WebhookLog::with(['documento:id,nome,numero_externo,sistema_origem'])
            ->orderByDesc('id')
            ->limit(50)
            ->get();

        return Inertia::render('Configuracao/SistemasIntegrados/Index', [
            'sistemas' => $sistemas,
            'logs'     => $logs,
        ]);
    }

    /**
     * Reenvia manualmente um webhook que falhou (admin).
     * Chamado da tela de logs.
     */
    public function reenviarWebhook($logId)
    {
        $log = \App\Models\WebhookLog::findOrFail($logId);
        $sucesso = app(\App\Services\WebhookDispatcher::class)->reenviar($log);

        return redirect()->back()->with(
            $sucesso ? 'success' : 'error',
            $sucesso ? 'Webhook reenviado com sucesso.' : 'Falha ao reenviar — verifique o detalhe nos logs.'
        );
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'codigo'    => ['required', 'string', 'max:50', 'regex:/^[a-z0-9_-]+$/', 'unique:ged_sistemas_integrados,codigo'],
            'nome'      => ['required', 'string', 'max:200'],
            'descricao' => ['nullable', 'string'],
        ]);

        $sistema = new SistemaIntegrado([
            'codigo'    => strtolower($validated['codigo']),
            'nome'      => $validated['nome'],
            'descricao' => $validated['descricao'] ?? null,
            'ativo'     => true,
        ]);

        $tokenPuro = $sistema->gerarToken();
        $sistema->save();

        // Token + secret vao via flash e sao exibidos no Index uma unica vez
        return redirect()->route('configuracoes.sistemas-integrados.index')
            ->with('token_gerado', [
                'codigo'         => $sistema->codigo,
                'token'          => $tokenPuro,
                'webhook_secret' => $sistema->webhook_secret,
            ])
            ->with('success', "Sistema '{$sistema->codigo}' cadastrado. Copie o token e o webhook secret agora — nao serao exibidos novamente.");
    }

    public function update(Request $request, $id)
    {
        $sistema = SistemaIntegrado::findOrFail($id);

        $validated = $request->validate([
            'nome'      => ['required', 'string', 'max:200'],
            'descricao' => ['nullable', 'string'],
        ]);

        $sistema->update($validated);

        return redirect()->back()->with('success', 'Sistema atualizado.');
    }

    public function regenerarToken($id)
    {
        $sistema = SistemaIntegrado::findOrFail($id);
        $tokenPuro = $sistema->gerarToken();
        $sistema->save();

        return redirect()->back()
            ->with('token_gerado', [
                'codigo'         => $sistema->codigo,
                'token'          => $tokenPuro,
                'webhook_secret' => $sistema->webhook_secret,
            ])
            ->with('success', "Token regenerado para '{$sistema->codigo}'. Copie agora — o anterior foi invalidado.");
    }

    /**
     * Regenera apenas o webhook secret (sem mexer no API token).
     * Util quando o secret foi vazado mas o token ainda esta seguro.
     */
    public function regenerarWebhookSecret($id)
    {
        $sistema = SistemaIntegrado::findOrFail($id);
        $secret = $sistema->regenerarWebhookSecret();
        $sistema->save();

        return redirect()->back()
            ->with('token_gerado', [
                'codigo'         => $sistema->codigo,
                'webhook_secret' => $secret,
                'so_secret'      => true,
            ])
            ->with('success', "Webhook secret regenerado para '{$sistema->codigo}'.");
    }

    public function toggleAtivo($id)
    {
        $sistema = SistemaIntegrado::findOrFail($id);
        $sistema->update(['ativo' => ! $sistema->ativo]);

        return redirect()->back()->with('success',
            'Sistema ' . ($sistema->ativo ? 'reativado' : 'desativado') . '.');
    }

    public function destroy($id)
    {
        $sistema = SistemaIntegrado::findOrFail($id);

        $totalDocs = Documento::where('sistema_origem', $sistema->codigo)->count();
        if ($totalDocs > 0) {
            return redirect()->back()->with('error',
                "Nao e possivel excluir: {$totalDocs} documento(s) ja foram enviados por este sistema. Use 'Desativar' em vez de excluir.");
        }

        $sistema->delete();
        return redirect()->back()->with('success', 'Sistema excluido.');
    }
}
