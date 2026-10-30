<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Jobs\ClassificarRetroativo;
use App\Support\Classificacao;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Plano de classificação (TR 4.27): quatro níveis com código único, suspender sem tirar
 * dos itens já classificados, classificação padrão por módulo e por assunto, importação
 * CSV com conferência antes de gravar, exportação e classificação retroativa.
 */
class ClassificacaoController extends Controller
{
    public function index()
    {
        $uso = [];
        foreach (Classificacao::MODULOS as [$tabela]) {
            foreach (DB::table($tabela)->whereNotNull('classificacao_id')->groupBy('classificacao_id')
                ->selectRaw('classificacao_id, count(*) as n')->pluck('n', 'classificacao_id') as $id => $n) {
                $uso[$id] = ($uso[$id] ?? 0) + (int) $n;
            }
        }

        return Inertia::render('GED/Admin/Classificacao/Index', [
            'classificacoes' => DB::table('ged_classificacoes')->orderBy('codigo')->get()
                ->map(fn ($c) => (array) $c + ['uso' => $uso[$c->id] ?? 0])->values(),
            'modulos'        => collect(Classificacao::MODULOS)->map(fn ($m, $k) => [
                'modulo' => $k, 'rotulo' => $m[3],
                'classificacao_id' => DB::table('ged_classificacao_modulos')->where('modulo', $k)->value('classificacao_id'),
            ])->values(),
            'assuntos'       => [
                'processo'  => DB::table('proc_tipos_processo')->where('ativo', true)->orderBy('nome')->get(['id', 'nome', 'classificacao_id']),
                'documento' => DB::table('ged_tipos_documentais')->where('ativo', true)->orderBy('nome')->get(['id', 'nome', 'classificacao_id']),
            ],
            'pendentes'      => Classificacao::pendentes(),
            'conferencia'    => session('conferencia'),
        ]);
    }

    public function store(Request $request)
    {
        $dados = $this->validar($request);
        DB::table('ged_classificacoes')->insert($dados + ['ativo' => true, 'created_at' => now(), 'updated_at' => now()]);

        return back()->with('success', "Classificação {$dados['codigo']} criada.");
    }

    public function update(Request $request, int $id)
    {
        $dados = $this->validar($request, $id);
        DB::table('ged_classificacoes')->where('id', $id)->update($dados + ['updated_at' => now()]);
        // Mudou de nível: as de baixo acompanham
        $this->renivelar($id, $dados['nivel']);

        return back()->with('success', 'Classificação alterada.');
    }

    /** Suspender (TR 4.27.4) ou reativar. Suspensa some dos selects, mas fica nos itens. */
    public function alternar(int $id)
    {
        $c = DB::table('ged_classificacoes')->where('id', $id)->first() ?? abort(404);
        DB::table('ged_classificacoes')->where('id', $id)->update(['ativo' => ! $c->ativo, 'updated_at' => now()]);

        return back()->with('success', $c->ativo
            ? "Código {$c->codigo} suspenso. Os itens já classificados nele continuam com ele."
            : "Código {$c->codigo} reativado.");
    }

    public function destroy(int $id)
    {
        $c = DB::table('ged_classificacoes')->where('id', $id)->first() ?? abort(404);
        if (DB::table('ged_classificacoes')->where('parent_id', $id)->exists()) {
            return back()->with('error', "O código {$c->codigo} tem classificações abaixo dele.");
        }
        foreach (Classificacao::MODULOS as [$tabela]) {
            if (DB::table($tabela)->where('classificacao_id', $id)->exists()) {
                return back()->with('error', "O código {$c->codigo} já está em uso. Suspenda em vez de excluir.");
            }
        }
        DB::table('ged_classificacoes')->where('id', $id)->delete();

        return back()->with('success', "Código {$c->codigo} excluído.");
    }

    /** Classificação padrão de cada módulo e de cada assunto (TR 4.27.6–7). */
    public function padroes(Request $request)
    {
        $dados = $request->validate([
            'modulos'              => ['array'],
            'modulos.*'            => ['nullable', 'integer', 'exists:ged_classificacoes,id'],
            'assuntos'             => ['array'],
            'assuntos.processo'    => ['array'],
            'assuntos.processo.*'  => ['nullable', 'integer', 'exists:ged_classificacoes,id'],
            'assuntos.documento'   => ['array'],
            'assuntos.documento.*' => ['nullable', 'integer', 'exists:ged_classificacoes,id'],
        ]);

        DB::transaction(function () use ($dados) {
            foreach ($dados['modulos'] ?? [] as $modulo => $id) {
                if (! isset(Classificacao::MODULOS[$modulo])) {
                    continue;
                }
                $id
                    ? DB::table('ged_classificacao_modulos')->updateOrInsert(['modulo' => $modulo], ['classificacao_id' => $id, 'updated_at' => now(), 'created_at' => now()])
                    : DB::table('ged_classificacao_modulos')->where('modulo', $modulo)->delete();
            }
            foreach (['processo' => 'proc_tipos_processo', 'documento' => 'ged_tipos_documentais'] as $k => $tabela) {
                foreach ($dados['assuntos'][$k] ?? [] as $tipoId => $id) {
                    DB::table($tabela)->where('id', (int) $tipoId)->update(['classificacao_id' => $id ?: null]);
                }
            }
        });

        return back()->with('success', 'Classificação automática salva. Vale para os itens criados daqui em diante.');
    }

    /** Classificar os itens antigos sem classificação, em segundo plano (TR 4.27.10). */
    public function retroativa()
    {
        ClassificarRetroativo::dispatch();

        return back()->with('success', 'A classificação dos itens antigos começou. Atualize a página em alguns instantes.');
    }

    /** CSV codigo;descricao;codigo_superior — confere tudo e mostra antes de gravar (TR 4.27.5). */
    public function conferir(Request $request)
    {
        $request->validate(['arquivo' => ['required', 'file', 'mimes:csv,txt', 'max:2048']]);
        $linhas = $this->lerCsv($request->file('arquivo')->getRealPath());
        $resultado = $this->analisar($linhas);
        $token = Str::uuid()->toString();
        Cache::put("classificacao-importacao:{$token}", $resultado['itens'], now()->addMinutes(30));

        return back()->with('conferencia', ['token' => $token] + $resultado);
    }

    public function importar(Request $request)
    {
        $token = $request->validate(['token' => ['required', 'uuid']])['token'];
        $itens = Cache::pull("classificacao-importacao:{$token}") ?? throw ValidationException::withMessages(['arquivo' => 'A conferência expirou. Envie o arquivo de novo.']);
        if (collect($itens)->contains(fn ($i) => $i['erro'])) {
            throw ValidationException::withMessages(['arquivo' => 'O arquivo tem erros. Corrija e envie de novo.']);
        }

        $novos = 0;
        $alterados = 0;
        DB::transaction(function () use ($itens, &$novos, &$alterados) {
            // Em ordem de nível: o superior já existe quando o filho chega
            foreach (collect($itens)->sortBy('nivel') as $i) {
                $pai = $i['superior'] ? DB::table('ged_classificacoes')->where('codigo', $i['superior'])->value('id') : null;
                $existe = DB::table('ged_classificacoes')->where('codigo', $i['codigo'])->value('id');
                $dados = ['descricao' => $i['descricao'], 'parent_id' => $pai, 'nivel' => $i['nivel'], 'updated_at' => now()];
                if ($existe) {
                    DB::table('ged_classificacoes')->where('id', $existe)->update($dados);
                    $alterados++;
                } else {
                    DB::table('ged_classificacoes')->insert($dados + ['codigo' => $i['codigo'], 'ativo' => true, 'created_at' => now()]);
                    $novos++;
                }
            }
        });

        return back()->with('success', "Plano importado: {$novos} código(s) novo(s) e {$alterados} atualizado(s).");
    }

    public function exportar(): StreamedResponse
    {
        $todas = DB::table('ged_classificacoes')->get(['id', 'codigo', 'parent_id'])->keyBy('id');

        return response()->streamDownload(function () use ($todas) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF");
            fputcsv($out, ['codigo', 'descricao', 'codigo_superior', 'situacao'], ';');
            foreach (Classificacao::opcoes(false) as $c) {
                $paiId = $todas[$c['id']]->parent_id;
                fputcsv($out, [$c['codigo'], $c['descricao'], $paiId ? $todas[$paiId]->codigo : '', $c['ativo'] ? 'ativa' : 'suspensa'], ';');
            }
            fclose($out);
        }, 'plano-de-classificacao.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    /** Reclassificar um item (TR 4.27.8): processo, memorando, ofício, circular ou documento. */
    public function reclassificar(Request $request, string $modulo, int $id)
    {
        abort_unless(isset(Classificacao::MODULOS[$modulo]), 404);
        $dados = $request->validate([
            'classificacao_id' => ['required', 'integer', Rule::exists('ged_classificacoes', 'id')->where('ativo', true)],
            'motivo'           => ['required', 'string', 'max:1000'],
        ]);
        // O item tem de estar ao alcance de quem reclassifica (UG da sessão e sigilo)
        $model = [
            'processo' => \App\Models\Processo\Processo::class, 'memorando' => \App\Models\Processo\Memorando::class,
            'oficio' => \App\Models\Processo\Oficio::class, 'circular' => \App\Models\Processo\Circular::class,
            'documento' => \App\Models\Documento::class,
        ][$modulo];
        $model::findOrFail($id);

        $mudou = Classificacao::reclassificar($modulo, $id, (int) $dados['classificacao_id'], $dados['motivo']);

        return back()->with($mudou ? 'success' : 'error', $mudou ? 'Reclassificado.' : 'O item já está nessa classificação.');
    }

    private function validar(Request $request, ?int $id = null): array
    {
        $dados = $request->validate([
            'codigo'     => ['required', 'string', 'max:30', 'regex:/^[0-9][0-9.\-\/]*$/', Rule::unique('ged_classificacoes', 'codigo')->ignore($id)],
            'descricao'  => ['required', 'string', 'max:255'],
            'observacao' => ['nullable', 'string', 'max:2000'],
            'parent_id'  => ['nullable', 'integer', 'exists:ged_classificacoes,id'],
        ], ['codigo.regex' => 'Use só números, pontos, hífen ou barra (ex.: 01.02.03).']);

        $nivel = 1;
        if ($dados['parent_id'] ?? null) {
            if ($id && in_array((int) $dados['parent_id'], Classificacao::comDescendentes($id), true)) {
                throw ValidationException::withMessages(['parent_id' => 'Uma classificação não pode ficar abaixo dela mesma.']);
            }
            $nivel = (int) DB::table('ged_classificacoes')->where('id', $dados['parent_id'])->value('nivel') + 1;
        }
        $profundidade = $id ? $this->profundidadeAbaixo($id) : 0;
        if ($nivel + $profundidade > Classificacao::NIVEIS) {
            throw ValidationException::withMessages(['parent_id' => 'O plano tem no máximo quatro níveis.']);
        }

        return ['codigo' => $dados['codigo'], 'descricao' => $dados['descricao'], 'observacao' => $dados['observacao'] ?? null,
            'parent_id' => $dados['parent_id'] ?? null, 'nivel' => $nivel];
    }

    private function profundidadeAbaixo(int $id): int
    {
        $n = 0;
        $fronteira = [$id];
        while ($fronteira = DB::table('ged_classificacoes')->whereIn('parent_id', $fronteira)->pluck('id')->all()) {
            $n++;
        }

        return $n;
    }

    private function renivelar(int $id, int $nivel): void
    {
        foreach (DB::table('ged_classificacoes')->where('parent_id', $id)->pluck('id') as $filho) {
            DB::table('ged_classificacoes')->where('id', $filho)->update(['nivel' => $nivel + 1]);
            $this->renivelar((int) $filho, $nivel + 1);
        }
    }

    /** @return list<array{linha:int, codigo:string, descricao:string, superior:string}> */
    private function lerCsv(string $caminho): array
    {
        $conteudo = (string) file_get_contents($caminho);
        $conteudo = preg_replace('/^\xEF\xBB\xBF/', '', $conteudo);
        if (! mb_check_encoding($conteudo, 'UTF-8')) {
            $conteudo = mb_convert_encoding($conteudo, 'UTF-8', 'Windows-1252');   // CSV salvo pelo Excel
        }
        $linhas = preg_split('/\r\n|\r|\n/', trim($conteudo));
        $sep = substr_count($linhas[0] ?? '', ';') >= substr_count($linhas[0] ?? '', ',') ? ';' : ',';
        $saida = [];
        foreach ($linhas as $n => $linha) {
            $col = array_map('trim', str_getcsv($linha, $sep));
            if ($n === 0 && Str::lower($col[0] ?? '') === 'codigo') {
                continue;   // cabeçalho
            }
            if (implode('', $col) === '') {
                continue;
            }
            $saida[] = ['linha' => $n + 1, 'codigo' => $col[0] ?? '', 'descricao' => $col[1] ?? '', 'superior' => $col[2] ?? ''];
        }

        return $saida;
    }

    /** Confere o arquivo inteiro: código, descrição, superior existente, sem repetição, até quatro níveis. */
    private function analisar(array $linhas): array
    {
        $noArquivo = collect($linhas)->keyBy('codigo');
        $noBanco = DB::table('ged_classificacoes')->get(['codigo', 'parent_id', 'id', 'nivel', 'descricao'])->keyBy('codigo');
        $codigoPorId = $noBanco->pluck('codigo', 'id');
        $vistos = [];
        $itens = [];

        $superiorDe = function (string $codigo) use ($noArquivo, $noBanco, $codigoPorId): ?string {
            if ($noArquivo->has($codigo)) {
                return $noArquivo[$codigo]['superior'] ?: null;
            }
            $pai = $noBanco[$codigo]->parent_id ?? null;

            return $pai ? $codigoPorId[$pai] : null;
        };

        foreach ($linhas as $l) {
            $erro = null;
            if ($l['codigo'] === '' || ! preg_match('/^[0-9][0-9.\-\/]*$/', $l['codigo'])) {
                $erro = 'Código vazio ou com caracteres inválidos';
            } elseif (isset($vistos[$l['codigo']])) {
                $erro = "Código repetido (já está na linha {$vistos[$l['codigo']]})";
            } elseif ($l['descricao'] === '') {
                $erro = 'Sem descrição';
            } elseif ($l['superior'] !== '' && ! $noArquivo->has($l['superior']) && ! $noBanco->has($l['superior'])) {
                $erro = "Código superior {$l['superior']} não existe no arquivo nem no plano";
            }
            $vistos[$l['codigo']] = $l['linha'];

            // Nível pela cadeia de superiores (detecta ciclo)
            $nivel = 1;
            if (! $erro) {
                $atual = $l['superior'] ?: null;
                $cadeia = [$l['codigo'] => true];
                while ($atual) {
                    if (isset($cadeia[$atual])) {
                        $erro = 'Os códigos superiores formam um ciclo';
                        break;
                    }
                    $cadeia[$atual] = true;
                    $nivel++;
                    $atual = $superiorDe($atual);
                }
                if (! $erro && $nivel > Classificacao::NIVEIS) {
                    $erro = "Fica no {$nivel}º nível (o plano tem no máximo quatro)";
                }
            }

            $itens[] = $l + ['nivel' => $nivel, 'erro' => $erro, 'acao' => $noBanco->has($l['codigo']) ? 'atualiza' : 'novo'];
        }

        return [
            'itens'     => $itens,
            'novos'     => collect($itens)->where('acao', 'novo')->whereNull('erro')->count(),
            'atualiza'  => collect($itens)->where('acao', 'atualiza')->whereNull('erro')->count(),
            'erros'     => collect($itens)->whereNotNull('erro')->count(),
        ];
    }
}
