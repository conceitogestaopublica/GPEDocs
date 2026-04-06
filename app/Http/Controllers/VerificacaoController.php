<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Documento;
use Inertia\Inertia;

class VerificacaoController extends Controller
{
    public function verificar(string $token)
    {
        $documento = Documento::with(['tipoDocumental', 'autor', 'versaoAtual'])
            ->where('qr_code_token', $token)
            ->first();

        if (!$documento) {
            return Inertia::render('GED/Verificar', [
                'documento' => null,
                'valido'    => false,
            ]);
        }

        return Inertia::render('GED/Verificar', [
            'documento' => [
                'nome'            => $documento->nome,
                'tipo_documental' => $documento->tipoDocumental?->nome,
                'autor'           => $documento->autor?->name,
                'status'          => $documento->status,
                'classificacao'   => $documento->classificacao,
                'versao'          => $documento->versao_atual,
                'hash'            => $documento->versaoAtual?->hash_sha256,
                'criado_em'       => $documento->created_at?->format('d/m/Y H:i'),
                'atualizado_em'   => $documento->updated_at?->format('d/m/Y H:i'),
            ],
            'valido' => true,
        ]);
    }
}
