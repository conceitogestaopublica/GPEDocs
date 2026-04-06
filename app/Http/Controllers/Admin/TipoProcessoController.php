<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Processo\TipoEtapa;
use App\Models\Processo\TipoProcesso;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class TipoProcessoController extends Controller
{
    public function index(): Response
    {
        $tipos = TipoProcesso::withCount('processos')
            ->with('etapas')
            ->orderBy('nome')
            ->get();

        return Inertia::render('GED/Admin/TiposProcesso/Index', [
            'tipos' => $tipos,
        ]);
    }

    public function store(Request $request)
    {
        $request->validate([
            'nome'                    => ['required', 'string', 'max:150'],
            'sigla'                   => ['required', 'string', 'max:10'],
            'descricao'               => ['nullable', 'string'],
            'categoria'               => ['required', 'string'],
            'schema_formulario'       => ['nullable', 'array'],
            'templates_despacho'      => ['nullable', 'array'],
            'sla_padrao_horas'        => ['required', 'integer'],
            'etapas'                  => ['nullable', 'array'],
            'etapas.*.nome'           => ['required', 'string'],
            'etapas.*.tipo'           => ['required', 'string'],
            'etapas.*.setor_destino'  => ['nullable', 'string'],
            'etapas.*.sla_horas'      => ['nullable', 'integer'],
            'etapas.*.template_texto' => ['nullable', 'string'],
            'etapas.*.obrigatorio'    => ['nullable', 'boolean'],
            'etapas.*.ordem'          => ['nullable', 'integer'],
        ]);

        try {
            DB::beginTransaction();

            $tipo = TipoProcesso::create([
                'nome'              => $request->input('nome'),
                'sigla'             => $request->input('sigla'),
                'descricao'         => $request->input('descricao'),
                'categoria'         => $request->input('categoria'),
                'schema_formulario' => $request->input('schema_formulario'),
                'templates_despacho'=> $request->input('templates_despacho'),
                'sla_padrao_horas'  => $request->input('sla_padrao_horas'),
                'ativo'             => true,
                'criado_por'        => Auth::id(),
            ]);

            if ($request->filled('etapas')) {
                foreach ($request->input('etapas') as $index => $etapa) {
                    TipoEtapa::create([
                        'tipo_processo_id' => $tipo->id,
                        'nome'             => $etapa['nome'],
                        'tipo'             => $etapa['tipo'],
                        'setor_destino'    => $etapa['setor_destino'] ?? null,
                        'sla_horas'        => $etapa['sla_horas'] ?? null,
                        'template_texto'   => $etapa['template_texto'] ?? null,
                        'obrigatorio'      => $etapa['obrigatorio'] ?? false,
                        'ordem'            => $etapa['ordem'] ?? $index + 1,
                    ]);
                }
            }

            DB::commit();

            return redirect()->back()->with('success', 'Tipo de processo criado com sucesso.');
        } catch (\Exception $e) {
            DB::rollBack();

            return redirect()->back()->with('error', 'Erro ao criar tipo de processo: ' . $e->getMessage());
        }
    }

    public function update(Request $request, $id)
    {
        $request->validate([
            'nome'                    => ['required', 'string', 'max:150'],
            'sigla'                   => ['required', 'string', 'max:10'],
            'descricao'               => ['nullable', 'string'],
            'categoria'               => ['required', 'string'],
            'schema_formulario'       => ['nullable', 'array'],
            'templates_despacho'      => ['nullable', 'array'],
            'sla_padrao_horas'        => ['required', 'integer'],
            'etapas'                  => ['nullable', 'array'],
            'etapas.*.nome'           => ['required', 'string'],
            'etapas.*.tipo'           => ['required', 'string'],
            'etapas.*.setor_destino'  => ['nullable', 'string'],
            'etapas.*.sla_horas'      => ['nullable', 'integer'],
            'etapas.*.template_texto' => ['nullable', 'string'],
            'etapas.*.obrigatorio'    => ['nullable', 'boolean'],
            'etapas.*.ordem'          => ['nullable', 'integer'],
        ]);

        try {
            DB::beginTransaction();

            $tipo = TipoProcesso::findOrFail($id);
            $tipo->update([
                'nome'              => $request->input('nome'),
                'sigla'             => $request->input('sigla'),
                'descricao'         => $request->input('descricao'),
                'categoria'         => $request->input('categoria'),
                'schema_formulario' => $request->input('schema_formulario'),
                'templates_despacho'=> $request->input('templates_despacho'),
                'sla_padrao_horas'  => $request->input('sla_padrao_horas'),
            ]);

            $tipo->etapas()->delete();

            if ($request->filled('etapas')) {
                foreach ($request->input('etapas') as $index => $etapa) {
                    TipoEtapa::create([
                        'tipo_processo_id' => $tipo->id,
                        'nome'             => $etapa['nome'],
                        'tipo'             => $etapa['tipo'],
                        'setor_destino'    => $etapa['setor_destino'] ?? null,
                        'sla_horas'        => $etapa['sla_horas'] ?? null,
                        'template_texto'   => $etapa['template_texto'] ?? null,
                        'obrigatorio'      => $etapa['obrigatorio'] ?? false,
                        'ordem'            => $etapa['ordem'] ?? $index + 1,
                    ]);
                }
            }

            DB::commit();

            return redirect()->back()->with('success', 'Tipo de processo atualizado com sucesso.');
        } catch (\Exception $e) {
            DB::rollBack();

            return redirect()->back()->with('error', 'Erro ao atualizar tipo de processo: ' . $e->getMessage());
        }
    }

    public function destroy($id)
    {
        $tipo = TipoProcesso::withCount('processos')->findOrFail($id);

        if ($tipo->processos_count > 0) {
            return redirect()->back()->with('error', 'Nao e possivel excluir tipo com processos vinculados. Use a opcao inativar.');
        }

        $tipo->etapas()->delete();
        $tipo->delete();

        return redirect()->back()->with('success', 'Tipo de processo excluido com sucesso.');
    }

    public function toggleAtivo($id)
    {
        $tipo = TipoProcesso::findOrFail($id);
        $tipo->update(['ativo' => !$tipo->ativo]);

        $status = $tipo->ativo ? 'ativado' : 'inativado';

        return redirect()->back()->with('success', "Tipo de processo {$status} com sucesso.");
    }
}
