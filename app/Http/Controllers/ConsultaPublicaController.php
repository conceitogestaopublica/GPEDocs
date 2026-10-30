<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Processo\Parecer;
use App\Models\Processo\Processo;
use App\Models\Processo\ProcessoAnexo;
use App\Models\Processo\Tramitacao;
use App\Support\DocumentoRedigido;
use App\Support\ExternoDoProcesso;
use App\Support\RequerimentoServidor;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Inertia\Inertia;

/**
 * Consulta do processo sem login, pelo link (TR 4.48.27): andamento, decisão, pareceres,
 * participantes e os documentos liberados ao externo. Processo sigiloso (requerimento de servidor,
 * PAD, manifestação sigilosa ou anônima) não abre por link.
 */
class ConsultaPublicaController extends Controller
{
    public static function token(Processo $p): string
    {
        if (! $p->consulta_token) {
            $p->forceFill(['consulta_token' => (string) Str::uuid()])->saveQuietly();
        }

        return $p->consulta_token;
    }

    public static function url(Processo $p): string
    {
        return url('/consulta/' . self::token($p));
    }

    /** Gera (ou devolve) o link para o servidor copiar. */
    public function link(int $id)
    {
        $p = Processo::findOrFail($id);
        abort_unless(RequerimentoServidor::podeVer(Auth::user(), $p), 403);
        abort_unless(self::publicavel($p), 422, 'Processo sigiloso não tem consulta por link.');

        return response()->json(['url' => self::url($p)]);
    }

    public function show(string $token)
    {
        $p = $this->processo($token);
        ExternoDoProcesso::registrar($p, 'visualizou', null, 'Consulta pelo link', null);
        $etapas = Tramitacao::with('destinoUnidade:id,nome,sigla')->where('processo_id', $p->id)->orderBy('ordem')->orderBy('id')->get();

        return Inertia::render('Publico/ConsultaProcesso', [
            'token' => $token,
            'processo' => [
                'numero' => $p->numero_protocolo, 'assunto' => $p->assunto, 'tipo' => $p->tipoProcesso?->nome, 'status' => $p->status,
                'aberto_em' => $p->created_at?->toIso8601String(), 'concluido_em' => $p->concluido_em?->toIso8601String(),
                'decisao' => $p->decisao, 'orgao' => \App\Models\Ug::withoutGlobalScopes()->find($p->ug_id)?->nome,
            ],
            'andamento' => $etapas->map(fn ($t) => ['setor' => $t->destinoUnidade?->nome ?? $t->setor_destino, 'sigla' => $t->destinoUnidade?->sigla,
                'situacao' => $t->status, 'desde' => ($t->despachado_em ?? $t->created_at)?->toIso8601String()])->values(),
            'pareceres' => Parecer::withoutGlobalScopes()->where('processo_id', $p->id)->orderBy('id')->get()->map(fn (Parecer $x) => [
                'id' => $x->id, 'numero' => $x->numero, 'assunto' => $x->assunto, 'ementa' => $x->ementa, 'conclusao' => Parecer::CONCLUSOES[$x->conclusao] ?? $x->conclusao,
                'em' => $x->enviado_em?->toIso8601String(), 'pdf' => url("/consulta/{$token}/pareceres/{$x->id}"),
            ]),
            'participantes' => DB::table('proc_participantes')->where('processo_id', $p->id)->orderBy('id')->get(['papel', 'nome', 'registro'])
                ->map(fn ($x) => ['papel' => ExternoDoProcesso::PAPEIS[$x->papel] ?? $x->papel, 'nome' => $x->nome, 'registro' => $x->registro]),
            'documentos' => ExternoDoProcesso::anexosVisiveis($p)->map(fn ($a) => ['id' => $a->id, 'nome' => $a->nome, 'tamanho' => $a->tamanho,
                'url' => url("/consulta/{$token}/anexos/{$a->id}")])->values(),
        ]);
    }

    public function anexo(string $token, int $anexo)
    {
        $p = $this->processo($token);
        $a = ExternoDoProcesso::anexosVisiveis($p)->firstWhere('id', $anexo) ?? abort(404);
        $a = ProcessoAnexo::find($a->id);
        ExternoDoProcesso::registrar($p, 'baixou', $a->id, 'Consulta pelo link', null);

        return \App\Support\Anexos::baixar($a->arquivo_path, $a->nome);
    }

    public function parecer(string $token, int $parecer)
    {
        $p = $this->processo($token);
        $x = Parecer::withoutGlobalScopes()->where('processo_id', $p->id)->findOrFail($parecer);

        return response(DocumentoRedigido::pdfBytes('parecer', $x->load(['remetente', 'processo', 'memorando', 'anexos'])), 200,
            ['Content-Type' => 'application/pdf', 'Content-Disposition' => 'inline; filename="parecer-' . str_replace('/', '-', $x->numero) . '.pdf"']);
    }

    private function processo(string $token): Processo
    {
        $p = Str::isUuid($token) ? Processo::withoutGlobalScopes()->with('tipoProcesso:id,nome')->where('consulta_token', $token)->first() : null;
        abort_unless($p && self::publicavel($p), 404, 'Consulta não encontrada.');

        return $p;
    }

    private static function publicavel(Processo $p): bool
    {
        return ! in_array($p->tipo_processo_id, RequerimentoServidor::assuntos(), true) && ! in_array($p->identificacao, ['sigilosa', 'anonima'], true);
    }
}