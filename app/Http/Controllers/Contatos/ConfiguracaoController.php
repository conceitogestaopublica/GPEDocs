<?php

declare(strict_types=1);

namespace App\Http\Controllers\Contatos;

use App\Http\Controllers\Controller;
use App\Support\Contatos;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;

/**
 * Configuração dos contatos: até dez campos complementares (nome, explicação, formato,
 * onde é pedido e obrigatoriedade — TR 4.21.5–9) e as regras de preenchimento por perfil (4.21.3).
 */
class ConfiguracaoController extends Controller
{
    public function index()
    {
        return Inertia::render('GED/Contatos/Configuracao', [
            'campos'   => DB::table('contato_campos')->orderBy('ordem')->orderBy('id')->get()->map(fn ($c) => array_merge((array) $c, [
                'opcoes' => $c->opcoes ? json_decode($c->opcoes, true) : [], 'perfis' => $c->perfis ? json_decode($c->perfis, true) : [],
            ])),
            'regras'   => Contatos::regras(),
            'tipos'    => Contatos::TIPOS,
            'basicos'  => Contatos::BASICOS,
            'formatos' => Contatos::FORMATOS,
            'maximo'   => Contatos::MAX_CAMPOS,
        ]);
    }

    public function salvarCampo(Request $request, ?int $id = null)
    {
        $d = $request->validate([
            'nome'        => ['required', 'string', 'max:80'],
            'explicacao'  => ['nullable', 'string', 'max:255'],
            'formato'     => ['required', Rule::in(array_keys(Contatos::FORMATOS))],
            'opcoes'      => ['nullable', 'array', 'max:50'],
            'opcoes.*'    => ['string', 'max:100'],
            'onde'        => ['required', 'in:interno,autocadastro,ambos'],
            'obrigatorio' => ['boolean'],
            'perfis'      => ['nullable', 'array'],
            'perfis.*'    => [Rule::in(array_keys(Contatos::TIPOS))],
            'ordem'       => ['nullable', 'integer', 'min:0', 'max:99'],
        ]);
        if (! $id && DB::table('contato_campos')->where('ativo', true)->count() >= Contatos::MAX_CAMPOS) {
            throw ValidationException::withMessages(['nome' => 'O limite é de ' . Contatos::MAX_CAMPOS . ' campos complementares ativos.']);
        }
        $opcoes = array_values(array_filter(array_map('trim', $d['opcoes'] ?? [])));
        if ($d['formato'] === 'lista' && count($opcoes) < 2) {
            throw ValidationException::withMessages(['opcoes' => 'Informe ao menos duas opções.']);
        }
        $dados = [
            'nome' => $d['nome'], 'explicacao' => $d['explicacao'] ?? null, 'formato' => $d['formato'],
            'opcoes' => $d['formato'] === 'lista' ? json_encode($opcoes, JSON_UNESCAPED_UNICODE) : null,
            'onde' => $d['onde'], 'obrigatorio' => (bool) ($d['obrigatorio'] ?? false),
            'perfis' => ! empty($d['perfis']) ? json_encode(array_values($d['perfis'])) : null,
            'ordem' => $d['ordem'] ?? 0, 'updated_at' => now(),
        ];
        $id
            ? DB::table('contato_campos')->where('id', $id)->update($dados)
            : DB::table('contato_campos')->insert($dados + ['ativo' => true, 'created_at' => now()]);

        return back()->with('success', $id ? 'Campo alterado.' : "Campo \"{$d['nome']}\" criado.");
    }

    /** Desativar guarda o que já foi preenchido; reativar respeita o limite. */
    public function alternarCampo(int $id)
    {
        $c = DB::table('contato_campos')->where('id', $id)->first() ?? abort(404);
        if (! $c->ativo && DB::table('contato_campos')->where('ativo', true)->count() >= Contatos::MAX_CAMPOS) {
            return back()->with('error', 'Já há ' . Contatos::MAX_CAMPOS . ' campos ativos. Desative outro antes.');
        }
        DB::table('contato_campos')->where('id', $id)->update(['ativo' => ! $c->ativo, 'updated_at' => now()]);

        return back()->with('success', $c->ativo ? 'Campo desativado. O que já foi preenchido fica guardado.' : 'Campo reativado.');
    }

    public function regras(Request $request)
    {
        $d = $request->validate([
            'regras'   => ['required', 'array'],
            'regras.*' => ['array'],
            'regras.*.*' => [Rule::in(array_keys(Contatos::BASICOS))],
        ]);
        foreach (Contatos::TIPOS as $tipo => $_) {
            DB::table('contato_regras')->updateOrInsert(['tipo' => $tipo], [
                'obrigatorios' => json_encode(array_values($d['regras'][$tipo] ?? [])), 'updated_at' => now(), 'created_at' => now(),
            ]);
        }

        return back()->with('success', 'Regras de preenchimento salvas. Valem para os próximos cadastros e alterações.');
    }
}
