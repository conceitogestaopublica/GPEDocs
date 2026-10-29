<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\Notificacao;
use App\Models\Portal\Solicitacao;
use App\Models\Portal\SolicitacaoAnexo;
use App\Models\Portal\SolicitacaoEvento;
use App\Models\Processo\Memorando;
use App\Models\Processo\MemorandoResposta;
use App\Models\Processo\Oficio;
use App\Models\Processo\OficioAnexo;
use App\Models\Processo\OficioResposta;
use App\Models\Processo\Processo;
use App\Models\Processo\ProcessoAnexo;
use App\Models\Processo\ProcessoComentario;
use App\Models\User;
use App\Tenant\TenantStorage;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Resposta por e-mail vira despacho no documento (TR 4.7.23, 4.8.8–4.8.9, 4.47.3).
 *
 * Ao sair, o e-mail ganha Reply-To resposta+código@domínio (MAIL_RESPOSTAS_DOMINIO). O provedor
 * entrega a resposta no webhook; aqui ela é conferida (o remetente tem de ser quem recebeu),
 * perde o texto citado do e-mail original e entra no documento com os anexos:
 * processo → despacho por e-mail; memorando → resposta; ofício → resposta externa;
 * solicitação do portal → mensagem do cidadão.
 */
final class RespostaPorEmail
{
    private const MAX_ANEXO = 20 * 1024 * 1024;

    /** Endereço de resposta para este documento e esta pessoa; null se o recurso está desligado. */
    public static function endereco(string $refTipo, int $refId, ?string $email, ?int $usuarioId = null): ?string
    {
        $dominio = (string) config('mail.respostas.dominio');
        $email = mb_strtolower(trim((string) $email));
        if ($dominio === '' || $email === '' || ! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return null;
        }
        $chave = ['referencia_tipo' => $refTipo, 'referencia_id' => $refId, 'email_esperado' => $email];
        $token = DB::table('ged_respostas_email')->where($chave)->value('token');
        if (! $token) {
            $token = Str::lower(Str::random(24));
            DB::table('ged_respostas_email')->insertOrIgnore($chave + ['token' => $token, 'usuario_id' => $usuarioId,
                'expira_em' => now()->addDays(180), 'created_at' => now()]);
            $token = DB::table('ged_respostas_email')->where($chave)->value('token');
        }

        return "resposta+{$token}@{$dominio}";
    }

    /**
     * E-mail recebido do provedor: {de, para: string|array, assunto, texto, html, anexos: [{nome, tipo, conteudo (base64)}]}.
     * @return array{resultado: string, motivo: ?string}
     */
    public static function receber(array $msg): array
    {
        $de = self::enderecoDe((string) ($msg['de'] ?? ''));
        $para = implode(', ', (array) ($msg['para'] ?? []));
        $assunto = mb_substr((string) ($msg['assunto'] ?? ''), 0, 300);
        $anexos = array_values(array_filter((array) ($msg['anexos'] ?? []), fn ($a) => ! empty($a['conteudo'])));
        $log = fn (string $res, ?string $motivo, ?object $r = null, ?string $token = null) => DB::table('ged_emails_recebidos')->insert([
            'de' => $de, 'para' => mb_substr($para, 0, 500), 'assunto' => $assunto, 'token' => $token, 'resultado' => $res, 'motivo' => $motivo,
            'referencia_tipo' => $r?->referencia_tipo, 'referencia_id' => $r?->referencia_id, 'anexos' => $res === 'registrado' ? count($anexos) : 0,
            'created_at' => now(),
        ]);

        preg_match('/resposta\+([a-z0-9]{24})@/i', $para, $m);
        $r = isset($m[1]) ? DB::table('ged_respostas_email')->where('token', mb_strtolower($m[1]))->first() : null;
        if (! $r) {
            $log('sem_destino', 'Endereço de resposta desconhecido', null, $m[1] ?? null);

            return ['resultado' => 'sem_destino', 'motivo' => 'Endereço de resposta desconhecido'];
        }
        // Só quem recebeu o e-mail responde por ele: o código vazado não basta
        if ($de !== $r->email_esperado || ($r->expira_em && now()->gt($r->expira_em))) {
            $motivo = $de !== $r->email_esperado ? "Remetente {$de} não é o destinatário do e-mail" : 'Endereço de resposta expirado';
            $log('recusado', $motivo, $r, $r->token);

            return ['resultado' => 'recusado', 'motivo' => $motivo];
        }

        $texto = self::semCitacao((string) ($msg['texto'] ?? '') ?: strip_tags((string) ($msg['html'] ?? '')));
        if ($texto === '' && ! $anexos) {
            $log('recusado', 'Resposta vazia', $r, $r->token);

            return ['resultado' => 'recusado', 'motivo' => 'Resposta vazia'];
        }

        DB::transaction(fn () => self::registrar($r, $de, $texto, $anexos));
        $log('registrado', null, $r, $r->token);

        return ['resultado' => 'registrado', 'motivo' => null];
    }

    private static function registrar(object $r, string $de, string $texto, array $anexos): void
    {
        $user = $r->usuario_id ? User::find($r->usuario_id) : null;
        $html = '<p>' . nl2br(e($texto), false) . '</p>';
        $guardar = function (string $pasta, array $a): ?array {
            $bytes = base64_decode((string) $a['conteudo'], true);
            if ($bytes === false || strlen($bytes) > self::MAX_ANEXO) {
                return null;
            }
            $nome = mb_substr(basename((string) ($a['nome'] ?? 'anexo')), 0, 200) ?: 'anexo';
            $path = TenantStorage::pasta($pasta) . '/' . Str::random(40) . '.' . (pathinfo($nome, PATHINFO_EXTENSION) ?: 'bin');
            Storage::disk('documentos')->put($path, $bytes);

            return ['nome' => $nome, 'arquivo_path' => $path, 'tamanho' => strlen($bytes), 'mime_type' => $a['tipo'] ?? 'application/octet-stream', 'hash_sha256' => hash('sha256', $bytes)];
        };

        switch ($r->referencia_tipo) {
            case 'processo':
                $p = Processo::withoutGlobalScopes()->findOrFail($r->referencia_id);
                ProcessoComentario::create(['processo_id' => $p->id, 'tramitacao_id' => $p->etapa_atual_id, 'usuario_id' => $user->id,
                    'texto' => "[Despacho por e-mail] {$texto}", 'interno' => true]);
                foreach ($anexos as $a) {
                    if ($dados = $guardar('processos', $a)) {
                        ProcessoAnexo::create($dados + ['processo_id' => $p->id, 'tramitacao_id' => $p->etapa_atual_id, 'enviado_por' => $user->id]);
                    }
                }
                break;
            case 'memorando':
                MemorandoResposta::create(['memorando_id' => $r->referencia_id, 'usuario_id' => $user->id, 'conteudo' => $html]);
                $m = Memorando::withoutGlobalScopes()->find($r->referencia_id);
                if ($m && $m->remetente_id !== $user->id) {
                    Notificacao::create(['usuario_id' => $m->remetente_id, 'tipo' => 'memorando_resposta', 'titulo' => 'Resposta por e-mail no memorando',
                        'mensagem' => "{$user->name} respondeu o memorando {$m->numero} por e-mail.", 'referencia_tipo' => 'memorando', 'referencia_id' => $m->id, 'lida' => false]);
                }
                break;
            case 'oficio':
                $o = Oficio::withoutGlobalScopes()->findOrFail($r->referencia_id);
                OficioResposta::create(['oficio_id' => $o->id, 'respondente_nome' => $o->destinatario_nome, 'respondente_email' => $de, 'conteudo' => $texto, 'externo' => true]);
                foreach ($anexos as $a) {
                    if ($dados = $guardar('oficios', $a)) {
                        OficioAnexo::create(array_diff_key($dados, ['hash_sha256' => 1]) + ['oficio_id' => $o->id, 'solicitar_assinatura' => false, 'enviado_por' => $o->remetente_id]);
                    }
                }
                Notificacao::create(['usuario_id' => $o->remetente_id, 'tipo' => 'oficio_lido', 'titulo' => 'Resposta ao ofício por e-mail',
                    'mensagem' => "{$o->destinatario_nome} respondeu o ofício {$o->numero}" . ($anexos ? ' com ' . count($anexos) . ' anexo(s)' : '') . '.',
                    'referencia_tipo' => 'oficio', 'referencia_id' => $o->id, 'lida' => false]);
                break;
            case 'solicitacao':
                $s = Solicitacao::withoutGlobalScopes()->findOrFail($r->referencia_id);
                SolicitacaoEvento::create(['solicitacao_id' => $s->id, 'tipo' => 'comentario', 'autor_tipo' => 'cidadao',
                    'autor_nome' => $s->cidadao?->nome ?? $de, 'autor_cidadao_id' => $s->cidadao_id, 'mensagem' => "[Resposta por e-mail] {$texto}"]);
                foreach ($anexos as $a) {
                    if ($dados = $guardar('portal', $a)) {
                        SolicitacaoAnexo::create($dados + ['solicitacao_id' => $s->id]);
                    }
                }
                break;
        }
    }

    /** Tira do texto a mensagem original citada ("Em ... escreveu:", "> ...", "-----Original"). */
    public static function semCitacao(string $texto): string
    {
        $texto = str_replace("\r\n", "\n", $texto);
        $corte = preg_split('/^\s*(Em .{5,200} escreveu:|On .{5,200} wrote:|-{2,}\s*(Mensagem original|Original Message)\s*-{2,}|De: .+\nEnviad[oa]: )/mi', $texto)[0] ?? $texto;
        $linhas = array_filter(explode("\n", $corte), fn ($l) => ! str_starts_with(ltrim($l), '>'));

        return trim(mb_substr(implode("\n", $linhas), 0, 20000));
    }

    private static function enderecoDe(string $de): string
    {
        return mb_strtolower(preg_match('/<([^>]+)>/', $de, $m) ? trim($m[1]) : trim($de));
    }
}
