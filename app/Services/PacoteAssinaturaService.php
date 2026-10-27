<?php

declare(strict_types=1);

namespace App\Services;

use App\Mail\ConviteAssinaturaPacote;
use App\Models\Assinatura;
use App\Models\PacoteAssinatura;
use App\Models\PacoteConvite;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

/**
 * Pacote de assinatura: vários documentos para o mesmo signatário externo num link só.
 * Cada documento mantém a própria Assinatura (com o próprio convite individual); o pacote
 * só agrupa por CPF e manda UM e-mail por pessoa.
 */
class PacoteAssinaturaService
{
    /**
     * Intervalo mínimo entre e-mails do mesmo convite: a API recebe um documento por
     * chamada (o gpe2 manda empenho a empenho) — sem isso, cada um geraria um e-mail.
     */
    public const MINUTOS_ENTRE_EMAILS = 10;

    /** Pacote da API (sistema + código, reaproveitado entre chamadas) ou um novo (tela). */
    public function obter(string $titulo, int $solicitanteId, ?string $sistema = null, ?string $codigo = null): PacoteAssinatura
    {
        if ($sistema && $codigo) {
            return PacoteAssinatura::firstOrCreate(
                ['sistema_origem' => $sistema, 'codigo_externo' => $codigo],
                ['titulo' => $titulo, 'solicitante_id' => $solicitanteId],
            );
        }

        return PacoteAssinatura::create(['titulo' => $titulo, 'solicitante_id' => $solicitanteId]);
    }

    /** Põe a assinatura externa no convite do CPF dela dentro do pacote (cria se preciso). */
    public function vincular(PacoteAssinatura $pacote, Assinatura $assinatura): PacoteConvite
    {
        $cpf = ConviteAssinaturaService::soDigitos($assinatura->cpf_signatario);
        $convite = PacoteConvite::where('pacote_id', $pacote->id)->where('cpf', $cpf)->first();

        if (! $convite) {
            $token = Str::random(48);
            $convite = PacoteConvite::create([
                'pacote_id'  => $pacote->id,
                'cpf'        => $cpf,
                'nome'       => $assinatura->nome_signatario,
                'email'      => $assinatura->email_signatario,
                'telefone'   => $assinatura->telefone_signatario,
                'token_hash' => hash('sha256', $token),
                'token'      => $token,
                'expira_em'  => $assinatura->convite_expira_em,
            ]);
        } elseif ($assinatura->convite_expira_em && (! $convite->expira_em || $assinatura->convite_expira_em->gt($convite->expira_em))) {
            // O link do pacote vale enquanto houver documento dentro do prazo.
            $convite->update(['expira_em' => $assinatura->convite_expira_em]);
        }

        $assinatura->update(['pacote_convite_id' => $convite->id]);

        return $convite;
    }

    public function porToken(string $token): ?PacoteConvite
    {
        if (strlen($token) < 20) {
            return null;
        }

        return PacoteConvite::with('pacote.solicitante')->where('token_hash', hash('sha256', $token))->first();
    }

    /** E-mail do link do pacote — no máximo um a cada MINUTOS_ENTRE_EMAILS, salvo $forcar. */
    public function enviar(PacoteConvite $convite, bool $forcar = false): bool
    {
        if (! $convite->email) {
            return false;
        }
        if (! $forcar && $convite->enviado_em && $convite->enviado_em->gt(now()->subMinutes(self::MINUTOS_ENTRE_EMAILS))) {
            return false;
        }

        try {
            Mail::to($convite->email)->send(new ConviteAssinaturaPacote($convite));
            $convite->forceFill(['enviado_em' => now()])->save();

            return true;
        } catch (\Throwable $e) {
            Log::warning('Convite de pacote não enviado', ['pacote_convite_id' => $convite->id, 'erro' => $e->getMessage()]);

            return false;
        }
    }
}
