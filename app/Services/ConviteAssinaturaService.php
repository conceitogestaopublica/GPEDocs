<?php

declare(strict_types=1);

namespace App\Services;

use App\Mail\ConviteAssinaturaExterna;
use App\Models\Assinatura;
use App\Models\Documento;
use App\Models\SolicitacaoAssinatura;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

/**
 * Convite de assinatura para signatário EXTERNO: quem não é usuário do GED recebe um link
 * /assinar/{token}, faz o cadastro (portal_cidadaos) com o CPF do convite e assina.
 *
 * O token só existe no link: o banco guarda o hash (para localizar) e o token cifrado com
 * a APP_KEY (para o solicitante copiar o link de novo e mandar por WhatsApp).
 */
class ConviteAssinaturaService
{
    /** Validade padrão do link, em dias. */
    public const VALIDADE_DIAS = 30;

    /**
     * @param  array{nome:string, cpf:string, email?:?string, telefone?:?string}  $dados
     */
    public function criar(
        SolicitacaoAssinatura $solicitacao,
        Documento $documento,
        array $dados,
        int $ordem,
        ?array $posicao = null,
    ): Assinatura {
        $token = Str::random(48);
        $prazo = $solicitacao->prazo ? $solicitacao->prazo->copy()->endOfDay() : null;

        return Assinatura::create([
            'solicitacao_id'      => $solicitacao->id,
            'documento_id'        => $documento->id,
            'signatario_id'       => null,
            'tipo_signatario'     => 'externo',
            'nome_signatario'     => trim($dados['nome']),
            'cpf_signatario'      => self::soDigitos($dados['cpf']),
            'email_signatario'    => isset($dados['email']) && $dados['email'] !== '' ? mb_strtolower(trim($dados['email'])) : null,
            'telefone_signatario' => $dados['telefone'] ?? null,
            'ordem'               => $ordem,
            'status'              => 'pendente',
            'signature_position'  => $posicao,
            'convite_token_hash'  => hash('sha256', $token),
            'convite_token'       => $token,
            'convite_expira_em'   => $prazo ?? now()->addDays(self::VALIDADE_DIAS),
        ]);
    }

    /** Convite pelo token do link (null se não existe). */
    public function porToken(string $token): ?Assinatura
    {
        if (strlen($token) < 20) {
            return null;
        }

        // Página pública: quem abre o link pode estar logado no GED em outra UG — a
        // autorização aqui é o próprio convite (CPF do cadastro), não os escopos.
        return Assinatura::with([
            'documento' => fn ($q) => $q->withoutGlobalScopes(['ug', 'sigilo']),
            'documento.versaoAtual',
            'solicitacao.solicitante',
        ])
            ->where('tipo_signatario', 'externo')
            ->where('convite_token_hash', hash('sha256', $token))
            ->first();
    }

    public function expirado(Assinatura $assinatura): bool
    {
        return $assinatura->convite_expira_em !== null && $assinatura->convite_expira_em->isPast();
    }

    /**
     * Manda o link por e-mail, se o convite tem e-mail. Sem e-mail, o solicitante copia o
     * link na tela do documento e manda por outro meio (WhatsApp). Falha de envio não
     * derruba a solicitação: o link continua valendo.
     */
    public function enviar(Assinatura $assinatura): bool
    {
        if (! $assinatura->email_signatario || ! $assinatura->convite_token) {
            return false;
        }

        try {
            Mail::to($assinatura->email_signatario)->send(new ConviteAssinaturaExterna($assinatura));
            $assinatura->forceFill(['convite_enviado_em' => now()])->save();

            return true;
        } catch (\Throwable $e) {
            Log::warning('Convite de assinatura externa não enviado', [
                'assinatura_id' => $assinatura->id,
                'erro'          => $e->getMessage(),
            ]);

            return false;
        }
    }

    public static function soDigitos(?string $valor): string
    {
        return preg_replace('/\D/', '', (string) $valor);
    }

    /** CPF com dígitos verificadores válidos. */
    public static function cpfValido(?string $cpf): bool
    {
        $d = self::soDigitos($cpf);
        if (strlen($d) !== 11 || preg_match('/^(\d)\1{10}$/', $d)) {
            return false;
        }
        for ($t = 9; $t < 11; $t++) {
            $soma = 0;
            for ($i = 0; $i < $t; $i++) {
                $soma += (int) $d[$i] * (($t + 1) - $i);
            }
            $digito = ((10 * $soma) % 11) % 10;
            if ((int) $d[$t] !== $digito) {
                return false;
            }
        }

        return true;
    }

    public static function mascararCpf(?string $cpf): string
    {
        return (string) \App\Support\DocumentoPessoa::mascararCpf($cpf);
    }
}
