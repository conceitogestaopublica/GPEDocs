<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Portal;

use App\Services\Portal\CidadaoPortalException;
use App\Models\Portal\Cidadao;
use App\Models\Processo\Pss;
use App\Models\Processo\PssAnexo;
use App\Models\Processo\PssInscricao;
use App\Services\Portal\AnexosSolicitacaoService;
use App\Support\CamposDinamicos;
use App\Tenant\TenantStorage;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

/**
 * Processo seletivo simplificado no portal (TR 4.41): editais publicados, inscrição do cidadão
 * logado com a ficha do assunto (campos e documentos), minhas inscrições com a resposta e a
 * ficha de inscrição em PDF.
 */
class PssController extends PortalApiController
{
    public function index(Request $request): JsonResponse
    {
        $ug = $this->ug($request);

        return response()->json(['editais' => Pss::withoutGlobalScope('ug')->with('tipoProcesso')->where('ug_id', $ug->id)
            ->where('publicado', true)->orderByDesc('inscricoes_inicio')->get()->map(fn (Pss $p) => self::edital($p))]);
    }

    public function inscrever(Request $request, int $id, AnexosSolicitacaoService $anexos): JsonResponse
    {
        $ug = $this->ug($request);
        $cidadao = Cidadao::where('id', $request->integer('cidadao_id'))->where('ativo', true)->first();
        if (! $cidadao) {
            return response()->json(['erro' => 'Entre no portal para se inscrever.'], 401);
        }
        $pss = Pss::withoutGlobalScope('ug')->with('tipoProcesso')->where('ug_id', $ug->id)->where('publicado', true)->findOrFail($id);
        if ($pss->situacao() !== 'aberto') {
            return response()->json(['erro' => 'As inscrições deste processo seletivo não estão abertas.'], 422);
        }
        $cargos = collect($pss->cargos ?? [])->pluck('nome')->all();
        $request->validate([
            'cargo' => [$cargos ? 'required' : 'nullable', 'string', ...($cargos ? [\Illuminate\Validation\Rule::in($cargos)] : [])],
            'telefone' => ['nullable', 'string', 'max:30'],
            'documentos_exigidos' => ['nullable', 'array', 'max:30'],
            'documentos_exigidos.*.indice' => ['required', 'integer', 'min:0'],
            'documentos_exigidos.*.conteudo' => ['required', 'string'],
            'anexos' => ['nullable', 'array', 'max:' . AnexosSolicitacaoService::MAX_ARQUIVOS],
            'anexos.*.nome' => ['required', 'string', 'max:200'], 'anexos.*.conteudo' => ['required', 'string'],
        ]);
        // Campos da ficha = formulário do assunto (TR 4.41.4, 4.41.5)
        $schema = $pss->tipoProcesso?->schema_formulario;
        if ($schema) {
            [$regras, $nomes] = CamposDinamicos::regras($schema, 'dados');
            Validator::make($request->all(), $regras, [], $nomes)->validate();
        }
        $cargo = $request->input('cargo') ?: null;
        if (PssInscricao::where('pss_id', $pss->id)->where('cpf', $cidadao->cpf)->where('cargo', $cargo)->exists()) {
            return response()->json(['erro' => 'Você já se inscreveu' . ($cargo ? " para o cargo {$cargo}" : '') . ' neste processo seletivo.'], 422);
        }
        try {
            $prontos = [...$anexos->prepararExigidos($pss->tipoProcesso?->anexos_exigidos, $request->input('documentos_exigidos', [])),
                ...$anexos->preparar($request->input('anexos', []))];
            $anexos->conferirTotal($prontos);
        } catch (CidadaoPortalException $e) {
            return response()->json(['erro' => $e->getMessage()], 422);
        }

        $i = DB::transaction(function () use ($pss, $cidadao, $cargo, $request, $schema, $prontos) {
            DB::select('select pg_advisory_xact_lock(?)', [crc32("pss-{$pss->id}")]);
            $seq = PssInscricao::where('pss_id', $pss->id)->count() + 1;
            $i = PssInscricao::create([
                'pss_id' => $pss->id, 'numero' => 'PSS' . $pss->id . '-' . str_pad((string) $seq, 5, '0', STR_PAD_LEFT), 'cargo' => $cargo,
                'cidadao_id' => $cidadao->id, 'nome' => $cidadao->nome, 'cpf' => $cidadao->cpf, 'email' => $cidadao->email,
                'telefone' => $request->input('telefone') ?: $cidadao->telefone, 'situacao' => 'recebida',
                'dados' => $schema ? CamposDinamicos::normalizar($schema, $request->input('dados')) : null,
            ]);
            foreach ($prontos as $a) {
                $path = TenantStorage::pasta('pss') . '/' . Str::uuid() . '.' . AnexosSolicitacaoService::TIPOS[$a['mime']];
                Storage::disk('documentos')->put($path, $a['bytes']);
                PssAnexo::create(['inscricao_id' => $i->id, 'nome' => $a['nome'], 'arquivo_path' => $path, 'tamanho' => strlen($a['bytes']),
                    'mime_type' => $a['mime'], 'hash_sha256' => hash('sha256', $a['bytes'])]);
            }

            return $i;
        });

        return response()->json(['inscricao' => self::inscricao($i->load('pss', 'anexos'))], 201);
    }

    public function minhas(Request $request): JsonResponse
    {
        $ug = $this->ug($request);
        $cidadao = Cidadao::where('id', $request->integer('cidadao_id'))->where('ativo', true)->firstOrFail();

        return response()->json(['inscricoes' => PssInscricao::with(['pss', 'anexos'])->where('cpf', $cidadao->cpf)
            ->whereHas('pss', fn ($q) => $q->withoutGlobalScope('ug')->where('ug_id', $ug->id))->orderByDesc('id')->get()->map(fn ($i) => self::inscricao($i))]);
    }

    /** Ficha de inscrição em PDF (TR 4.41.4). */
    public function ficha(Request $request, string $numero)
    {
        $ug = $this->ug($request);
        $cidadao = Cidadao::where('id', $request->integer('cidadao_id'))->where('ativo', true)->firstOrFail();
        $i = PssInscricao::with(['pss.tipoProcesso', 'anexos'])->where('numero', $numero)->where('cpf', $cidadao->cpf)->firstOrFail();
        abort_unless($i->pss?->ug_id === $ug->id, 404);
        $rotulos = collect($i->pss->tipoProcesso?->schema_formulario ?? [])->pluck('label', 'campo')->all();

        return \App\Support\Relatorio::pdf('pdf.ficha-pss', ['i' => $i, 'pss' => $i->pss, 'rotulos' => $rotulos, 'ug' => $ug])
            ->stream("ficha-{$i->numero}.pdf");
    }

    private static function edital(Pss $p): array
    {
        return [
            'id' => $p->id, 'titulo' => $p->titulo, 'edital_numero' => $p->edital_numero, 'descricao' => $p->descricao, 'requisitos' => $p->requisitos,
            'cargos' => $p->cargos ?? [], 'inscricoes_inicio' => $p->inscricoes_inicio?->toIso8601String(), 'inscricoes_fim' => $p->inscricoes_fim?->toIso8601String(),
            'situacao' => $p->situacao(), 'formulario' => CatalogoController::formulario($p->tipoProcesso),
            'documentos_exigidos' => CatalogoController::documentos($p->tipoProcesso),
        ];
    }

    private static function inscricao(PssInscricao $i): array
    {
        return [
            'numero' => $i->numero, 'edital' => ['id' => $i->pss?->id, 'titulo' => $i->pss?->titulo], 'cargo' => $i->cargo,
            'situacao' => $i->situacao, 'situacao_rotulo' => PssInscricao::SITUACOES[$i->situacao] ?? $i->situacao, 'resposta' => $i->resposta,
            'respondida_em' => $i->respondida_em?->toIso8601String(), 'inscrito_em' => $i->created_at?->toIso8601String(),
            'anexos' => $i->anexos->pluck('nome')->all(), 'ficha_url' => url("/api/portal/pss/inscricoes/{$i->numero}/ficha"),
        ];
    }
}