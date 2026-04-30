<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Certificado;
use App\Services\CertificadoService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;
use Throwable;

class CertificadoController extends Controller
{
    /**
     * Lista os certificados do usuario logado com contagem de assinaturas vinculadas.
     */
    public function index(): Response
    {
        $certs = Certificado::where('user_id', Auth::id())
            ->withCount('assinaturas')
            ->orderByRaw('revogado asc, valido_ate desc')
            ->get()
            ->map(fn (Certificado $c) => [
                'id'                => $c->id,
                'tipo'              => $c->tipo,
                'subject_cn'        => $c->subject_cn,
                'subject_cpf'       => $c->subject_cpf,
                'subject_dn'        => $c->subject_dn,
                'issuer_cn'         => $c->issuer_cn,
                'issuer_dn'         => $c->issuer_dn,
                'serial_number'     => $c->serial_number,
                'thumbprint_sha256' => $c->thumbprint_sha256,
                'valido_de'         => $c->valido_de?->format('Y-m-d'),
                'valido_ate'        => $c->valido_ate?->format('Y-m-d'),
                'icp_brasil'        => (bool) $c->icp_brasil,
                'revogado'          => (bool) $c->revogado,
                'verificado_em'     => $c->verificado_em?->format('Y-m-d H:i'),
                'assinaturas_count' => $c->assinaturas_count,
                'expirado'          => $c->valido_ate && $c->valido_ate->isPast(),
                'dias_para_expirar' => $c->valido_ate ? (int) now()->diffInDays($c->valido_ate, false) : null,
            ])
            ->values();

        return Inertia::render('GED/Perfil/MeusCertificados', [
            'certificados' => $certs,
            'usuario_cpf'  => Auth::user()->cpf,
        ]);
    }

    /**
     * Cadastra um novo certificado a partir de upload de .pfx/.p12.
     * O servidor abre o PFX em memoria, valida e armazena APENAS o cert publico.
     * A senha e a chave privada sao descartadas imediatamente.
     */
    public function store(Request $request, CertificadoService $svc)
    {
        $request->validate([
            'pfx'   => ['required', 'file', 'max:5120', 'extensions:pfx,p12'],
            'senha' => ['required', 'string'],
        ]);

        $pfxBin = (string) file_get_contents($request->file('pfx')->getRealPath());
        $senha  = (string) $request->input('senha');

        try {
            $material = $svc->abrirPfx($pfxBin, $senha);

            $validacao = $svc->validarParaUso(Auth::user(), $material['cert'], $material['extracerts']);

            if (! $validacao['ok']) {
                return back()->with('error', 'Certificado recusado: ' . implode(' ', $validacao['erros']));
            }

            // Verifica se ja existe (mesmo thumbprint para o mesmo user)
            $thumbprint = $validacao['meta']['thumbprint_sha256'];
            $jaExiste = Certificado::where('user_id', Auth::id())
                ->where('thumbprint_sha256', $thumbprint)
                ->first();

            if ($jaExiste && ! $jaExiste->revogado) {
                return back()->with('info', 'Este certificado ja estava cadastrado (thumbprint identico).');
            }

            $svc->registrarParaUsuario(Auth::user(), $material['cert'], $material['extracerts']);

            // Se estava revogado, reativa
            if ($jaExiste && $jaExiste->revogado) {
                $jaExiste->update(['revogado' => false]);
                return back()->with('success', 'Certificado reativado a partir do mesmo PFX.');
            }

            return back()->with('success', sprintf(
                'Certificado %s cadastrado com sucesso (valido ate %s).',
                $validacao['meta']['subject_cn'],
                date('d/m/Y', strtotime($validacao['meta']['valido_ate'])),
            ));
        } catch (Throwable $e) {
            return back()->with('error', 'Falha ao processar certificado: ' . $e->getMessage());
        } finally {
            // Apaga material sensivel (best-effort)
            $pfxBin = str_repeat("\0", strlen($pfxBin));
            $senha  = str_repeat("\0", strlen($senha));
            unset($pfxBin, $senha, $material);
        }
    }

    /**
     * Marca o certificado como inativo (flag revogado=true). Nao deleta o
     * registro porque assinaturas passadas continuam vinculadas a ele.
     */
    public function inativar($id)
    {
        $cert = Certificado::where('user_id', Auth::id())->findOrFail($id);
        $cert->update(['revogado' => true]);

        return back()->with('success', 'Certificado marcado como inativo. Assinaturas anteriores nao sao afetadas.');
    }

    /**
     * Reativa um certificado previamente inativado.
     */
    public function reativar($id)
    {
        $cert = Certificado::where('user_id', Auth::id())->findOrFail($id);
        $cert->update(['revogado' => false]);

        return back()->with('success', 'Certificado reativado.');
    }
}
