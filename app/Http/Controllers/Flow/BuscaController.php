<?php

declare(strict_types=1);

namespace App\Http\Controllers\Flow;

use App\Http\Controllers\Controller;
use App\Models\Processo\Memorando;
use App\Models\Processo\Oficio;
use App\Models\Processo\Processo;
use App\Models\Processo\TipoProcesso;
use App\Models\User;
use App\Support\Etiquetas;
use App\Support\Setores;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

/**
 * Busca avançada de processos, memorandos e ofícios (TR 4.15.1–4.15.8): termos dentro do
 * conteúdo (despachos, comentários, pareceres e o texto dos anexos — com OCR), frase exata
 * entre aspas, tipo + número + ano, período, assunto(s), setor e escopo (setor atual, todos
 * os meus ou todos), usuário interno, interessado externo, etiqueta e situação geral
 * (em aberto, parcialmente arquivado, arquivado). Mostra só o que o usuário já pode ver.
 */
class BuscaController extends Controller
{
    private const LIMITE = 100;
    private const FINAIS = ['concluido', 'cancelado', 'arquivado'];

    public function index(Request $request)
    {
        $user = Auth::user();
        $f = $this->filtros($request);
        $buscou = collect($f)->except(['tipos', 'escopo'])->filter(fn ($v) => $v !== null && $v !== '' && $v !== [])->isNotEmpty();

        $resultados = collect();
        if ($buscou) {
            if (in_array('processo', $f['tipos'], true)) {
                $resultados = $resultados->concat($this->processos($f, $user));
            }
            if (in_array('memorando', $f['tipos'], true) && ! $f['assuntos']) {
                $resultados = $resultados->concat($this->memorandos($f, $user));
            }
            if (in_array('oficio', $f['tipos'], true) && ! $f['assuntos']) {
                $resultados = $resultados->concat($this->oficios($f, $user));
            }
        }

        return Inertia::render('GED/Flow/Busca', [
            'filtros'    => $f,
            'buscou'     => $buscou,
            'resultados' => $resultados->sortByDesc('data')->take(self::LIMITE)->values(),
            'limitado'   => $resultados->count() > self::LIMITE,
            'opcoes'     => [
                'assuntos'  => TipoProcesso::where('ativo', true)->orderBy('nome')->get(['id', 'nome']),
                'setores'   => \App\Models\UgOrganograma::whereIn('id', Setores::doUsuario($user))->orderBy('nome')->get(['id', 'nome']),
                'usuarios'  => User::where('tipo', 'interno')->whereNull('suspenso_em')->orderBy('name')->get(['id', 'name']),
                'etiquetas' => Etiquetas::doUsuario($user)->values(),
                'classificacoes' => \App\Support\Classificacao::opcoes(false),
                'pode_todos' => (bool) ($user->acesso_geral_ug || $user->super_admin),
            ],
        ]);
    }

    /** @return array<string, mixed> */
    private function filtros(Request $r): array
    {
        $tipos = array_values(array_intersect((array) $r->input('tipos', []), ['processo', 'memorando', 'oficio']));

        return [
            'q'        => trim((string) $r->input('q', '')),
            'tipos'    => $tipos ?: ['processo', 'memorando', 'oficio'],
            'numero'   => trim((string) $r->input('numero', '')),
            'ano'      => $r->integer('ano') ?: null,
            'de'       => $r->input('de') ?: null,
            'ate'      => $r->input('ate') ?: null,
            'assuntos' => array_map('intval', (array) $r->input('assuntos', [])),
            'escopo'   => in_array($r->input('escopo'), ['atual', 'meus', 'todos'], true) ? $r->input('escopo') : 'meus',
            'setor'    => $r->integer('setor') ?: null,
            'interno'  => $r->integer('interno') ?: null,
            'externo'  => trim((string) $r->input('externo', '')),
            'etiqueta' => $r->integer('etiqueta') ?: null,
            'classificacao' => $r->integer('classificacao') ?: null,
            'situacao' => in_array($r->input('situacao'), ['aberto', 'parcial', 'arquivado'], true) ? $r->input('situacao') : null,
        ];
    }

    /**
     * "frase exata" e palavras soltas: cada uma precisa aparecer (TR 4.15.5).
     *
     * @return list<string>
     */
    private function termos(string $q): array
    {
        preg_match_all('/"([^"]+)"|(\S+)/u', $q, $m, PREG_SET_ORDER);

        return array_values(array_filter(array_map(fn ($x) => trim($x[1] !== '' ? $x[1] : ($x[2] ?? '')), $m), fn ($t) => mb_strlen($t) >= 2));
    }

    private function like(string $t): string
    {
        return '%' . addcslashes($t, '%_\\') . '%';
    }

    /** Setores do escopo: o escolhido, o principal (atual), todos os meus, ou sem filtro. @return list<int>|null */
    private function setoresDoEscopo(array $f, User $user): ?array
    {
        if ($f['setor']) {
            return in_array($f['setor'], Setores::doUsuario($user), true) || $user->acesso_geral_ug || $user->super_admin ? [$f['setor']] : [-1];
        }

        return match ($f['escopo']) {
            'atual' => [$user->unidade_id ?: -1],
            'todos' => $user->acesso_geral_ug || $user->super_admin ? null : (Setores::doUsuario($user) ?: [-1]),
            default => Setores::doUsuario($user) ?: [-1],
        };
    }

    private function processos(array $f, User $user)
    {
        $q = \App\Support\RequerimentoServidor::restringir(Processo::query()->with('tipoProcesso:id,nome'), $user);
        // Manifestante sigiloso: só a ouvidoria acha o processo pelo nome/CPF dele (TR 4.33.27)
        $sigilo = ! (Auth::user()->super_admin || Auth::user()->can('ouvidoria.gerir'));
        $doRequerente = fn ($w) => $sigilo ? $w->where('identificacao', '<>', 'sigilosa') : $w;
        foreach ($this->termos($f['q']) as $t) {
            $l = $this->like($t);
            $q->where(fn (Builder $w) => $w->where('numero_protocolo', 'ilike', $l)->orWhere('assunto', 'ilike', $l)
                ->orWhere('descricao', 'ilike', $l)->orWhere(fn ($r) => $doRequerente($r->where('requerente_nome', 'ilike', $l)))
                ->orWhereRaw('dados_formulario::text ilike ?', [$l])->orWhere('observacao_conclusao', 'ilike', $l)
                ->orWhereHas('tramitacoes', fn ($x) => $x->where('despacho', 'ilike', $l)->orWhere('parecer', 'ilike', $l))
                ->orWhereHas('comentarios', fn ($x) => $x->where('texto', 'ilike', $l))
                ->orWhereHas('anexos', fn ($x) => $x->where('nome', 'ilike', $l)->orWhere('texto_indexado', 'ilike', $l)));
        }
        $this->comuns($q, $f, 'numero_protocolo');
        if ($f['assuntos']) {
            $q->whereIn('tipo_processo_id', $f['assuntos']);
        }
        if (($setores = $this->setoresDoEscopo($f, $user)) !== null) {
            $q->whereHas('tramitacoes', fn ($x) => $x->whereIn('destino_unidade_id', $setores));
        }
        if ($f['interno']) {
            $q->where(fn ($w) => $w->where('aberto_por', $f['interno'])->orWhereHas('tramitacoes', fn ($x) => $x->where('remetente_id', $f['interno'])));
        }
        if ($f['externo'] !== '') {
            $d = preg_replace('/\D/', '', $f['externo']);
            $q->where(fn ($w) => $w->where('requerente_nome', 'ilike', $this->like($f['externo']))
                ->when(strlen($d) >= 5, fn ($w) => $w->orWhereRaw("regexp_replace(coalesce(requerente_cpf,''), '[^0-9]', '', 'g') like ?", ["%{$d}%"])));
            $doRequerente($q);
        }
        $this->etiqueta($q, $f, 'processo');
        // Situação geral (TR 4.15.8): parcialmente arquivado = algum setor já resolveu a parte dele
        $resolvido = fn ($x) => $x->where('frente', '>', 1)->where('status', 'resolvido');
        match ($f['situacao']) {
            'arquivado' => $q->whereIn('status', self::FINAIS),
            'parcial'   => $q->whereNotIn('status', self::FINAIS)->whereHas('tramitacoes', $resolvido),
            'aberto'    => $q->whereNotIn('status', self::FINAIS)->whereDoesntHave('tramitacoes', $resolvido),
            default     => null,
        };

        return $q->orderByDesc('created_at')->limit(self::LIMITE)->get()->map(fn (Processo $p) => [
            'tipo' => 'processo', 'id' => $p->id, 'numero' => $p->numero_protocolo, 'titulo' => $p->assunto,
            'detalhe' => $p->tipoProcesso?->nome . ($p->requerente_nome ? ' · ' . ($sigilo && $p->identificacao === 'sigilosa' ? 'Manifestante sigiloso' : $p->requerente_nome) : ''),
            'situacao' => $p->status, 'data' => $p->created_at?->toIso8601String(), 'link' => "/processos/{$p->id}",
        ]);
    }

    private function memorandos(array $f, User $user)
    {
        $setores = Setores::doUsuario($user);
        $q = Memorando::query();
        // Só os que o usuário vê (remetente, destinatário pessoal ou do setor, tramitação)
        if (! $user->super_admin && ! $user->acesso_geral_ug) {
            $q->where(fn ($w) => $w->where('remetente_id', $user->id)->orWhere('digitador_id', $user->id)
                ->orWhereHas('destinatarios', fn ($x) => $x->where('usuario_id', $user->id)->orWhereIn('unidade_id', $setores ?: [-1]))
                ->orWhereHas('tramitacoes', fn ($x) => $x->where('destino_usuario_id', $user->id)->orWhereIn('destino_unidade_id', $setores ?: [-1])));
        }
        foreach ($this->termos($f['q']) as $t) {
            $l = $this->like($t);
            $q->where(fn ($w) => $w->where('numero', 'ilike', $l)->orWhere('assunto', 'ilike', $l)->orWhere('conteudo', 'ilike', $l)
                ->orWhereHas('tramitacoes', fn ($x) => $x->where('parecer', 'ilike', $l))
                ->orWhereHas('anexos', fn ($x) => $x->where('nome', 'ilike', $l)));
        }
        $this->comuns($q, $f, 'numero');
        if (($s = $this->setoresDoEscopo($f, $user)) !== null && ($f['setor'] || $f['escopo'] === 'atual')) {
            $q->where(fn ($w) => $w->whereHas('destinatarios', fn ($x) => $x->whereIn('unidade_id', $s))
                ->orWhereHas('tramitacoes', fn ($x) => $x->whereIn('destino_unidade_id', $s)));
        }
        if ($f['interno']) {
            $q->where('remetente_id', $f['interno']);
        }
        if ($f['externo'] !== '') {
            $q->whereRaw('1 = 0');   // memorando é interno
        }
        $this->etiqueta($q, $f, 'memorando');
        $this->situacaoSimples($q, $f);

        return $q->orderByDesc('created_at')->limit(self::LIMITE)->get()->map(fn (Memorando $m) => [
            'tipo' => 'memorando', 'id' => $m->id, 'numero' => $m->numero, 'titulo' => $m->assunto, 'detalhe' => $m->setor_origem,
            'situacao' => $m->status, 'data' => $m->created_at?->toIso8601String(), 'link' => "/memorandos/{$m->id}",
        ]);
    }

    private function oficios(array $f, User $user)
    {
        $q = Oficio::query();
        if (! $user->super_admin) {
            $q->where(fn ($w) => $w->where('remetente_id', $user->id)->orWhere('digitador_id', $user->id));
        }
        foreach ($this->termos($f['q']) as $t) {
            $l = $this->like($t);
            $q->where(fn ($w) => $w->where('numero', 'ilike', $l)->orWhere('assunto', 'ilike', $l)->orWhere('conteudo', 'ilike', $l)
                ->orWhere('destinatario_nome', 'ilike', $l)->orWhere('destinatario_orgao', 'ilike', $l)
                ->orWhereHas('anexos', fn ($x) => $x->where('nome', 'ilike', $l)));
        }
        $this->comuns($q, $f, 'numero');
        if ($f['interno']) {
            $q->where(fn ($w) => $w->where('remetente_id', $f['interno'])->orWhere('digitador_id', $f['interno']));
        }
        if ($f['externo'] !== '') {
            $l = $this->like($f['externo']);
            $q->where(fn ($w) => $w->where('destinatario_nome', 'ilike', $l)->orWhere('destinatario_email', 'ilike', $l)->orWhere('destinatario_orgao', 'ilike', $l));
        }
        $this->etiqueta($q, $f, 'oficio');
        $this->situacaoSimples($q, $f);

        return $q->orderByDesc('created_at')->limit(self::LIMITE)->get()->map(fn (Oficio $o) => [
            'tipo' => 'oficio', 'id' => $o->id, 'numero' => $o->numero, 'titulo' => $o->assunto,
            'detalhe' => trim("{$o->destinatario_nome} {$o->destinatario_orgao}"),
            'situacao' => $o->status, 'data' => $o->created_at?->toIso8601String(), 'link' => "/oficios/{$o->id}",
        ]);
    }

    /** Número (com ou sem o ano: "123/2026"), ano, período (TR 4.15.6) e classificação com as de baixo (TR 4.27.9). */
    private function comuns(Builder $q, array $f, string $colunaNumero): void
    {
        if ($f['classificacao']) {
            $q->whereIn($q->getModel()->qualifyColumn('classificacao_id'), \App\Support\Classificacao::comDescendentes($f['classificacao']));
        }
        if ($f['numero'] !== '') {
            $q->where($colunaNumero, 'ilike', $this->like($f['numero']));
        }
        if ($f['ano']) {
            $q->whereYear('created_at', $f['ano']);
        }
        if ($f['de']) {
            $q->where('created_at', '>=', \Carbon\Carbon::parse($f['de'], \App\Support\Expediente::FUSO)->startOfDay()->utc());
        }
        if ($f['ate']) {
            $q->where('created_at', '<=', \Carbon\Carbon::parse($f['ate'], \App\Support\Expediente::FUSO)->endOfDay()->utc());
        }
    }

    private function etiqueta(Builder $q, array $f, string $tipo): void
    {
        if ($f['etiqueta'] && Etiquetas::doUsuario(Auth::user())->contains('id', $f['etiqueta'])) {
            $q->whereIn($q->getModel()->getQualifiedKeyName(), DB::table('proc_etiquetados')->where('tipo', $tipo)
                ->whereIn('etiqueta_id', Etiquetas::comFilhas($f['etiqueta']))->select('item_id'));
        }
    }

    /** Memorando e ofício não têm frentes: em aberto ou arquivado. */
    private function situacaoSimples(Builder $q, array $f): void
    {
        match ($f['situacao']) {
            'arquivado' => $q->whereIn('status', self::FINAIS),
            'aberto'    => $q->whereNotIn('status', self::FINAIS),
            'parcial'   => $q->whereRaw('1 = 0'),
            default     => null,
        };
    }
}
