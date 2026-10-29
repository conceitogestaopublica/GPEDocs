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
            'tipos'    => $tipos,
            // Setores das etapas: o tipo de processo vale para todas as UGs do município
            'unidades' => \App\Models\UgOrganograma::query()
                ->join('ugs', 'ugs.id', '=', 'ug_organograma.ug_id')
                ->where('ug_organograma.ativo', true)
                ->orderBy('ugs.codigo')->orderBy('ug_organograma.nivel')->orderBy('ug_organograma.nome')
                ->get(['ug_organograma.id', 'ug_organograma.nome', 'ug_organograma.nivel', 'ug_organograma.parent_id', 'ugs.codigo as ug']),
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
            'anexos_exigidos'               => ['nullable', 'array', 'max:30'],
            'anexos_exigidos.*.nome'        => ['required', 'string', 'max:150'],
            'anexos_exigidos.*.obrigatorio' => ['nullable', 'boolean'],
            'anexos_exigidos.*.extensoes'   => ['nullable', 'string', 'max:100', 'regex:/^[a-z0-9, ]*$/i'],
            'sla_padrao_horas'        => ['required', 'integer'],
            'prazo_dias_uteis'        => ['nullable', 'boolean'],
            'localizacao'             => ['nullable', 'in:nao,opcional,obrigatoria'],
            'avaliacao'               => ['nullable', 'boolean'],
            'etapas'                  => ['nullable', 'array'],
            'etapas.*.nome'           => ['required', 'string'],
            'etapas.*.tipo'           => ['required', 'string'],
            'etapas.*.setor_destino'  => ['nullable', 'string'],
            'etapas.*.unidades'       => ['nullable', 'array'],
            'etapas.*.unidades.*'     => ['integer', 'exists:ug_organograma,id'],
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
                'anexos_exigidos'   => $request->input('anexos_exigidos') ?: null,
                'sla_padrao_horas'  => $request->input('sla_padrao_horas'),
                'prazo_dias_uteis'  => $request->boolean('prazo_dias_uteis'),
                'localizacao'       => $request->input('localizacao', 'nao'),
                'avaliacao'         => $request->boolean('avaliacao', true),
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
                        'unidades'         => array_values(array_map('intval', $etapa['unidades'] ?? [])) ?: null,
                        'sla_horas'        => $etapa['sla_horas'] ?? null,
                        'template_texto'   => $etapa['template_texto'] ?? null,
                        'obrigatorio'      => $etapa['obrigatorio'] ?? true,  // sem pular etapas (TR 4.38.19)
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
            'anexos_exigidos'               => ['nullable', 'array', 'max:30'],
            'anexos_exigidos.*.nome'        => ['required', 'string', 'max:150'],
            'anexos_exigidos.*.obrigatorio' => ['nullable', 'boolean'],
            'anexos_exigidos.*.extensoes'   => ['nullable', 'string', 'max:100', 'regex:/^[a-z0-9, ]*$/i'],
            'sla_padrao_horas'        => ['required', 'integer'],
            'prazo_dias_uteis'        => ['nullable', 'boolean'],
            'localizacao'             => ['nullable', 'in:nao,opcional,obrigatoria'],
            'avaliacao'               => ['nullable', 'boolean'],
            'etapas'                  => ['nullable', 'array'],
            'etapas.*.id'             => ['nullable', 'integer'],
            'etapas.*.nome'           => ['required', 'string'],
            'etapas.*.tipo'           => ['required', 'string'],
            'etapas.*.setor_destino'  => ['nullable', 'string'],
            'etapas.*.unidades'       => ['nullable', 'array'],
            'etapas.*.unidades.*'     => ['integer', 'exists:ug_organograma,id'],
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
                'anexos_exigidos'   => $request->input('anexos_exigidos') ?: null,
                'sla_padrao_horas'  => $request->input('sla_padrao_horas'),
                'prazo_dias_uteis'  => $request->boolean('prazo_dias_uteis'),
                'localizacao'       => $request->input('localizacao', 'nao'),
                'avaliacao'         => $request->boolean('avaliacao', true),
            ]);

            // Sincroniza pelo id em vez de apagar e recriar: as tramitações guardam
            // tipo_etapa_id, e o delete falhava por chave estrangeira em tipo já em uso.
            $existentes = $tipo->todasEtapas()->get()->keyBy('id');
            $mantidas = [];

            foreach ((array) $request->input('etapas', []) as $index => $etapa) {
                $dados = [
                    'nome'           => $etapa['nome'],
                    'tipo'           => $etapa['tipo'],
                    'setor_destino'  => $etapa['setor_destino'] ?? null,
                    'unidades'       => array_values(array_map('intval', $etapa['unidades'] ?? [])) ?: null,
                    'sla_horas'      => $etapa['sla_horas'] ?? null,
                    'template_texto' => $etapa['template_texto'] ?? null,
                    'obrigatorio'    => $etapa['obrigatorio'] ?? true,  // sem pular etapas (TR 4.38.19)
                    'ordem'          => $etapa['ordem'] ?? $index + 1,
                    'ativo'          => true,
                ];

                $atual = isset($etapa['id']) ? $existentes->get((int) $etapa['id']) : null;
                if ($atual) {
                    $atual->update($dados);
                    $mantidas[] = $atual->id;
                } else {
                    $mantidas[] = TipoEtapa::create($dados + ['tipo_processo_id' => $tipo->id])->id;
                }
            }

            // Retiradas: sem tramitação, apaga; com tramitação, desativa (o histórico aponta para ela).
            foreach ($existentes->except($mantidas) as $retirada) {
                $emUso = DB::table('proc_tramitacoes')->where('tipo_etapa_id', $retirada->id)->exists();
                $emUso ? $retirada->update(['ativo' => false]) : $retirada->delete();
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
            return redirect()->back()->with('error', 'Não é possível excluir tipo com processos vinculados. Use a opção inativar.');
        }

        $tipo->etapas()->delete();
        $tipo->delete();

        return redirect()->back()->with('success', 'Tipo de processo excluído com sucesso.');
    }

    /** Imagem de ajuda de um campo do formulário (TR 4.38.6). Devolve o endereço para o campo. */
    public function enviarImagemAjuda(Request $request)
    {
        $request->validate(['imagem' => ['required', 'image', 'mimes:png,jpg,jpeg,webp,gif', 'max:2048']]);
        $id = (string) \Illuminate\Support\Str::uuid();
        $ext = strtolower($request->file('imagem')->extension() ?: 'png');
        $request->file('imagem')->storeAs(\App\Tenant\TenantStorage::pasta('ajuda-campos'), "{$id}.{$ext}", 'documentos');

        // Endereço sem extensão: o nginx serve *.png etc. como arquivo estático e nem chega aqui
        return response()->json(['url' => route('processos.ajuda-imagem', $id, false)]);
    }

    public function imagemAjuda(string $id)
    {
        $disco = \Illuminate\Support\Facades\Storage::disk('documentos');
        foreach (['png', 'jpg', 'jpeg', 'webp', 'gif'] as $ext) {
            $caminho = \App\Tenant\TenantStorage::pasta('ajuda-campos') . "/{$id}.{$ext}";
            if ($disco->exists($caminho)) {
                return $disco->response($caminho, "ajuda.{$ext}", ['Cache-Control' => 'private, max-age=86400']);
            }
        }
        abort(404);
    }

    public function toggleAtivo($id)
    {
        $tipo = TipoProcesso::findOrFail($id);
        $tipo->update(['ativo' => !$tipo->ativo]);

        $status = $tipo->ativo ? 'ativado' : 'inativado';

        return redirect()->back()->with('success', "Tipo de processo {$status} com sucesso.");
    }
}
