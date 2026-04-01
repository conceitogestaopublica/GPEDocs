<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Fluxo;
use App\Models\FluxoEtapa;
use App\Models\FluxoInstancia;
use App\Models\Notificacao;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class FluxoController extends Controller
{
    public function index(): Response
    {
        $fluxos = Fluxo::with('criador')
            ->orderByDesc('updated_at')
            ->paginate(20);

        return Inertia::render('GED/Fluxos/Index', [
            'fluxos' => $fluxos,
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('GED/Fluxos/Builder');
    }

    public function edit($id): Response
    {
        $fluxo = Fluxo::findOrFail($id);

        return Inertia::render('GED/Fluxos/Builder', [
            'fluxo' => $fluxo,
        ]);
    }

    public function show($id): Response
    {
        $fluxo = Fluxo::with(['instancias.documento', 'criador'])->findOrFail($id);

        return Inertia::render('GED/Fluxos/Show', [
            'fluxo' => $fluxo,
        ]);
    }

    public function store(Request $request)
    {
        $request->validate([
            'nome'      => ['required', 'string', 'max:255'],
            'descricao' => ['nullable', 'string'],
            'definicao' => ['required', 'array'],
        ]);

        try {
            Fluxo::create([
                'nome'      => $request->input('nome'),
                'descricao' => $request->input('descricao'),
                'definicao' => $request->input('definicao'),
                'ativo'     => true,
                'criado_por'=> Auth::id(),
            ]);

            return redirect()->route('fluxos.index')->with('success', 'Fluxo criado com sucesso.');
        } catch (\Exception $e) {
            return redirect()->back()->with('error', 'Erro ao criar fluxo: ' . $e->getMessage());
        }
    }

    public function update(Request $request, $id)
    {
        $request->validate([
            'nome'      => ['required', 'string', 'max:255'],
            'descricao' => ['nullable', 'string'],
            'definicao' => ['required', 'array'],
            'ativo'     => ['nullable', 'boolean'],
        ]);

        try {
            $fluxo = Fluxo::findOrFail($id);

            $fluxo->update($request->only(['nome', 'descricao', 'definicao', 'ativo']));

            return redirect()->route('fluxos.index')->with('success', 'Fluxo atualizado com sucesso.');
        } catch (\Exception $e) {
            return redirect()->back()->with('error', 'Erro ao atualizar fluxo: ' . $e->getMessage());
        }
    }

    public function destroy($id)
    {
        try {
            $fluxo = Fluxo::findOrFail($id);

            $hasActiveInstances = FluxoInstancia::where('fluxo_id', $id)
                ->whereIn('status', ['pendente', 'em_andamento'])
                ->exists();

            if ($hasActiveInstances) {
                return redirect()->back()->with('error', 'Não é possível excluir um fluxo com instâncias ativas.');
            }

            $fluxo->delete();

            return redirect()->route('fluxos.index')->with('success', 'Fluxo excluído com sucesso.');
        } catch (\Exception $e) {
            return redirect()->back()->with('error', 'Erro ao excluir fluxo: ' . $e->getMessage());
        }
    }

    public function iniciar(Request $request, $id)
    {
        $request->validate([
            'documento_id' => ['required', 'integer', 'exists:ged_documentos,id'],
        ]);

        try {
            DB::beginTransaction();

            $fluxo = Fluxo::findOrFail($id);

            $instancia = FluxoInstancia::create([
                'fluxo_id'     => $fluxo->id,
                'documento_id' => $request->input('documento_id'),
                'status'       => 'pendente',
                'etapa_atual'  => 1,
                'iniciado_por' => Auth::id(),
            ]);

            // Create steps from workflow definition
            if (is_array($fluxo->definicao) && isset($fluxo->definicao['etapas'])) {
                foreach ($fluxo->definicao['etapas'] as $ordem => $etapaDefinicao) {
                    $etapa = FluxoEtapa::create([
                        'instancia_id'   => $instancia->id,
                        'nome'           => $etapaDefinicao['nome'] ?? 'Etapa ' . ($ordem + 1),
                        'tipo'           => $etapaDefinicao['tipo'] ?? 'aprovacao',
                        'ordem'          => $ordem + 1,
                        'responsavel_id' => $etapaDefinicao['responsavel_id'] ?? null,
                        'status'         => $ordem === 0 ? 'pendente' : 'aguardando',
                        'prazo'          => isset($etapaDefinicao['prazo_dias'])
                            ? now()->addDays((int) $etapaDefinicao['prazo_dias'])
                            : null,
                    ]);

                    // Notify the responsible user for the first step
                    if ($ordem === 0 && !empty($etapaDefinicao['responsavel_id'])) {
                        Notificacao::create([
                            'usuario_id'      => $etapaDefinicao['responsavel_id'],
                            'tipo'            => 'fluxo',
                            'titulo'          => 'Nova etapa pendente',
                            'mensagem'        => "Você tem uma nova etapa pendente no fluxo \"{$fluxo->nome}\".",
                            'referencia_tipo' => 'fluxo_etapa',
                            'referencia_id'   => $etapa->id,
                            'lida'            => false,
                        ]);
                    }
                }
            }

            DB::commit();

            return redirect()->back()->with('success', 'Fluxo iniciado com sucesso.');
        } catch (\Exception $e) {
            DB::rollBack();

            return redirect()->back()->with('error', 'Erro ao iniciar fluxo: ' . $e->getMessage());
        }
    }
}
