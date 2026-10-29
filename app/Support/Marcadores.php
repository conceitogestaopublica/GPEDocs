<?php

declare(strict_types=1);

namespace App\Support;

use App\Mail\MencaoExterna;
use App\Models\Notificacao;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\URL;

/**
 * Marcadores do editor no documento salvo (TR 4.16.13–4.16.18):
 *  - @ menção: servidor é avisado e passa a ver o documento; contato externo recebe por
 *    e-mail um link temporário para o PDF (a ficha do documento);
 *  - # citação: o documento citado mostra onde foi citado;
 *  - ! assinatura: o PDF vai ao GED e a assinatura é pedida (servidor ou externo).
 * Roda depois de gravado; repetir não duplica (marcação única por origem/alvo).
 */
final class Marcadores
{
    public const ALVOS_DOCUMENTO = ['memorando', 'circular', 'oficio', 'processo'];

    /** @return list<array{tipo: string, alvo_tipo: string, alvo_id: int, label: string}> */
    public static function extrair(?string $html): array
    {
        preg_match_all('#<(?:span|a)\b[^>]*\bdata-type="(mencao|referencia|assinatura)"[^>]*>#u', (string) $html, $m);
        $saida = [];
        foreach ($m[0] as $i => $tag) {
            if (! preg_match('#data-id="([a-z_]+):(\d+)"#', $tag, $id)) {
                continue;
            }
            preg_match('#data-label="([^"]*)"#u', $tag, $label);
            $saida["{$m[1][$i]}|{$id[1]}|{$id[2]}"] = ['tipo' => $m[1][$i], 'alvo_tipo' => $id[1], 'alvo_id' => (int) $id[2],
                'label' => html_entity_decode($label[1] ?? '', ENT_QUOTES | ENT_HTML5, 'UTF-8')];
        }

        return array_values($saida);
    }

    /**
     * Registra os marcadores de um documento recém-gravado. `$doc` é o memorando/circular/ofício
     * (para PDF e assinatura); no despacho a origem é o processo e não há assinatura pelo texto.
     *
     * @return list<string> avisos para quem escreveu (ex.: contato sem e-mail)
     */
    public static function processar(string $origemTipo, int $origemId, ?string $html, User $autor, ?Model $doc = null, ?string $rotuloOrigem = null): array
    {
        $avisos = [];
        $servidoresAssinar = [];
        $externosAssinar = [];
        $rotuloOrigem ??= DocumentoRedigido::rotulo($origemTipo) . ($doc?->numero ? " {$doc->numero}" : '');

        foreach (self::extrair($html) as $m) {
            if ($m['tipo'] === 'referencia') {
                if (in_array($m['alvo_tipo'], self::ALVOS_DOCUMENTO, true) && ! ($m['alvo_tipo'] === $origemTipo && $m['alvo_id'] === $origemId)
                    && self::documentoExiste($m['alvo_tipo'], $m['alvo_id'])) {
                    self::gravar($origemTipo, $origemId, $m, $autor);
                }
                continue;
            }
            if ($m['alvo_tipo'] === 'usuario') {
                $u = User::query()->where('id', $m['alvo_id'])->where('tipo', 'interno')->first();
                if (! $u) {
                    continue;
                }
                $nova = self::gravar($origemTipo, $origemId, $m, $autor);
                if ($m['tipo'] === 'mencao' && $nova && $u->id !== $autor->id) {
                    Notificacao::create([
                        'usuario_id' => $u->id, 'tipo' => 'mencao', 'titulo' => "{$autor->name} mencionou você",
                        'mensagem' => "{$rotuloOrigem}" . ($doc?->assunto ? " — {$doc->assunto}" : ''),
                        'referencia_tipo' => $origemTipo, 'referencia_id' => $origemId, 'lida' => false,
                    ]);
                }
                if ($m['tipo'] === 'assinatura' && $nova) {
                    $servidoresAssinar[] = $u->id;
                }
                continue;
            }
            if ($m['alvo_tipo'] === 'contato') {
                $c = DB::table('contatos')->where('id', $m['alvo_id'])->whereNull('unificado_em_id')->first();
                if (! $c) {
                    continue;
                }
                if ($m['tipo'] === 'mencao') {
                    if (! $c->email) { $avisos[] = "{$c->nome} não tem e-mail no cadastro de contatos: a menção ficou no texto, mas não foi enviada."; continue; }
                    if (! $doc) { continue; }   // despacho: o contato não tem ficha do processo por link
                    if (self::gravar($origemTipo, $origemId, $m, $autor)) {
                        self::avisarExterno($c, $origemTipo, $origemId, $doc, $autor, $rotuloOrigem);
                    }
                } elseif ($m['tipo'] === 'assinatura') {
                    if (! $c->cpf || ! $c->email) { $avisos[] = "{$c->nome} precisa de CPF e e-mail no cadastro de contatos para assinar: a assinatura não foi pedida."; continue; }
                    if (self::gravar($origemTipo, $origemId, $m, $autor)) {
                        $externosAssinar[] = ['nome' => $c->nome, 'cpf' => $c->cpf, 'email' => $c->email, 'telefone' => $c->celular ?: $c->telefone];
                    }
                }
            }
        }

        if (($servidoresAssinar || $externosAssinar) && $doc) {
            try {
                $docId = DocumentoRedigido::pedirAssinatura($origemTipo, $doc, $servidoresAssinar, $externosAssinar, $autor);
                DB::table('ged_marcacoes')->where(['origem_tipo' => $origemTipo, 'origem_id' => $origemId, 'tipo' => 'assinatura'])
                    ->whereNull('documento_id')->update(['documento_id' => $docId]);
            } catch (\Throwable $e) {
                Log::warning('Assinatura pedida no texto falhou', ['origem' => "{$origemTipo}:{$origemId}", 'erro' => $e->getMessage()]);
                $avisos[] = 'Não foi possível pedir as assinaturas marcadas no texto. Peça pela tela do documento no GED.';
            }
        }

        return $avisos;
    }

    /** Depois do commit: falha aqui não pode virar "erro ao enviar" de um documento já gravado. */
    public static function aposSalvar(string $origemTipo, int $origemId, ?string $html, User $autor, ?Model $doc = null, ?string $rotuloOrigem = null): array
    {
        try {
            return self::processar($origemTipo, $origemId, $html, $autor, $doc, $rotuloOrigem);
        } catch (\Throwable $e) {
            Log::error('Marcadores do editor não processados', ['origem' => "{$origemTipo}:{$origemId}", 'erro' => $e->getMessage()]);

            return ['As menções, citações e assinaturas do texto não puderam ser registradas agora.'];
        }
    }
    /** Servidor mencionado no documento (ou que recebeu pedido de assinatura) passa a ver o documento. */
    public static function temAcesso(string $tipo, int $id, ?int $userId): bool
    {
        return $userId !== null && DB::table('ged_marcacoes')
            ->where(['origem_tipo' => $tipo, 'origem_id' => $id, 'alvo_tipo' => 'usuario', 'alvo_id' => $userId])
            ->whereIn('tipo', ['mencao', 'assinatura'])->exists();
    }

    /** Onde este documento foi citado (TR 4.16.15). */
    public static function citadoEm(string $tipo, int $id): array
    {
        return DB::table('ged_marcacoes as m')->leftJoin('users as u', 'u.id', '=', 'm.criado_por')
            ->where(['m.tipo' => 'referencia', 'm.alvo_tipo' => $tipo, 'm.alvo_id' => $id])
            ->orderByDesc('m.created_at')->get(['m.origem_tipo', 'm.origem_id', 'm.created_at', 'u.name'])
            ->map(fn ($r) => [
                'rotulo' => DocumentoRedigido::rotulo($r->origem_tipo) . ' ' . self::numero($r->origem_tipo, (int) $r->origem_id),
                'assunto' => self::assunto($r->origem_tipo, (int) $r->origem_id),
                'url' => DocumentoRedigido::url($r->origem_tipo, (int) $r->origem_id),
                'por' => $r->name, 'em' => \Illuminate\Support\Carbon::parse($r->created_at, 'UTC')->toIso8601String(),
            ])->all();
    }

    /** Menções e assinaturas pedidas no texto deste documento, com a situação da assinatura. */
    public static function doDocumento(string $tipo, int $id): array
    {
        return DB::table('ged_marcacoes as m')
            ->leftJoin('users as u', fn ($j) => $j->on('u.id', '=', 'm.alvo_id')->where('m.alvo_tipo', 'usuario'))
            ->leftJoin('contatos as c', fn ($j) => $j->on('c.id', '=', 'm.alvo_id')->where('m.alvo_tipo', 'contato'))
            ->where(['m.origem_tipo' => $tipo, 'm.origem_id' => $id])->whereIn('m.tipo', ['mencao', 'assinatura'])
            ->orderBy('m.id')->get(['m.tipo', 'm.alvo_tipo', 'm.alvo_id', 'm.documento_id', 'u.name', 'c.nome', 'u.email as eu', 'c.email as ec', 'c.cpf'])
            ->map(function ($r) {
                $status = null;
                if ($r->tipo === 'assinatura' && $r->documento_id) {
                    $q = DB::table('ged_assinaturas')->where('documento_id', $r->documento_id);
                    $r->alvo_tipo === 'usuario' ? $q->where('signatario_id', $r->alvo_id) : $q->where('cpf_signatario', $r->cpf);
                    $status = $q->value('status');
                }

                return ['tipo' => $r->tipo, 'externo' => $r->alvo_tipo === 'contato', 'nome' => $r->name ?? $r->nome,
                    'status' => $status, 'documento_id' => $r->documento_id];
            })->all();
    }

    private static function gravar(string $origemTipo, int $origemId, array $m, User $autor): bool
    {
        return DB::table('ged_marcacoes')->insertOrIgnore([
            'origem_tipo' => $origemTipo, 'origem_id' => $origemId, 'tipo' => $m['tipo'],
            'alvo_tipo' => $m['alvo_tipo'], 'alvo_id' => $m['alvo_id'], 'criado_por' => $autor->id, 'created_at' => now(),
        ]) > 0;
    }

    private static function avisarExterno(object $c, string $tipo, int $id, Model $doc, User $autor, string $rotulo): void
    {
        $link = URL::temporarySignedRoute('documentos-redigidos.publico', now()->addDays(30), ['tipo' => $tipo, 'id' => $id]);
        try {
            Mail::to($c->email)->send(new MencaoExterna($c->nome, $autor->name, $rotulo, (string) $doc->assunto, $link, $doc->ug_id));
        } catch (\Throwable $e) {
            Log::warning('E-mail de menção a contato falhou', ['contato' => $c->id, 'erro' => $e->getMessage()]);
        }
    }

    private static function documentoExiste(string $tipo, int $id): bool
    {
        return self::tabela($tipo) !== null && DB::table(self::tabela($tipo))->where('id', $id)->where('ug_id', session('ug_id'))->exists();
    }

    private static function tabela(string $tipo): ?string
    {
        return ['memorando' => 'proc_memorandos', 'circular' => 'proc_circulares', 'oficio' => 'proc_oficios', 'processo' => 'proc_processos'][$tipo] ?? null;
    }

    private static function numero(string $tipo, int $id): string
    {
        return (string) DB::table(self::tabela($tipo))->where('id', $id)->value($tipo === 'processo' ? 'numero_protocolo' : 'numero');
    }

    private static function assunto(string $tipo, int $id): string
    {
        return (string) DB::table(self::tabela($tipo))->where('id', $id)->value('assunto');
    }
}
