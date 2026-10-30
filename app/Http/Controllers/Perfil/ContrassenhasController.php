<?php

declare(strict_types=1);

namespace App\Http\Controllers\Perfil;

use App\Http\Controllers\Controller;
use App\Models\Contrassenha;
use App\Models\Processo\Circular;
use App\Models\Processo\Memorando;
use App\Models\Processo\Oficio;
use App\Models\User;
use App\Support\HistoricoUsuario;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

/**
 * Meu perfil › Contrassenhas (TR 4.18): o autorizador gera, acompanha e
 * cancela as senhas que permitem a um servidor emitir documentos em nome
 * dele, e vê o relatório do que foi emitido; o digitador vê o que recebeu.
 */
class ContrassenhasController extends Controller
{
    /** Sem 0/O, 1/I/L: o código é lido e digitado por outra pessoa. */
    private const ALFABETO = 'ABCDEFGHJKMNPQRSTUVWXYZ23456789';

    public function index(Request $request)
    {
        $eu = Auth::id();
        $fmt = fn ($d) => $d?->setTimezone('America/Sao_Paulo')->format('d/m/Y H:i');

        $minhas = Contrassenha::with('digitador:id,name')->where('autorizador_id', $eu)->latest('id')->limit(100)->get()
            ->map(fn (Contrassenha $c) => [
                'id' => $c->id, 'digitador' => $c->digitador?->name, 'final' => $c->final,
                'usos' => $c->usos, 'usos_max' => $c->usos_max, 'valida_ate' => $fmt($c->valida_ate),
                'situacao' => $c->situacao(), 'observacao' => $c->observacao, 'criada_em' => $fmt($c->created_at),
            ]);

        $recebidas = Contrassenha::with('autorizador:id,name')->where('digitador_id', $eu)->latest('id')->limit(50)->get()
            ->map(fn (Contrassenha $c) => [
                'id' => $c->id, 'autorizador' => $c->autorizador?->name, 'final' => $c->final,
                'usos' => $c->usos, 'usos_max' => $c->usos_max, 'valida_ate' => $fmt($c->valida_ate), 'situacao' => $c->situacao(),
            ]);

        return Inertia::render('GED/Perfil/Contrassenhas', [
            'minhas'     => $minhas,
            'recebidas'  => $recebidas,
            'relatorio'  => $this->relatorio($eu),
            'servidores' => User::paraAssinatura($eu)->reject(fn ($u) => $u['voce'])->values()->map(fn ($u) => ['id' => $u['id'], 'name' => $u['name']]),
            'nova'       => $request->session()->get('contrassenha_nova'),   // mostrada uma vez
        ]);
    }

    public function store(Request $request)
    {
        $dados = $request->validate([
            'digitador_id' => ['required', 'integer', Rule::exists('users', 'id')->where('tipo', 'interno')->whereNull('suspenso_em'), Rule::notIn([Auth::id()])],
            'usos_max'     => ['required', 'integer', 'min:1', 'max:100'],
            'dias'         => ['required', 'integer', 'min:1', 'max:90'],
            'observacao'   => ['nullable', 'string', 'max:300'],
        ], ['digitador_id.not_in' => 'A contrassenha é para outro servidor emitir em seu nome.'], ['digitador_id' => 'servidor', 'usos_max' => 'usos', 'dias' => 'validade']);

        $codigo = '';
        for ($i = 0; $i < 8; $i++) {
            $codigo .= self::ALFABETO[random_int(0, strlen(self::ALFABETO) - 1)];
        }

        $c = Contrassenha::create([
            'autorizador_id' => Auth::id(),
            'digitador_id'   => $dados['digitador_id'],
            'codigo_hash'    => Hash::make($codigo),
            'final'          => substr($codigo, -4),
            'usos_max'       => $dados['usos_max'],
            'valida_ate'     => now()->addDays($dados['dias'])->endOfDay(),
            'observacao'     => $dados['observacao'] ?? null,
        ]);
        $digitador = User::find($dados['digitador_id']);
        HistoricoUsuario::registrar(Auth::user(), 'Contrassenha', null,
            "Gerada para {$digitador?->name} ({$c->usos_max} uso(s), até {$c->valida_ate->setTimezone('America/Sao_Paulo')->format('d/m/Y')})");

        return back()->with('contrassenha_nova', [
            'codigo'    => substr($codigo, 0, 4) . '-' . substr($codigo, 4),
            'digitador' => $digitador?->name,
        ]);
    }

    public function cancelar(int $id)
    {
        $c = Contrassenha::where('autorizador_id', Auth::id())->findOrFail($id);
        if (! $c->cancelada_em) {
            $c->update(['cancelada_em' => now()]);
            HistoricoUsuario::registrar(Auth::user(), 'Contrassenha', "…{$c->final}", 'Cancelada');
        }

        return back()->with('success', 'Contrassenha cancelada.');
    }

    /** Documentos emitidos em nome do autorizador por contrassenha (TR 4.18.5). */
    private function relatorio(int $autorizadorId): array
    {
        $linhas = [];
        foreach ([[Oficio::class, 'Ofício', 'oficios'], [Memorando::class, 'Memorando', 'memorandos'], [Circular::class, 'Circular', 'circulares']] as [$modelo, $tipo, $rota]) {
            $modelo::query()->withoutGlobalScopes()->with('digitador:id,name')
                ->where('remetente_id', $autorizadorId)->whereNotNull('contrassenha_id')
                ->latest('id')->limit(100)->get(['id', 'numero', 'assunto', 'digitador_id', 'created_at'])
                ->each(function ($d) use (&$linhas, $tipo, $rota) {
                    $linhas[] = [
                        'tipo' => $tipo, 'numero' => $d->numero, 'assunto' => $d->assunto, 'link' => "/{$rota}/{$d->id}",
                        'digitador' => $d->digitador?->name, 'quando' => $d->created_at?->setTimezone('America/Sao_Paulo')->format('d/m/Y H:i'),
                        'ordem' => $d->created_at?->getTimestamp() ?? 0,
                    ];
                });
        }
        usort($linhas, fn ($a, $b) => $b['ordem'] <=> $a['ordem']);

        return $linhas;
    }
}
