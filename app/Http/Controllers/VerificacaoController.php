<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Documento;
use App\Services\AssinaturaValidadorService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Throwable;

class VerificacaoController extends Controller
{
    public function verificar(string $token)
    {
        // Página pública: quem abre o QR pode estar logado em outra UG ou sem acesso ao
        // documento — a autenticidade não depende disso, então os escopos não se aplicam.
        $documento = Documento::withoutGlobalScopes(['ug', 'sigilo'])
            ->with(['tipoDocumental', 'autor', 'versaoAtual'])
            ->where('qr_code_token', $token)
            ->first();

        if (!$documento) {
            return Inertia::render('GED/Verificar', [
                'documento' => null,
                'valido'    => false,
            ]);
        }

        // Confidencial ou restrito: confirma a autenticidade sem expor o conteúdo descritivo.
        if ($documento->ehSigiloso()) {
            return Inertia::render('GED/Verificar', [
                'documento' => [
                    'sigiloso'      => true,
                    'classificacao' => $documento->classificacao,
                    'status'        => $documento->status,
                    'versao'        => $documento->versao_atual,
                    'hash'          => $documento->versaoAtual?->hash_sha256,
                    'criado_em'     => $documento->created_at?->format('d/m/Y H:i'),
                    'atualizado_em' => $documento->updated_at?->format('d/m/Y H:i'),
                ],
                'valido' => true,
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

    /**
     * Pagina de validacao de PDFs assinados — publica, sem auth.
     */
    public function validarPdfPagina()
    {
        return Inertia::render('GED/ValidarAssinatura', [
            'resultado' => null,
        ]);
    }

    /**
     * Recebe upload de PDF e devolve relatorio de validacao da(s) assinatura(s) ICP-Brasil.
     */
    public function validarPdf(Request $request, AssinaturaValidadorService $validador)
    {
        $request->validate([
            'pdf' => ['required', 'file', 'max:20480', 'mimes:pdf'],
        ]);

        try {
            $bytes = (string) file_get_contents($request->file('pdf')->getRealPath());
            $resultado = $validador->validar($bytes);
        } catch (Throwable $e) {
            return back()->with('error', 'Falha ao validar PDF: ' . $e->getMessage());
        }

        return Inertia::render('GED/ValidarAssinatura', [
            'resultado'    => $resultado,
            'arquivo_nome' => $request->file('pdf')->getClientOriginalName(),
        ]);
    }
}
