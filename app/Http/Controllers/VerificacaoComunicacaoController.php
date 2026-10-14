<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Processo\Circular;
use App\Models\Processo\Memorando;
use App\Models\Processo\Oficio;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Verificação pública (QR do PDF) de memorando, ofício e circular. Os PDFs já traziam o
 * QR para /memorandos|oficios|circulares/verificar/{token}, mas as rotas não existiam.
 * Confirma a emissão sem expor o conteúdo; memorando confidencial não mostra o assunto.
 */
class VerificacaoComunicacaoController extends Controller
{
    private const TIPOS = [
        'memorandos' => [Memorando::class, 'Memorando'],
        'oficios'    => [Oficio::class, 'Ofício'],
        'circulares' => [Circular::class, 'Circular'],
    ];

    public function verificar(string $tipo, string $token): Response
    {
        [$classe, $rotulo] = self::TIPOS[$tipo] ?? abort(404);

        // Pública: quem lê o QR pode estar logado em outra UG.
        $registro = $classe::withoutGlobalScope('ug')->with(['remetente:id,name', 'ug:id,nome'])
            ->where('qr_code_token', $token)->first();

        if (! $registro) {
            return Inertia::render('GED/VerificarComunicacao', ['valido' => false, 'tipo' => $rotulo]);
        }

        $confidencial = (bool) ($registro->confidencial ?? false);

        return Inertia::render('GED/VerificarComunicacao', [
            'valido' => true,
            'tipo'   => $rotulo,
            'comunicacao' => array_filter([
                'numero'       => $registro->numero,
                'assunto'      => $confidencial ? null : $registro->assunto,
                'confidencial' => $confidencial,
                'remetente'    => $registro->remetente?->name,
                'unidade'      => $registro->setor_origem,
                'orgao'        => $registro->ug?->nome,
                'emitido_em'   => ($registro->enviado_em ?? $registro->created_at)?->format('d/m/Y H:i'),
                'situacao'     => $registro->status,
            ], fn ($v) => $v !== null && $v !== ''),
        ]);
    }
}
