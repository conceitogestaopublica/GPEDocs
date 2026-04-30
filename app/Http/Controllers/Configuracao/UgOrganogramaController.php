<?php

declare(strict_types=1);

namespace App\Http\Controllers\Configuracao;

use App\Http\Controllers\Controller;
use App\Models\Ug;
use App\Models\UgOrganograma;
use App\Models\User;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class UgOrganogramaController extends Controller
{
    /**
     * Pagina de gestao do organograma de uma UG. Devolve a arvore inteira
     * (max 3 niveis) e a config de labels.
     */
    public function show(Ug $ug): Response
    {
        $arvore = UgOrganograma::with(['filhosRecursivos' => fn ($q) => $q->withCount('usuarios')])
            ->withCount('usuarios')
            ->where('ug_id', $ug->id)
            ->whereNull('parent_id')
            ->orderBy('codigo')
            ->orderBy('nome')
            ->get();

        return Inertia::render('Configuracao/Ugs/Organograma', [
            'ug'      => $ug->only(['id', 'codigo', 'nome', 'cnpj',
                                    'nivel_1_label', 'nivel_2_label', 'nivel_3_label']),
            'arvore'  => $arvore,
        ]);
    }

    /**
     * Tela dedicada de cadastro de no — pode ser nivel 1 (sem parent) ou
     * receber ?parent_id=N para criar como filho.
     */
    public function createNode(Request $request, Ug $ug): Response
    {
        $parentId = $request->integer('parent_id') ?: null;
        $parent = $parentId ? UgOrganograma::where('ug_id', $ug->id)->findOrFail($parentId) : null;
        $nivel = $parent ? ($parent->nivel + 1) : 1;

        if ($nivel > 3) {
            abort(422, 'Nao e possivel adicionar abaixo do nivel 3.');
        }

        return Inertia::render('Configuracao/Ugs/OrganogramaForm', [
            'ug'             => $ug->only(['id','codigo','nome','nivel_1_label','nivel_2_label','nivel_3_label']),
            'parent'         => $parent ? $parent->only(['id','nome','nivel']) : null,
            'nivel'          => $nivel,
            'node'           => null,
            'usuarios'       => $this->listarUsuariosResponsaveis(),
        ]);
    }

    public function editNode(Ug $ug, UgOrganograma $node): Response
    {
        $this->validarPertence($ug, $node);
        $node->load('parent:id,nome,nivel');

        return Inertia::render('Configuracao/Ugs/OrganogramaForm', [
            'ug'        => $ug->only(['id','codigo','nome','nivel_1_label','nivel_2_label','nivel_3_label']),
            'parent'    => $node->parent ? $node->parent->only(['id','nome','nivel']) : null,
            'nivel'     => $node->nivel,
            'node'      => [
                'id'                => $node->id,
                'codigo'            => $node->codigo,
                'nome'              => $node->nome,
                'ativo'             => $node->ativo,
                'dt_inicio'         => $node->dt_inicio?->format('Y-m-d'),
                'dt_encerramento'   => $node->dt_encerramento?->format('Y-m-d'),
                'tipo_orgao'        => $node->tipo_orgao,
                'tipo_fundo'        => $node->tipo_fundo,
                'codigo_tce'        => $node->codigo_tce,
                'suprimir_tce'      => (bool) $node->suprimir_tce,
                'responsavel_id'    => $node->responsavel_id,
                'protocolo_externo' => (bool) $node->protocolo_externo,
                'endereco_proprio'  => (bool) $node->endereco_proprio,
                'cep'               => $node->cep,
                'logradouro'        => $node->logradouro,
                'numero'            => $node->numero,
                'complemento'       => $node->complemento,
                'bairro'            => $node->bairro,
                'cidade'            => $node->cidade,
                'uf'                => $node->uf,
            ],
            'usuarios'  => $this->listarUsuariosResponsaveis(),
        ]);
    }

    private function listarUsuariosResponsaveis(): array
    {
        return User::where('tipo', 'interno')
            ->orderBy('name')
            ->get(['id', 'name', 'email'])
            ->toArray();
    }

    public function updateLabels(Request $request, Ug $ug)
    {
        $validated = $request->validate([
            'nivel_1_label' => ['required', 'string', 'max:60'],
            'nivel_2_label' => ['required', 'string', 'max:60'],
            'nivel_3_label' => ['required', 'string', 'max:60'],
        ]);

        $ug->update($validated);

        return back()->with('success', 'Labels do organograma atualizados.');
    }

    public function storeNode(Request $request, Ug $ug)
    {
        $validated = $this->validarNode($request, true);

        // Determina nivel a partir do parent
        $nivel = 1;
        if (! empty($validated['parent_id'])) {
            $parent = UgOrganograma::where('ug_id', $ug->id)->findOrFail($validated['parent_id']);
            if ($parent->nivel >= 3) {
                return back()->with('error', 'Não é possível adicionar mais um nível abaixo do nível 3.');
            }
            $nivel = $parent->nivel + 1;
        }

        UgOrganograma::create($this->montarPayloadNode($validated, $ug, $nivel));

        return redirect()->route('configuracoes.ugs.organograma', $ug->id)
            ->with('success', 'Unidade adicionada.');
    }

    public function updateNode(Request $request, Ug $ug, UgOrganograma $node)
    {
        $this->validarPertence($ug, $node);

        $validated = $this->validarNode($request, false);
        $node->update($this->montarPayloadNode($validated, $ug, $node->nivel, atualizando: true));

        return redirect()->route('configuracoes.ugs.organograma', $ug->id)
            ->with('success', 'Unidade atualizada.');
    }

    /**
     * Validacao comum para storeNode/updateNode.
     */
    private function validarNode(Request $request, bool $criando): array
    {
        $regras = [
            'codigo'            => ['nullable', 'string', 'max:20'],
            'nome'              => ['required', 'string', 'max:200'],
            'dt_inicio'         => ['nullable', 'date'],
            'dt_encerramento'   => ['nullable', 'date', 'after_or_equal:dt_inicio'],
            'tipo_orgao'        => ['nullable', 'string', 'max:50'],
            'tipo_fundo'        => ['nullable', 'string', 'max:50'],
            'codigo_tce'        => ['nullable', 'string', 'max:20'],
            'suprimir_tce'      => ['boolean'],
            'responsavel_id'    => ['nullable', 'integer', 'exists:users,id'],
            'protocolo_externo' => ['boolean'],
            'endereco_proprio'  => ['boolean'],
            'cep'               => ['nullable', 'string', 'max:9'],
            'logradouro'        => ['nullable', 'string', 'max:200'],
            'numero'            => ['nullable', 'string', 'max:20'],
            'complemento'       => ['nullable', 'string', 'max:100'],
            'bairro'            => ['nullable', 'string', 'max:100'],
            'cidade'            => ['nullable', 'string', 'max:100'],
            'uf'                => ['nullable', 'string', 'size:2'],
        ];
        if ($criando) {
            $regras['parent_id'] = ['nullable', 'integer', 'exists:ug_organograma,id'];
        }
        return $request->validate($regras);
    }

    /**
     * Monta o payload para create/update do no, garantindo que os campos de
     * endereco fiquem null quando o no esta herdando da UG.
     */
    private function montarPayloadNode(
        array $validated,
        Ug $ug,
        int $nivel,
        bool $atualizando = false,
    ): array {
        $proprio = (bool) ($validated['endereco_proprio'] ?? false);

        $base = [
            'codigo'            => $validated['codigo'] ?? null,
            'nome'              => $validated['nome'],
            'dt_inicio'         => $validated['dt_inicio'] ?? null,
            'dt_encerramento'   => $validated['dt_encerramento'] ?? null,
            'tipo_orgao'        => $nivel === 1 ? ($validated['tipo_orgao'] ?? null) : null,
            'tipo_fundo'        => $nivel === 2 ? ($validated['tipo_fundo'] ?? null) : null,
            'codigo_tce'        => $validated['codigo_tce'] ?? null,
            'suprimir_tce'      => (bool) ($validated['suprimir_tce'] ?? false),
            'responsavel_id'    => $validated['responsavel_id'] ?? null,
            'protocolo_externo' => $nivel === 3 ? (bool) ($validated['protocolo_externo'] ?? false) : false,
            'endereco_proprio'  => $proprio,
            'cep'         => $proprio ? ($validated['cep']         ?? null) : null,
            'logradouro'  => $proprio ? ($validated['logradouro']  ?? null) : null,
            'numero'      => $proprio ? ($validated['numero']      ?? null) : null,
            'complemento' => $proprio ? ($validated['complemento'] ?? null) : null,
            'bairro'      => $proprio ? ($validated['bairro']      ?? null) : null,
            'cidade'      => $proprio ? ($validated['cidade']      ?? null) : null,
            'uf'          => $proprio ? ($validated['uf']          ?? null) : null,
        ];

        if ($atualizando) {
            return $base;
        }

        return $base + [
            'ug_id'     => $ug->id,
            'parent_id' => $validated['parent_id'] ?? null,
            'nivel'     => $nivel,
            'ativo'     => true,
        ];
    }

    public function destroyNode(Ug $ug, UgOrganograma $node)
    {
        $this->validarPertence($ug, $node);

        // Bloqueia se tiver filhos ou usuarios vinculados
        $filhosCount = $node->filhos()->count();
        $usuariosCount = $node->usuarios()->count();

        if ($filhosCount > 0 || $usuariosCount > 0) {
            return back()->with('error', sprintf(
                'Não é possível excluir: %d sub-unidade(s) e %d usuário(s) vinculados. Use "Inativar".',
                $filhosCount,
                $usuariosCount,
            ));
        }

        $node->delete();
        return back()->with('success', 'Unidade excluída.');
    }

    public function toggleAtivoNode(Ug $ug, UgOrganograma $node)
    {
        $this->validarPertence($ug, $node);

        $node->update(['ativo' => ! $node->ativo]);

        return back()->with('success', 'Unidade ' . ($node->ativo ? 'reativada' : 'inativada') . '.');
    }

    private function validarPertence(Ug $ug, UgOrganograma $node): void
    {
        abort_if($node->ug_id !== $ug->id, 404);
    }
}
