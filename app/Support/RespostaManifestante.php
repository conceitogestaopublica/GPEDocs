<?php

declare(strict_types=1);

namespace App\Support;

use App\Mail\DecisaoManifestacao;
use App\Models\Processo\Processo;

/**
 * Decisão final ao manifestante fora do portal (TR 4.33.7, 4.33.23): quem veio pelo balcão,
 * telefone ou e-mail recebe a resposta no e-mail informado. Quem abriu pelo portal recebe
 * pela solicitação (e o e-mail de aviso do portal).
 */
final class RespostaManifestante
{
    public static function enviarDecisao(Processo $p, string $decisao): bool
    {
        $temSolicitacao = \App\Models\Portal\Solicitacao::withoutGlobalScope('ug')->where('processo_id', $p->id)->exists();
        if ($temSolicitacao || ! $p->requerente_email || ! filter_var($p->requerente_email, FILTER_VALIDATE_EMAIL)) {
            return false;
        }

        return RastreioEmail::enviar(new DecisaoManifestacao($p, $decisao), $p->requerente_email, 'cidadao',
            "Resposta: {$p->numero_protocolo}", 'processo', $p->id, auth()->id());
    }
}
