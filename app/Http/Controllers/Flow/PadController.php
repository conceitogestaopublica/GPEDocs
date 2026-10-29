<?php

declare(strict_types=1);

namespace App\Http\Controllers\Flow;

use App\Http\Controllers\Controller;
use App\Models\Processo\PadInfracao;
use App\Models\Processo\Processo;
use App\Models\User;
use App\Support\Ouvidoria;
use App\Support\RequerimentoServidor;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

/**
 * PAD — processo administrativo disciplinar (TR 4.40): lista dos PADs que o usuário pode ver,
 * tipos de infração (com o setor que recebe o processo) e a comissão processante.
 */
class PadController extends Controller
{
    public const PAPEIS = ['presidente' => 'Presidente', 'membro' => 'Membro', 'secretario' => 'Secretário(a)'];

    public function index(Request $request)
    {
        $busca = trim((string) $request->input('busca'));
        $lista = RequerimentoServidor::restringir(Processo::query(), Auth::user())
            ->whereHas('tipoProcesso', fn ($t) => $t->where('modalidade', 'pad'))
            ->with(['padInfracao:id,nome,gravidade', 'etapaAtual:id,setor_destino'])
            ->when($busca !== '', fn ($q) => $q->where(fn ($w) => $w->where('numero_protocolo', 'ilike', "%{$busca}%")->orWhere('assunto', 'ilike', "%{$busca}%")))
            ->orderByRaw("case when status in ('concluido','arquivado','cancelado') then 1 else 0 end")->orderByDesc('id')
            ->paginate(20)->withQueryString()
            ->through(fn (Processo $p) => [
                'id' => $p->id, 'numero' => $p->numero_protocolo, 'assunto' => $p->assunto, 'status' => $p->status,
                'infracao' => $p->padInfracao?->nome, 'gravidade' => PadInfracao::GRAVIDADES[$p->padInfracao?->gravidade] ?? null,
                'setor' => $p->etapaAtual?->setor_destino, 'aberto_em' => $p->created_at?->toIso8601String(),
                'comissao' => DB::table('proc_pad_comissao as c')->join('users as u', 'u.id', '=', 'c.user_id')->where('c.processo_id', $p->id)
                    ->orderByRaw("case c.papel when 'presidente' then 0 when 'membro' then 1 else 2 end")->pluck('u.name')->all(),
                'proximo_prazo' => DB::table('proc_prazos')->where('processo_id', $p->id)->whereNull('concluido_em')->orderBy('vence_em')->first(['titulo', 'vence_em']),
            ]);

        return Inertia::render('GED/Flow/Pad', [
            'lista' => $lista, 'busca' => $busca, 'pode_gerir' => Auth::user()->super_admin || Auth::user()->can('pad.gerir'),
        ]);
    }

    public function infracoes()
    {
        $this->exigirGestor();

        return Inertia::render('GED/Flow/PadInfracoes', [
            'infracoes' => PadInfracao::with('unidade:id,nome')->orderByDesc('ativo')->orderBy('nome')->get(),
            'gravidades' => PadInfracao::GRAVIDADES,
            'unidades' => \App\Models\UgOrganograma::where('ativo', true)->orderBy('nome')->get(['id', 'nome']),
        ]);
    }

    public function salvarInfracao(Request $request, ?int $id = null)
    {
        $this->exigirGestor();
        $d = $request->validate([
            'nome' => ['required', 'string', 'max:200'], 'gravidade' => ['required', 'in:leve,media,grave'],
            'base_legal' => ['nullable', 'string', 'max:255'], 'unidade_id' => ['nullable', 'integer', 'exists:ug_organograma,id'],
            'ativo' => ['nullable', 'boolean'],
        ]);
        $d['ativo'] = $request->boolean('ativo', true);
        $id ? PadInfracao::findOrFail($id)->update($d) : PadInfracao::create($d);

        return back()->with('success', 'Tipo de infração salvo.');
    }

    /** Inclui ou troca o papel de um membro da comissão. */
    public function comissao(Request $request, int $id)
    {
        $this->exigirGestor();
        $p = Processo::findOrFail($id);
        $d = $request->validate(['user_id' => ['required', 'integer', 'exists:users,id'], 'papel' => ['required', 'in:presidente,membro,secretario']]);
        DB::transaction(function () use ($p, $d, $request) {
            if ($d['papel'] === 'presidente') {   // um presidente por comissão
                DB::table('proc_pad_comissao')->where('processo_id', $p->id)->where('papel', 'presidente')->update(['papel' => 'membro']);
            }
            DB::table('proc_pad_comissao')->updateOrInsert(['processo_id' => $p->id, 'user_id' => $d['user_id']],
                ['papel' => $d['papel'], 'updated_at' => now(), 'created_at' => now()]);
            DB::table('proc_historico')->insert(['processo_id' => $p->id, 'usuario_id' => Auth::id(), 'acao' => 'comissao_pad',
                'detalhes' => json_encode(['membro' => User::find($d['user_id'])?->name, 'papel' => self::PAPEIS[$d['papel']]], JSON_UNESCAPED_UNICODE),
                'ip' => $request->ip(), 'created_at' => now()]);
        });

        return back()->with('success', 'Comissão atualizada.');
    }

    public function removerMembro(Request $request, int $id, int $userId)
    {
        $this->exigirGestor();
        DB::table('proc_pad_comissao')->where('processo_id', $id)->where('user_id', $userId)->delete();
        DB::table('proc_historico')->insert(['processo_id' => $id, 'usuario_id' => Auth::id(), 'acao' => 'comissao_pad',
            'detalhes' => json_encode(['removido' => User::find($userId)?->name], JSON_UNESCAPED_UNICODE), 'ip' => $request->ip(), 'created_at' => now()]);

        return back()->with('success', 'Membro retirado da comissão.');
    }

    /** Dados do PAD para a tela do processo. */
    public static function doProcesso(Processo $p): ?array
    {
        if ($p->tipoProcesso?->modalidade !== 'pad') {
            return null;
        }
        $i = $p->padInfracao;

        return [
            'infracao' => $i ? ['nome' => $i->nome, 'gravidade' => PadInfracao::GRAVIDADES[$i->gravidade] ?? $i->gravidade, 'base_legal' => $i->base_legal] : null,
            'comissao' => DB::table('proc_pad_comissao as c')->join('users as u', 'u.id', '=', 'c.user_id')->where('c.processo_id', $p->id)
                ->orderByRaw("case c.papel when 'presidente' then 0 when 'membro' then 1 else 2 end")
                ->get(['u.id', 'u.name', 'c.papel'])->map(fn ($m) => ['id' => $m->id, 'nome' => $m->name, 'papel' => self::PAPEIS[$m->papel] ?? $m->papel])->all(),
            'papeis' => self::PAPEIS,
            'pode_gerir' => Auth::user()->super_admin || Auth::user()->can('pad.gerir'),
        ];
    }

    private function exigirGestor(): void
    {
        abort_unless(Auth::user()->super_admin || Auth::user()->can('pad.gerir'), 403);
    }
}
