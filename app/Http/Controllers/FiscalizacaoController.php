<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Processo\Processo;
use App\Models\Processo\ProcessoAnexo;
use App\Models\Processo\TipoEtapa;
use App\Models\Processo\TipoProcesso;
use App\Models\Processo\Tramitacao;
use App\Support\Expediente;
use App\Support\RequerimentoServidor;
use App\Tenant\TenantStorage;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Fiscalização de obras e posturas (TR 4.49): gera o processo de fiscalização a partir de outro
 * (ex.: denúncia da ouvidoria — o denunciante sigiloso ou anônimo não é copiado), registra as
 * diligências com fotos/plantas e ponto no mapa, e envia cópia de documento por e-mail (4.49.8).
 */
class FiscalizacaoController extends Controller
{
    public const RESULTADOS = ['regular' => 'Situação regular', 'irregular' => 'Irregularidade constatada', 'notificado' => 'Notificado',
        'autuado' => 'Autuado', 'embargado' => 'Embargado / interditado', 'nao_localizado' => 'Endereço não localizado'];

    /** Abre a fiscalização ligada ao processo de origem. */
    public function gerar(Request $request, int $id)
    {
        $origem = $this->processo($id);
        $d = $request->validate(['tipo_processo_id' => ['required', 'integer', 'exists:proc_tipos_processo,id'],
            'setor_destino' => ['required', 'integer', 'exists:ug_organograma,id'], 'motivo' => ['required', 'string', 'max:2000'],
            'copiar_anexos' => ['nullable', 'boolean']]);
        $tipo = TipoProcesso::findOrFail($d['tipo_processo_id']);
        $unidade = \App\Models\UgOrganograma::findOrFail($d['setor_destino']);
        $sigilo = in_array($origem->identificacao, ['sigilosa', 'anonima'], true);
        $u = Auth::user();

        $novo = DB::transaction(function () use ($origem, $d, $tipo, $unidade, $sigilo, $u, $request) {
            $ano = date('Y');
            DB::select('select pg_advisory_xact_lock(?)', [crc32("prot-{$tipo->id}-{$ano}")]);
            $ultimo = Processo::withoutGlobalScopes()->where('tipo_processo_id', $tipo->id)->whereYear('created_at', $ano)
                ->max(DB::raw("CAST(SPLIT_PART(numero_protocolo, '/', 2) AS INTEGER)"));
            $p = Processo::create([
                'ug_id' => $origem->ug_id, 'numero_protocolo' => $tipo->sigla . '-' . $ano . '/' . str_pad((string) (((int) $ultimo) + 1), 6, '0', STR_PAD_LEFT),
                'tipo_processo_id' => $tipo->id, 'assunto' => mb_substr("Fiscalização — {$origem->assunto}", 0, 250), 'descricao' => $d['motivo'],
                'dados_formulario' => ['Origem' => $origem->numero_protocolo, 'Fato' => $origem->descricao ?: $origem->assunto],
                // Denunciante sigiloso/anônimo fica só na origem (TR 4.33.27)
                'requerente_nome' => $sigilo ? null : $origem->requerente_nome, 'requerente_email' => $sigilo ? null : $origem->requerente_email,
                'requerente_cpf' => $sigilo ? null : $origem->requerente_cpf, 'requerente_telefone' => $sigilo ? null : $origem->requerente_telefone,
                'latitude' => $origem->latitude, 'longitude' => $origem->longitude, 'local_endereco' => $origem->local_endereco,
                'setor_origem' => $origem->numero_protocolo, 'status' => 'aberto', 'prioridade' => $origem->prioridade ?: 'normal', 'aberto_por' => $u->id,
                'canal' => 'interno', 'processo_origem_id' => $origem->id,
            ]);
            $etapa = TipoEtapa::where('tipo_processo_id', $tipo->id)->orderBy('ordem')->first();
            $t = Tramitacao::create(['processo_id' => $p->id, 'tipo_etapa_id' => $etapa?->id, 'ordem' => 1, 'setor_origem' => $origem->numero_protocolo,
                'setor_destino' => $unidade->nome, 'destino_unidade_id' => $unidade->id, 'remetente_id' => $u->id, 'destinatario_id' => $etapa?->responsavel_id,
                'status' => 'pendente', 'despachado_em' => now(), 'sla_horas' => $etapa?->sla_horas ?? $tipo->sla_padrao_horas,
                'prazo' => Expediente::prazo($tipo, $etapa?->sla_horas ?? $tipo->sla_padrao_horas ?? 72)]);
            $p->update(['etapa_atual_id' => $t->id]);
            if ($request->boolean('copiar_anexos', true)) {
                foreach (ProcessoAnexo::where('processo_id', $origem->id)->get() as $a) {
                    if (! Storage::disk('documentos')->exists($a->arquivo_path)) {
                        continue;
                    }
                    $path = TenantStorage::pasta('processos') . '/' . Str::uuid() . '.' . (pathinfo($a->arquivo_path, PATHINFO_EXTENSION) ?: 'bin');
                    Storage::disk('documentos')->copy($a->arquivo_path, $path);
                    ProcessoAnexo::create(['processo_id' => $p->id, 'tramitacao_id' => $t->id, 'nome' => $a->nome, 'arquivo_path' => $path,
                        'tamanho' => $a->tamanho, 'mime_type' => $a->mime_type, 'hash_sha256' => $a->hash_sha256, 'enviado_por' => $u->id]);
                }
            }
            foreach ([[$origem->id, 'fiscalizacao_gerada', ['fiscalizacao' => $p->numero_protocolo]], [$p->id, 'gerado_de', ['origem' => $origem->numero_protocolo]]] as [$pid, $acao, $det]) {
                DB::table('proc_historico')->insert(['processo_id' => $pid, 'usuario_id' => $u->id, 'acao' => $acao,
                    'detalhes' => json_encode($det + ['motivo' => $d['motivo']], JSON_UNESCAPED_UNICODE), 'ip' => $request->ip(), 'created_at' => now()]);
            }

            return $p;
        });

        return redirect("/processos/{$novo->id}")->with('success', "Fiscalização {$novo->numero_protocolo} aberta a partir de {$origem->numero_protocolo}.");
    }

    /** Diligência com fotos/plantas e ponto no mapa (TR 4.49.2). */
    public function diligencia(Request $request, int $id)
    {
        $p = $this->processo($id);
        $d = $request->validate(['realizada_em' => ['required', 'date'], 'resultado' => ['required', 'in:' . implode(',', array_keys(self::RESULTADOS))],
            'constatacao' => ['required', 'string', 'max:5000'], 'latitude' => ['nullable', 'numeric', 'between:-90,90'], 'longitude' => ['nullable', 'numeric', 'between:-180,180'],
            'arquivos' => ['nullable', 'array', 'max:20'], 'arquivos.*' => ['file', 'max:20480', 'mimes:jpg,jpeg,png,webp,heic,pdf,dwg']]);
        $u = Auth::user();
        $quando = \Carbon\Carbon::parse($d['realizada_em'], Expediente::FUSO);
        DB::transaction(function () use ($p, $d, $u, $request, $quando) {
            $ids = [];
            foreach ((array) $request->file('arquivos', []) as $i => $arq) {
                $a = ProcessoAnexo::create(['processo_id' => $p->id, 'tramitacao_id' => $p->etapa_atual_id,
                    'nome' => mb_substr('Diligência ' . $quando->format('d-m-Y') . ' — ' . $arq->getClientOriginalName(), 0, 255),
                    'arquivo_path' => $arq->store(TenantStorage::pasta('processos'), 'documentos'), 'tamanho' => $arq->getSize(),
                    'mime_type' => $arq->getMimeType() ?: 'application/octet-stream', 'hash_sha256' => hash_file('sha256', $arq->getRealPath()), 'enviado_por' => $u->id]);
                $ids[] = $a->id;
            }
            DB::table('proc_diligencias')->insert(['processo_id' => $p->id, 'fiscal_id' => $u->id, 'realizada_em' => $quando->utc(), 'resultado' => $d['resultado'],
                'constatacao' => $d['constatacao'], 'latitude' => $d['latitude'] ?? null, 'longitude' => $d['longitude'] ?? null, 'anexos' => json_encode($ids),
                'created_at' => now(), 'updated_at' => now()]);
            if (! $p->latitude && ! empty($d['latitude'])) {
                $p->forceFill(['latitude' => $d['latitude'], 'longitude' => $d['longitude']])->saveQuietly();
            }
            DB::table('proc_historico')->insert(['processo_id' => $p->id, 'usuario_id' => $u->id, 'acao' => 'diligencia',
                'detalhes' => json_encode(['resultado' => self::RESULTADOS[$d['resultado']], 'arquivos' => count($ids), 'constatacao' => mb_substr($d['constatacao'], 0, 300)], JSON_UNESCAPED_UNICODE),
                'ip' => $request->ip(), 'created_at' => now()]);
        });

        return back()->with('success', 'Diligência registrada.');
    }

    /** Cópia de um anexo por e-mail ao requerente ou a terceiro (TR 4.49.8). */
    public function enviarCopia(Request $request, int $anexo)
    {
        $a = ProcessoAnexo::findOrFail($anexo);
        $p = $this->processo($a->processo_id);
        $d = $request->validate(['email' => ['required', 'email', 'max:150'], 'mensagem' => ['nullable', 'string', 'max:2000']]);
        abort_unless(Storage::disk('documentos')->exists($a->arquivo_path), 404);
        abort_if($a->tamanho > 15 * 1024 * 1024, 422, 'Arquivo grande demais para e-mail (máx. 15 MB): use o link seguro.');
        $ok = \App\Support\RastreioEmail::enviar(new \App\Mail\CopiaDocumento($p, $a->nome, Storage::disk('documentos')->get($a->arquivo_path), $a->mime_type ?: 'application/octet-stream', $d['mensagem'] ?? null),
            $d['email'], 'cidadao', "Cópia: {$a->nome}", 'processo', $p->id, Auth::id());
        DB::table('proc_historico')->insert(['processo_id' => $p->id, 'usuario_id' => Auth::id(), 'acao' => 'copia_enviada',
            'detalhes' => json_encode(['anexo' => $a->nome, 'para' => $d['email']], JSON_UNESCAPED_UNICODE), 'ip' => $request->ip(), 'created_at' => now()]);

        return back()->with($ok ? 'success' : 'error', $ok ? "Cópia de \"{$a->nome}\" enviada para {$d['email']}." : 'Não foi possível enviar o e-mail.');
    }

    /** Dados para a tela do processo. */
    public static function doProcesso(Processo $p): array
    {
        return [
            'origem' => $p->processo_origem_id ? Processo::withoutGlobalScopes()->find($p->processo_origem_id, ['id', 'numero_protocolo', 'assunto']) : null,
            'geradas' => Processo::withoutGlobalScopes()->where('processo_origem_id', $p->id)->orderBy('id')->get(['id', 'numero_protocolo', 'status']),
            'eh_fiscalizacao' => $p->tipoProcesso?->modalidade === 'fiscalizacao',
            'diligencias' => DB::table('proc_diligencias as d')->join('users as u', 'u.id', '=', 'd.fiscal_id')->where('d.processo_id', $p->id)->orderByDesc('d.realizada_em')
                ->get(['d.id', 'd.realizada_em', 'd.resultado', 'd.constatacao', 'd.latitude', 'd.longitude', 'd.anexos', 'u.name as fiscal'])
                ->map(fn ($x) => array_merge((array) $x, ['resultado_rotulo' => self::RESULTADOS[$x->resultado] ?? $x->resultado,
                    'anexos' => json_decode((string) $x->anexos, true) ?: [],
                    // gravado em UTC sem fuso: manda com o fuso para a tela mostrar a hora local certa
                    'realizada_em' => \Carbon\Carbon::parse($x->realizada_em, 'UTC')->toIso8601String()])),
            'resultados' => self::RESULTADOS,
            'tipos_fiscalizacao' => TipoProcesso::where('ativo', true)->where('modalidade', 'fiscalizacao')->orderBy('nome')->get(['id', 'nome']),
        ];
    }

    private function processo(int $id): Processo
    {
        $p = Processo::with('tipoProcesso')->findOrFail($id);
        abort_unless(RequerimentoServidor::podeVer(Auth::user(), $p), 403);
        abort_unless(Auth::user()->super_admin || Auth::user()->can('processo.tramitar'), 403);

        return $p;
    }
}