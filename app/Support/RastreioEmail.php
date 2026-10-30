<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\Notificacao;
use App\Models\User;
use Illuminate\Mail\Mailable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

/**
 * Rastreio dos e-mails enviados para fora (TR 4.11.7–4.11.14): entregue (pelo provedor),
 * aberto (pixel), clicado (link) ou falhou (com motivo), e de que dispositivo. Quem enviou é
 * avisado conforme escolheu, por módulo, em Meu perfil › Preferências.
 */
final class RastreioEmail
{
    public const MODULOS = ['oficio' => 'Ofícios', 'mencao' => 'Menções a contatos', 'assinatura' => 'Convites de assinatura', 'cidadao' => 'Avisos ao cidadão'];

    public const EVENTOS = ['falha' => 'Falha na entrega', 'entrega' => 'Entrega', 'abertura' => 'Abertura', 'clique' => 'Clique no link'];

    private const COLUNA = ['entrega' => 'entregue_em', 'abertura' => 'aberto_em', 'clique' => 'clicado_em', 'falha' => null];

    private const STATUS = ['entrega' => 'entregue', 'abertura' => 'aberto', 'clique' => 'clicado', 'falha' => 'falhou'];

    private const ORDEM = ['enviado' => 0, 'entregue' => 1, 'aberto' => 2, 'clicado' => 3, 'falhou' => 4];

    /** Envia rastreando. Falha não interrompe quem chamou: fica registrada e avisada. */
    public static function enviar(Mailable $mail, string $para, string $modulo, ?string $assunto, ?string $refTipo, ?int $refId, ?int $remetenteId): bool
    {
        $token = Str::random(40);
        DB::table('ged_emails_rastreados')->insert([
            'token' => $token, 'modulo' => $modulo, 'referencia_tipo' => $refTipo, 'referencia_id' => $refId,
            'remetente_id' => $remetenteId, 'destinatario' => mb_strtolower(trim($para)),
            'assunto' => $assunto ? mb_substr($assunto, 0, 300) : null,
            'status' => 'enviado', 'created_at' => now(), 'updated_at' => now(),
        ]);
        try {
            Mail::to($para)->send(method_exists($mail, 'rastreado') ? $mail->rastreado($token) : $mail);
            DB::table('ged_emails_rastreados')->where('token', $token)->update(['enviado_em' => now(), 'updated_at' => now()]);

            return true;
        } catch (\Throwable $e) {
            Log::warning('E-mail rastreado não enviado', ['modulo' => $modulo, 'erro' => $e->getMessage()]);
            self::registrar($token, 'falha', null, $e->getMessage());

            return false;
        }
    }

    public static function pixel(string $token): string
    {
        return url("/e/{$token}/a");
    }

    /** Link que passa pelo GED antes de seguir; a assinatura impede trocar o destino. */
    public static function link(string $token, string $destino): string
    {
        return url("/e/{$token}/l") . '?' . http_build_query(['u' => $destino, 's' => self::assinar($token, $destino)]);
    }

    public static function confere(string $token, string $destino, string $assinatura): bool
    {
        return hash_equals(self::assinar($token, $destino), $assinatura);
    }

    /** Registra o evento (só a primeira vez de cada um) e avisa quem enviou, se ele quer. */
    public static function registrar(string $token, string $evento, ?string $userAgent = null, ?string $motivo = null): void
    {
        if (! array_key_exists($evento, self::COLUNA)) {
            return;
        }
        $r = DB::table('ged_emails_rastreados')->where('token', $token)->first();
        $coluna = self::COLUNA[$evento];
        if (! $r || ($coluna && $r->{$coluna}) || ($evento === 'falha' && $r->status === 'falhou')) {
            return;
        }
        $status = self::STATUS[$evento];
        $dados = ['updated_at' => now(), 'status' => self::ORDEM[$status] >= (self::ORDEM[$r->status] ?? 0) ? $status : $r->status];
        if ($coluna) {
            $dados[$coluna] = now();
        }
        if ($evento === 'falha') {
            $dados['falha_motivo'] = mb_substr(self::motivoLegivel((string) $motivo), 0, 300);
        }
        if ($userAgent && ! $r->dispositivo) {
            $dados['dispositivo'] = mb_substr(Dispositivo::descrever($userAgent), 0, 200);
        }
        DB::table('ged_emails_rastreados')->where('id', $r->id)->update($dados);

        self::avisar($r, $evento, $motivo);
    }

    private static function avisar(object $r, string $evento, ?string $motivo): void
    {
        $user = $r->remetente_id ? User::find($r->remetente_id) : null;
        if (! $user || ! in_array($r->modulo, PreferenciasUsuario::de($user)['rastreio'][$evento] ?? [], true)) {
            return;
        }
        $titulos = ['falha' => 'E-mail não entregue', 'entrega' => 'E-mail entregue', 'abertura' => 'E-mail aberto', 'clique' => 'Link do e-mail acessado'];
        $tipos = ['falha' => 'email_falhou', 'entrega' => 'email_entregue', 'abertura' => 'email_aberto', 'clique' => 'email_clicado'];
        Notificacao::create([
            'usuario_id' => $user->id, 'tipo' => $tipos[$evento], 'titulo' => $titulos[$evento],
            'mensagem' => trim("{$r->destinatario} — " . ($r->assunto ?? '') . ($motivo ? ' · ' . self::motivoLegivel($motivo) : '')),
            'referencia_tipo' => $r->referencia_tipo, 'referencia_id' => $r->referencia_id, 'lida' => false,
        ]);
    }

    /** Motivo de falha em português, a partir da mensagem do servidor de e-mail. */
    public static function motivoLegivel(string $erro): string
    {
        return match (true) {
            (bool) preg_match('/user unknown|no such user|does not exist|5\.1\.1|mailbox unavailable/i', $erro) => 'Endereço de e-mail não existe',
            (bool) preg_match('/mailbox full|quota|5\.2\.2/i', $erro)                                          => 'Caixa postal cheia',
            (bool) preg_match('/spam|blocked|blacklist|5\.7\./i', $erro)                                       => 'Bloqueado pelo servidor do destinatário',
            (bool) preg_match('/timed? ?out|connection|could not connect/i', $erro)                           => 'Servidor de e-mail indisponível',
            default                                                                                             => mb_substr($erro, 0, 200),
        };
    }

    private static function assinar(string $token, string $destino): string
    {
        return substr(hash_hmac('sha256', "{$token}|{$destino}", (string) config('app.key')), 0, 32);
    }
}
