<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Portal;

use App\Models\Notificacao;
use App\Models\Processo\Oficio;
use App\Models\Processo\OficioAnexo;
use App\Models\Processo\OficioResposta;
use App\Support\Verificacoes;
use App\Tenant\TenantStorage;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Central de Atendimento no portal (TR 4.23): verificação de autenticidade por código com
 * registro (4.23.14–16), textos do portal — política de privacidade e acesso à informação
 * (4.23.1, 4.23.5) — e resposta a ofício eletrônico pela Central (4.23.6).
 */
class CentralController extends PortalApiController
{
    public function verificar(Request $request, string $codigo): JsonResponse
    {
        return response()->json(Verificacoes::consultar($request, $codigo));
    }

    public function textos(): JsonResponse
    {
        return response()->json(self::textosPortal());
    }

    /** @return array<string, array{titulo: string, texto: ?string, url: ?string}> */
    public static function textosPortal(): array
    {
        return DB::table('ged_portal_textos')->get(['chave', 'titulo', 'texto', 'url'])
            ->mapWithKeys(fn ($t) => [$t->chave => ['titulo' => $t->titulo, 'texto' => $t->texto, 'url' => $t->url]])->all();
    }

    /** O ofício pelo código do link (o mesmo do e-mail), com as respostas já enviadas. */
    public function oficio(string $token): JsonResponse
    {
        $o = $this->oficioPorToken($token);

        return response()->json([
            'numero' => $o->numero, 'assunto' => $o->assunto, 'orgao' => $o->ug?->nome, 'setor' => $o->setor_origem,
            'enviado_em' => ($o->enviado_em ?? $o->created_at)?->toIso8601String(), 'destinatario' => $o->destinatario_nome,
            'pdf_url' => url("/oficios/rastrear/{$o->rastreio_token}/pdf"),
            'respostas' => $o->respostas()->where('externo', true)->orderBy('created_at')->get()
                ->map(fn ($r) => ['nome' => $r->respondente_nome, 'texto' => $r->conteudo, 'em' => $r->created_at?->toIso8601String()]),
        ]);
    }

    /** Resposta protocolada pela Central, com anexos (TR 4.23.6) — vai para o ofício e avisa quem enviou. */
    public function responderOficio(Request $request, string $token): JsonResponse
    {
        $d = $request->validate([
            'nome'      => ['required', 'string', 'max:200'],
            'email'     => ['nullable', 'email', 'max:255'],
            'texto'     => ['required', 'string', 'max:20000'],
            'anexos'    => ['nullable', 'array', 'max:10'],
            'anexos.*'  => ['file', 'max:20480', 'mimes:pdf,jpg,jpeg,png,doc,docx,odt,xls,xlsx'],
        ]);
        $o = $this->oficioPorToken($token);
        $resposta = DB::transaction(function () use ($request, $d, $o) {
            $r = OficioResposta::create(['oficio_id' => $o->id, 'respondente_nome' => $d['nome'], 'respondente_email' => $d['email'] ?? null,
                'conteudo' => $d['texto'], 'externo' => true]);
            foreach ((array) $request->file('anexos', []) as $arquivo) {
                OficioAnexo::create(['oficio_id' => $o->id, 'nome' => $arquivo->getClientOriginalName(),
                    'arquivo_path' => $arquivo->store(TenantStorage::pasta('oficios'), 'documentos'), 'tamanho' => $arquivo->getSize(),
                    'mime_type' => $arquivo->getMimeType(), 'solicitar_assinatura' => false, 'enviado_por' => $o->remetente_id]);
            }

            return $r;
        });
        app(\App\Services\OficioEmailService::class)->registrarLeitura($o);   // respondeu, então leu
        Notificacao::create(['usuario_id' => $o->remetente_id, 'tipo' => 'oficio_lido', 'titulo' => 'Resposta ao ofício pela Central',
            'mensagem' => "{$d['nome']} respondeu o ofício {$o->numero}" . ($request->file('anexos') ? ' com ' . count((array) $request->file('anexos')) . ' anexo(s)' : '') . '.',
            'referencia_tipo' => 'oficio', 'referencia_id' => $o->id, 'lida' => false]);

        return response()->json(['protocolo' => "{$o->numero}-R{$resposta->id}", 'recebido_em' => $resposta->created_at?->toIso8601String()], 201);
    }

    private function oficioPorToken(string $token): Oficio
    {
        $o = Oficio::withoutGlobalScopes()->with('ug')->where('rastreio_token', $token)->whereNotNull('rastreio_token')->first();
        abort_unless($o, 404, 'Ofício não encontrado.');

        return $o;
    }
}
