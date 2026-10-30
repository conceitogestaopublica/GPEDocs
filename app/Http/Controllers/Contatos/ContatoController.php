<?php

declare(strict_types=1);

namespace App\Http\Controllers\Contatos;

use App\Http\Controllers\Controller;
use App\Support\Contatos;
use App\Support\DocumentoPessoa;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Contatos (TR 4.21): consulta e cadastro da base única, listas, procurações,
 * unificação de duplicados e CSV. A configuração (campos e regras) fica em ConfiguracaoController.
 */
class ContatoController extends Controller
{
    public function index(Request $request)
    {
        $f = [
            'q'     => trim((string) $request->input('q', '')),
            'tipo'  => array_key_exists((string) $request->input('tipo'), Contatos::TIPOS) ? $request->input('tipo') : '',
            'lista' => $request->integer('lista') ?: null,
            'aba'   => $request->input('aba') === 'duplicados' ? 'duplicados' : 'todos',
        ];

        $contatos = $this->consulta($f)->paginate(30)->withQueryString()
            ->through(fn ($c) => $this->linha($c));

        return Inertia::render('GED/Contatos/Index', [
            'contatos'   => $contatos,
            'filtros'    => $f,
            'tipos'      => Contatos::TIPOS,
            'listas'     => DB::table('contato_listas')->orderBy('nome')->get()->map(fn ($l) => (array) $l + [
                'total' => DB::table('contato_lista_itens')->where('lista_id', $l->id)->count(),
            ]),
            'duplicados' => $f['aba'] === 'duplicados' ? $this->duplicados() : [],
            'conferencia' => session('conferencia'),
        ]);
    }

    public function create()
    {
        return $this->form(null);
    }

    public function show(int $id)
    {
        $principal = Contatos::principal($id);
        if ($principal !== $id) {
            return redirect("/flow/contatos/{$principal}")->with('success', 'Este contato foi unificado neste.');
        }

        return $this->form($id);
    }

    public function store(Request $request)
    {
        $dados = Contatos::validar($request->all());
        $id = DB::table('contatos')->insertGetId($dados + ['origem' => 'interno', 'criado_por' => Auth::id(), 'created_at' => now(), 'updated_at' => now()]);
        Contatos::registrar($id, 'cadastro');

        return redirect("/flow/contatos/{$id}")->with('success', 'Contato cadastrado.');
    }

    public function update(Request $request, int $id)
    {
        $antes = DB::table('contatos')->whereNull('deleted_at')->where('id', $id)->first() ?? abort(404);
        $dados = Contatos::validar($request->all(), $id);
        DB::table('contatos')->where('id', $id)->update($dados + ['updated_at' => now()]);
        $mudou = collect($dados)->filter(fn ($v, $k) => (string) $v !== (string) ($antes->{$k} ?? ''))->keys()->values()->all();
        if ($mudou) {
            Contatos::registrar($id, 'alteracao', ['campos' => $mudou]);
        }

        return back()->with('success', 'Contato alterado.');
    }

    public function destroy(int $id)
    {
        $c = DB::table('contatos')->where('id', $id)->whereNull('deleted_at')->first() ?? abort(404);
        if ($c->cidadao_id) {
            return back()->with('error', 'Este contato é o cadastro de um cidadão no portal. Desative o acesso dele em vez de excluir.');
        }
        if (DB::table('proc_processos')->where('contato_id', $id)->orWhere('procurador_contato_id', $id)->exists()) {
            return back()->with('error', 'Este contato está em processos. Para juntar com outro, use a unificação.');
        }
        DB::table('contatos')->where('id', $id)->update(['deleted_at' => now()]);
        Contatos::registrar($id, 'exclusao');

        return redirect('/flow/contatos')->with('success', 'Contato excluído.');
    }

    /** Autocompletar (empresa do setor, procurador, outros módulos). */
    public function buscar(Request $request)
    {
        $q = trim((string) $request->input('q', ''));
        if (mb_strlen($q) < 2) {
            return response()->json([]);
        }

        return response()->json($this->consulta(['q' => $q, 'tipo' => (string) $request->input('tipo', ''), 'lista' => null, 'aba' => 'todos'])
            ->limit(15)->get()->map(fn ($c) => ['id' => $c->id, 'nome' => $c->nome, 'tipo' => $c->tipo,
                'documento' => DocumentoPessoa::mascarar($c->cpf ?: $c->cnpj), 'email' => $c->email]));
    }

    /** Unificar duplicados (TR 4.21.13). */
    public function unificar(Request $request)
    {
        $d = $request->validate([
            'principal_id' => ['required', 'integer'],
            'outros'       => ['required', 'array', 'min:1', 'max:20'],
            'outros.*'     => ['integer'],
        ]);
        $ids = array_unique([(int) $d['principal_id'], ...array_map('intval', $d['outros'])]);
        if (DB::table('contatos')->whereIn('id', $ids)->whereNull('deleted_at')->whereNull('unificado_em_id')->count() !== count($ids)) {
            throw ValidationException::withMessages(['outros' => 'Algum contato já foi excluído ou unificado. Atualize a página.']);
        }
        $comPortal = DB::table('contatos')->whereIn('id', $ids)->whereNotNull('cidadao_id')->count();
        if ($comPortal > 1) {
            throw ValidationException::withMessages(['outros' => 'Dois destes contatos têm cadastro próprio no portal. Unifique os cadastros do portal antes.']);
        }
        $n = Contatos::unificar((int) $d['principal_id'], $d['outros']);

        return redirect("/flow/contatos/{$d['principal_id']}")->with('success', "{$n} contato(s) unificado(s) neste.");
    }

    // ── Listas (TR 4.21.14) ──

    public function salvarLista(Request $request, ?int $id = null)
    {
        $d = $request->validate(['nome' => ['required', 'string', 'max:120'], 'descricao' => ['nullable', 'string', 'max:255']]);
        $id
            ? DB::table('contato_listas')->where('id', $id)->update($d + ['updated_at' => now()])
            : DB::table('contato_listas')->insert($d + ['criado_por' => Auth::id(), 'created_at' => now(), 'updated_at' => now()]);

        return back()->with('success', $id ? 'Lista alterada.' : "Lista \"{$d['nome']}\" criada.");
    }

    public function excluirLista(int $id)
    {
        DB::table('contato_listas')->where('id', $id)->delete();

        return redirect('/flow/contatos')->with('success', 'Lista excluída. Os contatos continuam cadastrados.');
    }

    public function itensLista(Request $request, int $id)
    {
        $d = $request->validate(['contatos' => ['required', 'array', 'min:1', 'max:500'], 'contatos.*' => ['integer'], 'acao' => ['required', 'in:incluir,retirar']]);
        abort_unless(DB::table('contato_listas')->where('id', $id)->exists(), 404);
        if ($d['acao'] === 'incluir') {
            $validos = DB::table('contatos')->whereIn('id', $d['contatos'])->whereNull('deleted_at')->pluck('id');
            DB::table('contato_lista_itens')->insertOrIgnore($validos->map(fn ($c) => ['lista_id' => $id, 'contato_id' => $c])->all());
        } else {
            DB::table('contato_lista_itens')->where('lista_id', $id)->whereIn('contato_id', $d['contatos'])->delete();
        }

        return back()->with('success', $d['acao'] === 'incluir' ? 'Incluídos na lista.' : 'Retirados da lista.');
    }

    // ── Procurações (TR 4.21.10–11) ──

    public function procuracao(Request $request, int $id)
    {
        $d = $request->validate([
            'procurador_id' => ['required', 'integer', 'different:outorgante'],
            'poderes'       => ['nullable', 'string', 'max:1000'],
            'inicio'        => ['nullable', 'date'],
            'fim'           => ['nullable', 'date', 'after_or_equal:inicio'],
            'documento'     => ['nullable', 'file', 'mimes:pdf,png,jpg,jpeg', 'max:10240'],
        ]);
        $procurador = Contatos::principal((int) $d['procurador_id']);
        if ($procurador === $id || ! DB::table('contatos')->where('id', $procurador)->whereNull('deleted_at')->exists()) {
            throw ValidationException::withMessages(['procurador_id' => 'Escolha outro contato como procurador.']);
        }
        $caminho = $request->hasFile('documento')
            ? $request->file('documento')->store(\App\Tenant\TenantStorage::pasta('procuracoes'), 'documentos')
            : null;
        DB::table('contato_procuracoes')->insert([
            'outorgante_id' => $id, 'procurador_id' => $procurador, 'poderes' => $d['poderes'] ?? null,
            'inicio' => $d['inicio'] ?? null, 'fim' => $d['fim'] ?? null, 'documento_path' => $caminho,
            'criado_por' => Auth::id(), 'created_at' => now(), 'updated_at' => now(),
        ]);
        Contatos::registrar($id, 'procuracao', ['procurador' => $procurador]);

        return back()->with('success', 'Procuração registrada.');
    }

    public function revogarProcuracao(int $id)
    {
        $p = DB::table('contato_procuracoes')->where('id', $id)->whereNull('revogada_em')->first() ?? abort(404);
        DB::table('contato_procuracoes')->where('id', $id)->update(['revogada_em' => now(), 'updated_at' => now()]);
        Contatos::registrar((int) $p->outorgante_id, 'procuracao_revogada', ['procurador' => $p->procurador_id]);

        return back()->with('success', 'Procuração revogada.');
    }

    public function documentoProcuracao(int $id)
    {
        $caminho = DB::table('contato_procuracoes')->where('id', $id)->value('documento_path');
        abort_unless($caminho && Storage::disk('documentos')->exists($caminho), 404);

        return Storage::disk('documentos')->response($caminho);
    }

    // ── CSV (TR 4.21.12) ──

    public function exportar(Request $request): StreamedResponse
    {
        $f = ['q' => trim((string) $request->input('q', '')), 'tipo' => (string) $request->input('tipo', ''),
            'lista' => $request->integer('lista') ?: null, 'aba' => 'todos'];
        $campos = Contatos::campos();

        return response()->streamDownload(function () use ($f, $campos) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF");
            fputcsv($out, ['tipo', 'nome', 'cpf', 'cnpj', 'email', 'celular', 'telefone', 'cep', 'logradouro', 'numero',
                'bairro', 'cidade', 'uf', 'empresa_cnpj', ...array_map(fn ($c) => $c->nome, $campos)], ';');
            $this->consulta($f)->orderBy('id')->chunk(500, function ($lote) use ($out, $campos) {
                foreach ($lote as $c) {
                    $e = $c->endereco ? json_decode($c->endereco, true) : [];
                    $extra = $c->campos ? json_decode($c->campos, true) : [];
                    fputcsv($out, [$c->tipo, $c->nome, $c->cpf, $c->cnpj, $c->email, $c->celular, $c->telefone,
                        $e['cep'] ?? '', $e['logradouro'] ?? '', $e['numero'] ?? '', $e['bairro'] ?? '', $e['cidade'] ?? '', $e['uf'] ?? '',
                        $c->empresa_id ? DB::table('contatos')->where('id', $c->empresa_id)->value('cnpj') : '',
                        ...array_map(fn ($cp) => $extra[$cp->id] ?? '', $campos)], ';');
                }
            });
            fclose($out);
        }, 'contatos.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    /** Importação: confere todas as linhas (mesmas regras do cadastro) e mostra antes de gravar. */
    public function conferir(Request $request)
    {
        $request->validate(['arquivo' => ['required', 'file', 'mimes:csv,txt', 'max:5120']]);
        $conteudo = preg_replace('/^\xEF\xBB\xBF/', '', (string) file_get_contents($request->file('arquivo')->getRealPath()));
        if (! mb_check_encoding($conteudo, 'UTF-8')) {
            $conteudo = mb_convert_encoding($conteudo, 'UTF-8', 'Windows-1252');
        }
        $linhas = preg_split('/\r\n|\r|\n/', trim($conteudo));
        $sep = substr_count($linhas[0] ?? '', ';') >= substr_count($linhas[0] ?? '', ',') ? ';' : ',';
        $cab = array_map(fn ($h) => Str::lower(trim($h)), str_getcsv(array_shift($linhas) ?? '', $sep));
        if (! in_array('nome', $cab, true) || ! in_array('tipo', $cab, true)) {
            throw ValidationException::withMessages(['arquivo' => 'O arquivo precisa do cabeçalho com pelo menos as colunas tipo e nome (exporte um CSV para ver o modelo).']);
        }
        $porNome = collect(Contatos::campos())->keyBy(fn ($c) => Str::lower($c->nome));

        $itens = [];
        $docs = [];
        foreach (array_slice($linhas, 0, 2000) as $n => $linha) {
            if (trim($linha) === '') {
                continue;
            }
            $v = array_combine($cab, array_pad(array_map('trim', str_getcsv($linha, $sep)), count($cab), ''));
            $tipo = ['pf' => 'pf', 'pessoa física' => 'pf', 'pj' => 'pj', 'pessoa jurídica' => 'pj', 'setor_pj' => 'setor_pj'][Str::lower($v['tipo'])] ?? $v['tipo'];
            $dados = ['tipo' => $tipo, 'nome' => $v['nome'], 'cpf' => $v['cpf'] ?? '', 'cnpj' => $v['cnpj'] ?? '', 'email' => $v['email'] ?? '',
                'celular' => $v['celular'] ?? '', 'telefone' => $v['telefone'] ?? '',
                'endereco' => array_filter(['cep' => $v['cep'] ?? '', 'logradouro' => $v['logradouro'] ?? '', 'numero' => $v['numero'] ?? '',
                    'bairro' => $v['bairro'] ?? '', 'cidade' => $v['cidade'] ?? '', 'uf' => $v['uf'] ?? '']), 'campos' => []];
            if (! empty($v['empresa_cnpj'])) {
                $dados['empresa_id'] = Contatos::porDocumento($v['empresa_cnpj']);
            }
            foreach ($v as $col => $valor) {
                if ($porNome->has($col)) {
                    $dados['campos'][$porNome[$col]->id] = $valor;
                }
            }
            $erro = null;
            $doc = \App\Services\Portal\CidadaoPortalService::digitos($dados['cpf'] ?: $dados['cnpj']);
            if ($doc && isset($docs[$doc])) {
                $erro = "Documento repetido no arquivo (linha {$docs[$doc]})";
            } else {
                try {
                    Contatos::validar($dados);
                } catch (ValidationException $e) {
                    $erro = collect($e->errors())->flatten()->implode(' ');
                }
            }
            if ($doc) {
                $docs[$doc] = $n + 2;
            }
            $itens[] = ['linha' => $n + 2, 'nome' => $dados['nome'], 'tipo' => $tipo, 'documento' => DocumentoPessoa::mascarar($doc),
                'email' => $dados['email'], 'erro' => $erro, 'dados' => $dados];
        }

        $token = Str::uuid()->toString();
        Cache::put("contatos-importacao:{$token}", $itens, now()->addMinutes(30));
        $validos = collect($itens)->whereNull('erro')->count();

        return back()->with('conferencia', ['token' => $token, 'validos' => $validos, 'erros' => count($itens) - $validos,
            'itens' => collect($itens)->map(fn ($i) => collect($i)->except('dados'))->all()]);
    }

    public function importar(Request $request)
    {
        $token = $request->validate(['token' => ['required', 'uuid']])['token'];
        $itens = Cache::pull("contatos-importacao:{$token}") ?? throw ValidationException::withMessages(['arquivo' => 'A conferência expirou. Envie o arquivo de novo.']);
        $n = 0;
        DB::transaction(function () use ($itens, &$n) {
            foreach ($itens as $i) {
                if ($i['erro']) {
                    continue;   // as linhas com erro ficam de fora; as outras entram
                }
                try {
                    $dados = Contatos::validar($i['dados']);
                } catch (ValidationException) {
                    continue;   // alguém cadastrou o mesmo documento depois da conferência
                }
                $id = DB::table('contatos')->insertGetId($dados + ['origem' => 'importacao', 'criado_por' => Auth::id(), 'created_at' => now(), 'updated_at' => now()]);
                Contatos::registrar($id, 'importacao');
                $n++;
            }
        });

        return redirect('/flow/contatos')->with('success', "{$n} contato(s) importado(s).");
    }

    // ── internos ──

    private function consulta(array $f)
    {
        $q = DB::table('contatos as c')->whereNull('c.deleted_at')->whereNull('c.unificado_em_id')->select('c.*');
        if ($f['q'] !== '') {
            $d = preg_replace('/\D/', '', $f['q']);
            $like = '%' . str_replace(['%', '_'], ['\%', '\_'], $f['q']) . '%';
            $q->where(fn ($w) => $w->where('c.nome', 'ilike', $like)->orWhere('c.email', 'ilike', $like)
                ->when(strlen($d) >= 3, fn ($w) => $w->orWhere('c.cpf', 'like', "%{$d}%")->orWhere('c.cnpj', 'like', "%{$d}%")
                    ->orWhere('c.celular', 'like', "%{$d}%")));
        }
        if ($f['tipo']) {
            $q->where('c.tipo', $f['tipo']);
        }
        if ($f['lista']) {
            $q->whereIn('c.id', DB::table('contato_lista_itens')->where('lista_id', $f['lista'])->select('contato_id'));
        }

        return $q->orderBy('c.nome');
    }

    private function linha(object $c): array
    {
        return [
            'id' => $c->id, 'tipo' => $c->tipo, 'nome' => $c->nome, 'documento' => DocumentoPessoa::mascarar($c->cpf ?: $c->cnpj),
            'email' => $c->email, 'celular' => $c->celular, 'origem' => $c->origem, 'portal' => (bool) $c->cidadao_id,
            'empresa' => $c->empresa_id ? DB::table('contatos')->where('id', $c->empresa_id)->value('nome') : null,
        ];
    }

    /** Possíveis duplicados: mesmo CPF/CNPJ, mesmo e-mail ou mesmo nome. */
    private function duplicados(): array
    {
        $grupos = [];
        foreach (['cpf', 'cnpj', 'email'] as $col) {
            $repetidos = DB::table('contatos')->whereNull('deleted_at')->whereNull('unificado_em_id')->whereNotNull($col)
                ->groupBy($col)->havingRaw('count(*) > 1')->limit(50)->pluck($col);
            foreach ($repetidos as $valor) {
                $grupos[] = ['motivo' => ['cpf' => 'Mesmo CPF', 'cnpj' => 'Mesmo CNPJ', 'email' => 'Mesmo e-mail'][$col],
                    'contatos' => DB::table('contatos')->whereNull('deleted_at')->whereNull('unificado_em_id')->where($col, $valor)
                        ->orderBy('id')->get()->map(fn ($c) => $this->linha($c))->all()];
            }
        }
        $nomes = DB::table('contatos')->whereNull('deleted_at')->whereNull('unificado_em_id')
            ->groupByRaw('lower(trim(nome))')->havingRaw('count(*) > 1')->limit(50)->selectRaw('lower(trim(nome)) as n')->pluck('n');
        foreach ($nomes as $n) {
            $grupos[] = ['motivo' => 'Mesmo nome', 'contatos' => DB::table('contatos')->whereNull('deleted_at')->whereNull('unificado_em_id')
                ->whereRaw('lower(trim(nome)) = ?', [$n])->orderBy('id')->get()->map(fn ($c) => $this->linha($c))->all()];
        }
        // O mesmo par pode aparecer por dois motivos: fica o primeiro
        $vistos = [];

        return array_values(array_filter($grupos, function ($g) use (&$vistos) {
            $chave = implode(',', array_column($g['contatos'], 'id'));
            if (isset($vistos[$chave])) {
                return false;
            }
            $vistos[$chave] = true;

            return true;
        }));
    }

    private function form(?int $id)
    {
        $c = $id ? (DB::table('contatos')->where('id', $id)->whereNull('deleted_at')->first() ?? abort(404)) : null;
        $nome = fn ($cid) => DB::table('contatos')->where('id', $cid)->value('nome');
        $procuracoes = fn (string $col) => $id ? DB::table('contato_procuracoes as p')->where("p.{$col}", $id)
            ->join('contatos as o', 'o.id', '=', 'p.outorgante_id')->join('contatos as r', 'r.id', '=', 'p.procurador_id')
            ->orderByDesc('p.id')->get(['p.*', 'o.nome as outorgante', 'r.nome as procurador'])
            ->map(fn ($p) => (array) $p + ['vigente' => ! $p->revogada_em && (! $p->fim || $p->fim >= now()->toDateString())])->all() : [];

        return Inertia::render('GED/Contatos/Form', [
            'contato' => $c ? [
                'id' => $c->id, 'tipo' => $c->tipo, 'nome' => $c->nome, 'cpf' => Contatos::documento($c->cpf, null) ?? '',
                'cnpj' => Contatos::documento(null, $c->cnpj) ?? '', 'email' => $c->email, 'celular' => $c->celular, 'telefone' => $c->telefone,
                'endereco' => $c->endereco ? json_decode($c->endereco, true) : null, 'empresa_id' => $c->empresa_id,
                'empresa_nome' => $c->empresa_id ? $nome($c->empresa_id) : null, 'campos' => $c->campos ? json_decode($c->campos, true) : [],
                'observacao' => $c->observacao, 'origem' => $c->origem, 'portal' => (bool) $c->cidadao_id,
                'criado_em' => $c->created_at,
            ] : null,
            'tipos'       => Contatos::TIPOS,
            'campos'      => Contatos::campos(null, 'interno'),
            'regras'      => Contatos::regras(),
            'procuracoes' => $procuracoes('outorgante_id'),
            'procurador_de' => $procuracoes('procurador_id'),
            'setores'     => $id ? DB::table('contatos')->where('empresa_id', $id)->whereNull('deleted_at')->orderBy('nome')->get(['id', 'nome']) : [],
            'processos'   => $id ? DB::table('proc_processos')->where('contato_id', $id)->orWhere('procurador_contato_id', $id)
                ->orderByDesc('id')->limit(30)->get(['id', 'numero_protocolo', 'assunto', 'status', 'created_at']) : [],
            'listas'      => DB::table('contato_listas')->orderBy('nome')->get(['id', 'nome']),
            'nas_listas'  => $id ? DB::table('contato_lista_itens')->where('contato_id', $id)->pluck('lista_id') : [],
            'historico'   => $id ? DB::table('contato_historico as h')->leftJoin('users as u', 'u.id', '=', 'h.usuario_id')
                ->where('h.contato_id', $id)->orderByDesc('h.id')->limit(30)->get(['h.acao', 'h.detalhes', 'h.created_at', 'u.name as usuario']) : [],
        ]);
    }
}
