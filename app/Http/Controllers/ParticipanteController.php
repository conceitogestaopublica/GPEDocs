<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Processo\Processo;
use App\Services\ConviteAssinaturaService;
use App\Support\ExternoDoProcesso;
use App\Support\RastreioEmail;
use App\Support\RequerimentoServidor;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

/**
 * Participantes do processo (TR 4.48.30, 4.48.31) — proprietário, arquiteto, engenheiro,
 * responsável técnico — com convite por e-mail para o próprio participante confirmar os dados,
 * e a visibilidade de cada anexo para o externo (TR 4.48.20).
 */
class ParticipanteController extends Controller
{
    public function salvar(Request $request, int $id, ?int $participante = null)
    {
        $p = $this->processo($id);
        $d = $this->validar($request);
        $dados = $d + ['updated_at' => now()];
        if ($participante) {
            DB::table('proc_participantes')->where('processo_id', $p->id)->where('id', $participante)->update($dados);
        } else {
            DB::table('proc_participantes')->insert($dados + ['processo_id' => $p->id, 'adicionado_por' => Auth::id(), 'created_at' => now()]);
        }
        $this->historico($p, 'participante', ['nome' => $d['nome'], 'papel' => ExternoDoProcesso::PAPEIS[$d['papel']]]);

        return back()->with('success', 'Participante salvo.');
    }

    public function remover(int $id, int $participante)
    {
        $p = $this->processo($id);
        DB::table('proc_participantes')->where('processo_id', $p->id)->where('id', $participante)->delete();

        return back()->with('success', 'Participante retirado.');
    }

    /** Convite para autocadastro (TR 4.48.31). */
    public function convidar(int $id, int $participante)
    {
        $p = $this->processo($id);
        $pt = DB::table('proc_participantes')->where('processo_id', $p->id)->where('id', $participante)->first() ?? abort(404);
        abort_unless($pt->email && filter_var($pt->email, FILTER_VALIDATE_EMAIL), 422, 'Informe o e-mail do participante.');
        $token = $pt->convite_token ?: (string) Str::uuid();
        DB::table('proc_participantes')->where('id', $pt->id)->update(['convite_token' => $token, 'convite_enviado_em' => now()]);
        $ok = RastreioEmail::enviar(new \App\Mail\ConviteParticipante($p, $pt->nome, ExternoDoProcesso::PAPEIS[$pt->papel] ?? $pt->papel, url("/participantes/convite/{$token}")),
            $pt->email, 'cidadao', "Convite: {$p->numero_protocolo}", 'processo', $p->id, Auth::id());

        return back()->with($ok ? 'success' : 'error', $ok ? "Convite enviado para {$pt->email}." : 'Não foi possível enviar o convite.');
    }

    /** Visibilidade do anexo para o externo (TR 4.48.20). */
    public function visibilidade(Request $request, int $anexo)
    {
        $a = \App\Models\Processo\ProcessoAnexo::findOrFail($anexo);
        $this->processo($a->processo_id);
        $d = $request->validate(['externo' => ['required', Rule::in(array_keys(ExternoDoProcesso::VISIBILIDADE))]]);
        $a->update(['externo' => $d['externo']]);

        return back()->with('success', "\"{$a->nome}\": " . ExternoDoProcesso::VISIBILIDADE[$d['externo']] . '.');
    }

    /** Dados do card na tela do processo. */
    public static function doProcesso(Processo $p): array
    {
        return [
            'participantes' => DB::table('proc_participantes')->where('processo_id', $p->id)->orderBy('id')
                ->get(['id', 'papel', 'nome', 'cpf_cnpj', 'registro', 'email', 'telefone', 'convite_enviado_em', 'confirmado_em']),
            'papeis' => ExternoDoProcesso::PAPEIS, 'visibilidade' => ExternoDoProcesso::VISIBILIDADE,
            'deferido' => ExternoDoProcesso::deferido($p),
        ] + ExternoDoProcesso::painel($p);
    }

    // ── Convite público (sem login) ──────────────────────────────────

    public function convite(string $token)
    {
        [$pt, $p] = $this->doConvite($token);

        return Inertia::render('Publico/ConviteParticipante', [
            'token' => $token, 'confirmado' => (bool) $pt->confirmado_em,
            'participante' => ['nome' => $pt->nome, 'papel' => ExternoDoProcesso::PAPEIS[$pt->papel] ?? $pt->papel, 'cpf_cnpj' => $pt->cpf_cnpj,
                'registro' => $pt->registro, 'email' => $pt->email, 'telefone' => $pt->telefone],
            'processo' => ['numero' => $p->numero_protocolo, 'assunto' => $p->assunto, 'orgao' => \App\Models\Ug::withoutGlobalScopes()->find($p->ug_id)?->nome],
        ]);
    }

    public function confirmar(Request $request, string $token)
    {
        [$pt, $p] = $this->doConvite($token);
        $d = $request->validate([
            'nome' => ['required', 'string', 'max:200'], 'cpf_cnpj' => ['required', 'string', 'max:18', function ($a, $v, $f) {
                $n = preg_replace('/\D/', '', (string) $v);
                if (! (strlen($n) === 11 ? ConviteAssinaturaService::cpfValido($n) : \App\Services\Portal\CidadaoPortalService::documentoValido($n))) {
                    $f('CPF ou CNPJ inválido.');
                }
            }],
            'registro' => ['nullable', 'string', 'max:60'], 'email' => ['required', 'email', 'max:150'], 'telefone' => ['nullable', 'string', 'max:30'],
        ]);
        DB::table('proc_participantes')->where('id', $pt->id)->update(['nome' => $d['nome'], 'cpf_cnpj' => preg_replace('/\D/', '', $d['cpf_cnpj']),
            'registro' => $d['registro'] ?? null, 'email' => $d['email'], 'telefone' => $d['telefone'] ?? null, 'confirmado_em' => now(), 'updated_at' => now()]);
        DB::table('proc_historico')->insert(['processo_id' => $p->id, 'usuario_id' => null, 'acao' => 'participante_confirmou',
            'detalhes' => json_encode(['nome' => $d['nome'], 'papel' => ExternoDoProcesso::PAPEIS[$pt->papel] ?? $pt->papel], JSON_UNESCAPED_UNICODE),
            'ip' => $request->ip(), 'created_at' => now()]);

        return back()->with('success', 'Participação confirmada. Acompanhe o processo pelo portal com o seu CPF/CNPJ.');
    }

    private function doConvite(string $token): array
    {
        $pt = Str::isUuid($token) ? DB::table('proc_participantes')->where('convite_token', $token)->first() : null;
        abort_unless($pt, 404, 'Convite não encontrado.');

        return [$pt, Processo::withoutGlobalScopes()->findOrFail($pt->processo_id)];
    }

    private function validar(Request $request): array
    {
        $d = $request->validate([
            'papel' => ['required', Rule::in(array_keys(ExternoDoProcesso::PAPEIS))], 'nome' => ['required', 'string', 'max:200'],
            'cpf_cnpj' => ['nullable', 'string', 'max:18'], 'registro' => ['nullable', 'string', 'max:60'],
            'email' => ['nullable', 'email', 'max:150'], 'telefone' => ['nullable', 'string', 'max:30'],
        ]);
        $d['cpf_cnpj'] = ! empty($d['cpf_cnpj']) ? preg_replace('/\D/', '', $d['cpf_cnpj']) : null;

        return $d;
    }

    private function processo(int $id): Processo
    {
        $p = Processo::findOrFail($id);
        abort_unless(RequerimentoServidor::podeVer(Auth::user(), $p), 403);
        abort_unless(Auth::user()->super_admin || Auth::user()->can('processo.tramitar'), 403);

        return $p;
    }

    private function historico(Processo $p, string $acao, array $d): void
    {
        DB::table('proc_historico')->insert(['processo_id' => $p->id, 'usuario_id' => Auth::id(), 'acao' => $acao,
            'detalhes' => json_encode($d, JSON_UNESCAPED_UNICODE), 'ip' => request()->ip(), 'created_at' => now()]);
    }
}