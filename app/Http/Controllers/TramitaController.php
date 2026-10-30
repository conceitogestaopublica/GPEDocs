<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Integracoes\Tramita\Fabrica;
use App\Integracoes\Tramita\FalhaTramita;
use App\Integracoes\Tramita\Simulador;
use App\Models\Processo\Oficio;
use App\Models\Processo\Processo;
use App\Support\Relatorio;
use App\Support\RequerimentoServidor;
use App\Support\Tramita;
use App\Tenant\TenantStorage;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;

/**
 * Tramita GOV.BR (TR 4.28): enviados e recebidos com NRE, IDT, data e hora; envio do ofício e do processo;
 * aceite ou recusa do que chega; comprovante do trâmite em PDF; configuração da adesão (adaptador,
 * certificado, estrutura do ente, destino dos recebidos) e o simulador para a homologação.
 */
class TramitaController extends Controller
{
    public function index(Request $request)
    {
        $this->exigir('tramita.usar');
        $sentido = $request->input('sentido') === 'recebido' ? 'recebido' : 'enviado';
        $cfg = Fabrica::config();

        return Inertia::render('Tramita/Index', [
            'sentido' => $sentido,
            'tramites' => DB::table('tramita_tramites as t')->leftJoin('users as u', 'u.id', '=', 't.usuario_id')->leftJoin('proc_processos as p', 'p.id', '=', 't.processo_id')
                ->leftJoin('proc_oficios as o', 'o.id', '=', 't.oficio_id')->where('t.sentido', $sentido)->orderByDesc('t.id')->limit(300)
                ->get(['t.*', 'u.name as usuario', 'p.numero_protocolo', 'o.numero as oficio_numero'])->map(fn ($t) => $this->linha($t)),
            'contagem' => DB::table('tramita_tramites')->groupBy('sentido')->selectRaw('sentido as k, count(*) as v')->pluck('v', 'k'),
            'aguardando' => DB::table('tramita_tramites')->where('sentido', 'recebido')->where('status', 'aguardando_aceite')->count(),
            'config' => ['ativo' => (bool) $cfg->ativo, 'adaptador' => $cfg->adaptador, 'estrutura_nome' => $cfg->estrutura_nome,
                'sincronizado_em' => $cfg->sincronizado_em ? Carbon::parse($cfg->sincronizado_em, 'UTC')->toIso8601String() : null],
            'motivos' => Tramita::MOTIVOS_RECUSA, 'statusRotulos' => Tramita::STATUS,
            'podeConfigurar' => $this->pode('tramita.configurar'),
        ]);
    }

    public function estruturas(Request $request)
    {
        $this->exigir('tramita.usar');
        try {
            return response()->json(Fabrica::barramento()->estruturas(trim((string) $request->input('q'))));
        } catch (FalhaTramita $e) {
            return response()->json(['erro' => $e->getMessage()], 422);
        }
    }

    public function enviarOficio(Request $request, int $id)
    {
        $this->exigir('tramita.usar');
        $destino = $this->destino($request);
        $o = Oficio::findOrFail($id);

        return $this->tentar(fn () => Tramita::enviarOficio($o, $destino, Auth::user()), fn ($t) => "Ofício enviado pelo Tramita GOV.BR: NRE {$t->nre}, IDT {$t->idt}.");
    }

    public function enviarProcesso(Request $request, int $id)
    {
        $this->exigir('tramita.usar');
        $destino = $this->destino($request);
        $p = Processo::with('tipoProcesso')->findOrFail($id);
        abort_unless(RequerimentoServidor::podeVer(Auth::user(), $p), 403);

        return $this->tentar(fn () => Tramita::enviarProcesso($p, $destino, Auth::user()), fn ($t) => "Processo enviado pelo Tramita GOV.BR: NRE {$t->nre}, IDT {$t->idt}.");
    }

    public function sincronizar()
    {
        $this->exigir('tramita.usar');
        $r = Tramita::sincronizar();
        $msg = "Sincronizado: {$r['concluidos']} recibo(s), {$r['recusados']} recusa(s), {$r['recebidos']} recebido(s).";

        return back()->with($r['erros'] ? 'error' : 'success', $msg . ($r['erros'] ? ' Erros: ' . implode(' | ', array_slice($r['erros'], 0, 3)) : ''));
    }

    public function aceitar(int $id)
    {
        $this->exigir('tramita.usar');
        $t = $this->recebido($id);

        return $this->tentar(fn () => Tramita::aceitar($t, Auth::user()), fn ($p) => "Aceito: processo {$p->numero_protocolo} aberto e recibo enviado ao remetente.");
    }

    public function recusar(Request $request, int $id)
    {
        $this->exigir('tramita.usar');
        $d = $request->validate(['motivo' => ['required', 'in:' . implode(',', array_keys(Tramita::MOTIVOS_RECUSA))], 'justificativa' => ['required', 'string', 'max:1000']]);
        $t = $this->recebido($id);

        return $this->tentar(fn () => Tramita::recusar($t, $d['motivo'], $d['justificativa'], Auth::user()), fn () => 'Trâmite recusado. O remetente foi avisado pelo barramento.');
    }

    public function componente(int $id, int $indice)
    {
        $this->exigir('tramita.usar');
        $t = DB::table('tramita_tramites')->find($id);
        $c = (json_decode((string) $t?->componentes, true) ?: [])[$indice] ?? null;
        abort_unless($c && ! empty($c['path']) && Storage::disk('documentos')->exists($c['path']), 404);

        return Storage::disk('documentos')->response($c['path'], $c['nome'], ['Content-Type' => $c['mime']]);
    }

    /** Comprovante do trâmite (TR 4.28.5): data, hora, NRE, IDT, destino/origem, arquivos e hashes. */
    public function comprovante(int $id)
    {
        $this->exigir('tramita.usar');
        $t = DB::table('tramita_tramites')->find($id);
        abort_unless($t, 404);

        return Relatorio::pdf('pdf.tramita-comprovante', ['t' => $t, 'linha' => $this->linha($t), 'componentes' => json_decode((string) $t->componentes, true) ?: [],
            'recibo' => json_decode((string) $t->recibo, true) ?: [], 'ug' => Auth::user()->ugAtual(), 'usuario' => Auth::user()->name])
            ->stream("tramite-{$t->idt}.pdf");
    }

    /** Envios do ofício/processo para a tela dele. */
    public static function doDocumento(string $coluna, int $id): array
    {
        return ['ativo' => Tramita::ativo(), 'tramites' => DB::table('tramita_tramites')->where($coluna, $id)->orderByDesc('id')->get()
            ->map(fn ($t) => ['id' => $t->id, 'sentido' => $t->sentido, 'status' => $t->status, 'status_rotulo' => Tramita::STATUS[$t->status] ?? $t->status,
                'nre' => $t->nre, 'idt' => $t->idt, 'outro_nome' => $t->outro_nome,
                'enviado_em' => $t->enviado_em ? Carbon::parse($t->enviado_em, 'UTC')->toIso8601String() : null,
                'concluido_em' => $t->concluido_em ? Carbon::parse($t->concluido_em, 'UTC')->toIso8601String() : null,
                'recusa' => $t->recusa_motivo ? (Tramita::MOTIVOS_RECUSA[$t->recusa_motivo] ?? $t->recusa_motivo) . ($t->recusa_justificativa ? " — {$t->recusa_justificativa}" : '') : null])->all()];
    }

    // ---------------------------------------------------------------- configuração

    public function configuracao()
    {
        $this->exigir('tramita.configurar');
        $c = Fabrica::config();

        return Inertia::render('Tramita/Configuracao', [
            'config' => ['ativo' => (bool) $c->ativo, 'adaptador' => $c->adaptador, 'ambiente' => $c->ambiente, 'url' => $c->url,
                'tem_certificado' => (bool) $c->certificado_path, 'repositorio_id' => $c->repositorio_id, 'estrutura_id' => $c->estrutura_id,
                'estrutura_nome' => $c->estrutura_nome, 'tipo_recebido_id' => $c->tipo_recebido_id, 'unidade_entrada_id' => $c->unidade_entrada_id,
                'aceite_automatico' => (bool) $c->aceite_automatico],
            'tipos' => DB::table('proc_tipos_processo')->where('ativo', true)->orderBy('nome')->get(['id', 'nome']),
            'setores' => DB::table('ug_organograma')->where('ativo', true)->orderBy('nome')->get(['id', 'nome', 'sigla']),
        ]);
    }

    public function salvarConfig(Request $request)
    {
        $this->exigir('tramita.configurar');
        $d = $request->validate(['ativo' => ['boolean'], 'adaptador' => ['required', 'in:simulador,pen'], 'ambiente' => ['required', 'in:homologacao,producao'],
            'url' => ['nullable', 'url', 'max:300'], 'repositorio_id' => ['nullable', 'integer'], 'estrutura_id' => ['nullable', 'string', 'max:40'],
            'estrutura_nome' => ['nullable', 'string', 'max:200'], 'tipo_recebido_id' => ['nullable', 'integer', 'exists:proc_tipos_processo,id'],
            'unidade_entrada_id' => ['nullable', 'integer', 'exists:ug_organograma,id'], 'aceite_automatico' => ['boolean'],
            'certificado' => ['nullable', 'file', 'max:200'], 'certificado_senha' => ['nullable', 'string', 'max:200']]);
        $linha = collect($d)->except(['certificado', 'certificado_senha'])->all() + ['ativo' => $request->boolean('ativo'), 'aceite_automatico' => $request->boolean('aceite_automatico'), 'updated_at' => now()];
        if ($request->hasFile('certificado')) {
            $linha['certificado_path'] = $request->file('certificado')->storeAs(TenantStorage::pasta('tramita-certificado'), 'cliente.' . ($request->file('certificado')->getClientOriginalExtension() ?: 'pem'), 'local');
        }
        if ($request->filled('certificado_senha')) {
            $linha['certificado_senha'] = Crypt::encryptString($request->input('certificado_senha'));
        }
        $id = DB::table('tramita_config')->value('id');
        $id ? DB::table('tramita_config')->where('id', $id)->update($linha) : DB::table('tramita_config')->insert($linha + ['created_at' => now()]);

        return back()->with('success', 'Configuração do Tramita GOV.BR salva.');
    }

    public function testar()
    {
        $this->exigir('tramita.configurar');
        try {
            return back()->with('success', Fabrica::barramento()->testar());
        } catch (\Throwable $e) {
            return back()->with('error', 'Falha: ' . $e->getMessage());
        }
    }

    /** Simulador: um ente externo envia um ofício ou processo para nós (teste do recebimento). */
    public function simularEntrada(Request $request)
    {
        $this->exigir('tramita.configurar');
        $bar = Fabrica::barramento();
        abort_unless($bar instanceof Simulador, 422, 'Disponível só com o adaptador simulador.');
        $idt = $bar->simularEntrada($request->input('tipo') === 'oficio' ? 'oficio' : 'processo', ['estrutura' => Fabrica::config()->estrutura_id]);

        return back()->with('success', "Ente simulado enviou o trâmite IDT {$idt}. Clique em Sincronizar para recebê-lo.");
    }

    // ---------------------------------------------------------------- apoio

    private function linha(object $t): array
    {
        $h = fn ($v) => $v ? Carbon::parse($v, 'UTC')->toIso8601String() : null;
        $comps = json_decode((string) $t->componentes, true) ?: [];

        return ['id' => $t->id, 'sentido' => $t->sentido, 'tipo' => $t->tipo, 'status' => $t->status, 'status_rotulo' => Tramita::STATUS[$t->status] ?? $t->status,
            'nre' => $t->nre, 'idt' => $t->idt, 'outro_nome' => $t->outro_nome, 'outro_estrutura' => $t->outro_estrutura, 'assunto' => $t->assunto,
            'usuario' => $t->usuario ?? null, 'processo_id' => $t->processo_id, 'processo' => $t->numero_protocolo ?? null, 'oficio_id' => $t->oficio_id, 'oficio' => $t->oficio_numero ?? null,
            'componentes' => array_map(fn ($c, $i) => ['indice' => $i, 'nome' => $c['nome'], 'tamanho' => $c['tamanho'] ?? null, 'hash_ok' => $c['hash_ok'] ?? true, 'baixar' => ! empty($c['path'])], $comps, array_keys($comps)),
            'recusa' => $t->recusa_motivo ? (Tramita::MOTIVOS_RECUSA[$t->recusa_motivo] ?? $t->recusa_motivo) : null, 'recusa_justificativa' => $t->recusa_justificativa,
            'erro' => $t->erro, 'enviado_em' => $h($t->enviado_em), 'concluido_em' => $h($t->concluido_em)];
    }

    private function destino(Request $request): array
    {
        $d = $request->validate(['repositorio' => ['nullable', 'string', 'max:40'], 'estrutura' => ['required', 'string', 'max:40'], 'nome' => ['required', 'string', 'max:250']]);
        abort_unless(Tramita::ativo(), 422, 'O Tramita GOV.BR não está ativo. Configure em Tramita GOV.BR → Configuração.');

        return $d;
    }

    private function recebido(int $id): object
    {
        $t = DB::table('tramita_tramites')->where('id', $id)->where('sentido', 'recebido')->first();
        abort_unless($t, 404);
        abort_unless($t->status === 'aguardando_aceite', 422, 'Este trâmite já foi aceito ou recusado.');

        return $t;
    }

    private function tentar(callable $fazer, callable $mensagem)
    {
        try {
            return back()->with('success', $mensagem($fazer()));
        } catch (FalhaTramita $e) {
            return back()->with('error', $e->getMessage());
        }
    }

    private function pode(string $perm): bool
    {
        return Auth::user()->super_admin || Auth::user()->can($perm);
    }

    private function exigir(string $perm): void
    {
        abort_unless($this->pode($perm), 403);
    }
}
