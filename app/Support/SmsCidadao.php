<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\Portal\Solicitacao;

/**
 * SMS ao cidadão no protocolo pelo portal (TR 4.11.1): solicitação registrada, mudança de
 * situação e mensagem do atendente — curto, com o link de acompanhamento.
 */
final class SmsCidadao
{
    public static function solicitacao(Solicitacao $s, string $evento): void
    {
        $s->loadMissing('cidadao', 'ug');
        $numero = $s->telefone_contato ?: $s->cidadao?->telefone;
        if (! $numero || ! $s->ug) {
            return;
        }
        $status = Solicitacao::STATUS[$s->status] ?? $s->status;
        $acao = match ($evento) {
            'criada'     => 'registrada',
            'comentario' => 'tem mensagem nova do atendimento',
            default      => "agora está: {$status}",
        };
        $link = 'http://' . $s->ug->portal_slug . '.' . parse_url((string) config('app.url'), PHP_URL_HOST) . "/minhas-solicitacoes/{$s->id}";

        Sms::enfileirar($numero, "Solicitação {$s->codigo} {$acao}. Acompanhe: {$link}", 'solicitacao', $s->id);
    }
}
