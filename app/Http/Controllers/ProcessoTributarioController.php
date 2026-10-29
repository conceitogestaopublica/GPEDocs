<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Integracoes\Tributario\Fabrica;
use App\Integracoes\Tributario\FalhaTributario;
use App\Integracoes\Tributario\Normalizado;
use App\Integracoes\Tributario\SistemaTributario;
use App\Integracoes\Tributario\Vinculos;
use App\Models\Processo\Processo;
use App\Models\Processo\VinculoTributario;
use App\Support\RequerimentoServidor;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * O processo e o sistema tributário (TR 4.48.22, 4.48.42, 4.49, 4.50): imóvel, guia, alvará,
 * habite-se, certidão, fiscalização — pelo adaptador configurado ou informados à mão.
 */
class ProcessoTributarioController extends Controller
{
    /** Dados do card na tela do processo. */
    public static function doProcesso(Processo $p): array
    {
        $sis = Fabrica::para($p->ug_id);

        return [
            'sistema' => $sis->nome(), 'recursos' => $sis->recursos(), 'nomes_recursos' => SistemaTributario::RECURSOS,
            'documentos' => Normalizado::DOCUMENTOS, 'tipos' => VinculoTributario::TIPOS,
            'pode' => Auth::user()?->super_admin || Auth::user()?->can('tributario.usar'),
            'vinculos' => VinculoTributario::where('processo_id', $p->id)->orderBy('id')->get()->map(fn (VinculoTributario $v) => [
                'id' => $v->id, 'tipo' => $v->tipo, 'tipo_rotulo' => VinculoTributario::TIPOS[$v->tipo] ?? $v->tipo, 'numero' => $v->numero,
                'id_externo' => $v->id_externo, 'situacao' => $v->situacao, 'valor' => $v->valor, 'vencimento' => $v->vencimento?->format('Y-m-d'),
                'dados' => $v->dados ?? [], 'manual' => $v->manual, 'anexo_id' => $v->anexo_id, 'em' => $v->created_at?->toIso8601String(),
            ])->values(),
        ];
    }

    public function imovel(Request $request, int $id)
    {
        $d = $request->validate(['inscricao' => ['required', 'string', 'max:60']]);

        return $this->executar($id, fn (Processo $p) => Vinculos::vincularImovel($p, $d['inscricao'], Auth::user()), 'Imóvel vinculado ao processo.');
    }

    public function guia(Request $request, int $id)
    {
        $d = $request->validate(['taxa' => ['required', 'string', 'max:60'], 'valor' => ['nullable', 'numeric', 'min:0'],
            'descricao' => ['nullable', 'string', 'max:300'], 'vencimento' => ['nullable', 'date']]);

        return $this->executar($id, fn (Processo $p) => Vinculos::emitirGuia($p, $d, Auth::user()), 'Guia emitida.');
    }

    public function documento(Request $request, int $id)
    {
        $d = $request->validate(['tipo' => ['required', 'in:' . implode(',', array_keys(Normalizado::DOCUMENTOS))],
            'area' => ['nullable', 'numeric', 'min:0'], 'responsavel_tecnico' => ['nullable', 'string', 'max:200'], 'registro_profissional' => ['nullable', 'string', 'max:60'],
            'observacao' => ['nullable', 'string', 'max:1000']]);
        $tipo = $d['tipo'];
        unset($d['tipo']);

        return $this->executar($id, fn (Processo $p) => Vinculos::solicitarDocumento($p, $tipo, array_filter($d, fn ($x) => $x !== null), Auth::user()),
            Normalizado::DOCUMENTOS[$tipo] . ' solicitado.');
    }

    public function fiscalizacao(Request $request, int $id)
    {
        $d = $request->validate(['motivo' => ['required', 'string', 'max:1000']]);

        return $this->executar($id, fn (Processo $p) => Vinculos::abrirFiscalizacao($p, $d['motivo'], Auth::user()), 'Fiscalização aberta no sistema tributário.');
    }

    public function atualizar(int $id, int $vinculo)
    {
        return $this->executar($id, fn (Processo $p) => Vinculos::atualizar(VinculoTributario::where('processo_id', $p->id)->findOrFail($vinculo), Auth::user()), 'Situação atualizada.');
    }

    /** Sem integração ou sem o recurso: o servidor informa e anexa o PDF. */
    public function manual(Request $request, int $id)
    {
        $d = $request->validate(['tipo' => ['required', 'in:' . implode(',', array_keys(VinculoTributario::TIPOS))], 'numero' => ['required', 'string', 'max:100'],
            'situacao' => ['nullable', 'string', 'max:30'], 'valor' => ['nullable', 'numeric', 'min:0'], 'vencimento' => ['nullable', 'date'],
            'observacao' => ['nullable', 'string', 'max:1000'], 'pdf' => ['nullable', 'file', 'mimes:pdf', 'max:20480']]);

        return $this->executar($id, fn (Processo $p) => Vinculos::registrarManual($p, $d['tipo'], array_diff_key($d, ['pdf' => 1, 'tipo' => 1]), $request->file('pdf'), Auth::user()),
            (VinculoTributario::TIPOS[$d['tipo']] ?? 'Registro') . ' informado.');
    }

    public function remover(int $id, int $vinculo)
    {
        return $this->executar($id, function (Processo $p) use ($vinculo) {
            VinculoTributario::where('processo_id', $p->id)->findOrFail($vinculo)->delete();
        }, 'Vínculo retirado do processo (o anexo continua).');
    }

    private function executar(int $id, \Closure $acao, string $ok)
    {
        abort_unless(Auth::user()->super_admin || Auth::user()->can('tributario.usar'), 403);
        $p = Processo::findOrFail($id);
        abort_unless(RequerimentoServidor::podeVer(Auth::user(), $p), 403);
        try {
            $acao($p);
        } catch (FalhaTributario $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', $ok);
    }
}