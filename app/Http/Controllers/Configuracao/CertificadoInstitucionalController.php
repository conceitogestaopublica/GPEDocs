<?php

declare(strict_types=1);

namespace App\Http\Controllers\Configuracao;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\CertificadoInstitucional;
use App\Models\Ug;
use App\Services\SeloInstitucionalService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;
use RuntimeException;

/**
 * Certificado da prefeitura (e-CNPJ A1) por UG — sela os PDFs assinados por link.
 * O .pfx e a senha são gravados cifrados; a tela só mostra os dados públicos.
 */
class CertificadoInstitucionalController extends Controller
{
    public function index(): Response
    {
        $certificados = CertificadoInstitucional::with('enviadoPor:id,name')->get()->keyBy('ug_id');

        $ugs = Ug::orderBy('codigo')->get(['id', 'codigo', 'nome'])->map(function (Ug $ug) use ($certificados) {
            $c = $certificados->get($ug->id);

            return [
                'id'          => $ug->id,
                'codigo'      => $ug->codigo,
                'nome'        => $ug->nome,
                'certificado' => $c ? [
                    'titular'     => $c->subject_cn,
                    'cnpj'        => $c->cnpj,
                    'emissor'     => $c->issuer_cn,
                    'valido_ate'  => $c->valido_ate?->format('d/m/Y'),
                    'icp_brasil'  => $c->icp_brasil,
                    'ativo'       => $c->ativo,
                    'vigente'     => $c->vigente(),
                    'enviado_por' => $c->enviadoPor?->name,
                    'enviado_em'  => $c->updated_at?->format('d/m/Y H:i'),
                ] : null,
            ];
        });

        return Inertia::render('Configuracao/CertificadoInstitucional/Index', [
            'ugs'          => $ugs,
            'aceita_teste' => app()->environment(['local', 'testing']),
        ]);
    }

    public function store(Request $request, SeloInstitucionalService $selos): RedirectResponse
    {
        $request->validate([
            'ug_id' => ['required', 'integer', 'exists:ugs,id'],
            'pfx'   => ['required', 'file', 'max:5120', 'extensions:pfx,p12'],
            'senha' => ['required', 'string'],
        ]);

        try {
            $cert = $selos->salvar(
                (int) $request->input('ug_id'),
                (string) file_get_contents($request->file('pfx')->getRealPath() ?: $request->file('pfx')->getPathname()),
                (string) $request->input('senha'),
                Auth::id(),
            );
        } catch (RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        AuditLog::create([
            'usuario_id' => Auth::id(),
            'acao'       => 'certificado_institucional',
            'detalhes'   => ['ug_id' => $cert->ug_id, 'titular' => $cert->subject_cn, 'cnpj' => $cert->cnpj,
                'thumbprint_sha256' => $cert->thumbprint_sha256, 'icp_brasil' => $cert->icp_brasil],
            'ip'         => $request->ip(),
            'user_agent' => $request->userAgent(),
        ]);

        return back()->with('success', "Certificado de {$cert->subject_cn} gravado. Os próximos PDFs assinados por link sairão selados.");
    }

    public function toggle(int $ugId): RedirectResponse
    {
        $cert = CertificadoInstitucional::where('ug_id', $ugId)->firstOrFail();
        $cert->update(['ativo' => ! $cert->ativo]);

        return back()->with('success', $cert->ativo ? 'Selo reativado.' : 'Selo desativado: os PDFs saem sem o selo da prefeitura.');
    }
}
