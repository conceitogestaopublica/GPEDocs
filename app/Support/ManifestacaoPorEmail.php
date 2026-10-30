<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\Processo\Processo;
use App\Models\Processo\ProcessoAnexo;
use App\Models\Processo\TipoEtapa;
use App\Models\Processo\TipoProcesso;
use App\Models\Processo\Tramitacao;
use App\Models\UgOrganograma;
use App\Tenant\TenantStorage;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * E-mail setorial que vira manifestação (TR 4.33.4): o e-mail que chega no endereço de entrada
 * de um assunto abre o processo no setor configurado, com canal "e-mail", o remetente como
 * manifestante e os anexos. A decisão final volta ao e-mail de origem (RespostaManifestante).
 */
final class ManifestacaoPorEmail
{
    private const MAX_ANEXO = 20 * 1024 * 1024;

    /** Assunto cujo endereço de entrada está entre os destinatários. */
    public static function assuntoDe(array $para): ?TipoProcesso
    {
        $enderecos = [];
        foreach ($para as $p) {
            foreach (preg_split('/[,;]/', (string) $p) as $e) {
                $e = mb_strtolower(trim(preg_match('/<([^>]+)>/', $e, $m) ? $m[1] : $e));
                if ($e !== '') {
                    $enderecos[] = $e;
                }
            }
        }

        return $enderecos ? TipoProcesso::withoutGlobalScopes()->where('ativo', true)->whereNotNull('email_unidade_id')
            ->whereIn(DB::raw('lower(email_entrada)'), $enderecos)->first() : null;
    }

    /** @param list<array{nome?: string, tipo?: string, conteudo: string}> $anexos */
    public static function abrir(TipoProcesso $tipo, string $deBruto, string $assunto, string $texto, array $anexos): Processo
    {
        $email = mb_strtolower(preg_match('/<([^>]+)>/', $deBruto, $m) ? trim($m[1]) : trim($deBruto));
        $nome = trim(preg_replace('/<[^>]+>/', '', $deBruto), " \t\"'") ?: $email;
        $unidade = UgOrganograma::withoutGlobalScopes()->findOrFail($tipo->email_unidade_id);

        return DB::transaction(function () use ($tipo, $email, $nome, $assunto, $texto, $anexos, $unidade) {
            $etapa = TipoEtapa::where('tipo_processo_id', $tipo->id)->orderBy('ordem')->first();
            $ano = date('Y');
            $ultimo = Processo::withoutGlobalScopes()->where('tipo_processo_id', $tipo->id)->whereYear('created_at', $ano)
                ->max(DB::raw("CAST(SPLIT_PART(numero_protocolo, '/', 2) AS INTEGER)"));

            $p = Processo::withoutGlobalScopes()->create([
                'ug_id'            => $unidade->ug_id,
                'numero_protocolo' => $tipo->sigla . '-' . $ano . '/' . str_pad((string) (($ultimo ?? 0) + 1), 6, '0', STR_PAD_LEFT),
                'tipo_processo_id' => $tipo->id,
                'assunto'          => mb_substr($assunto !== '' ? $assunto : "[E-mail] {$tipo->nome}", 0, 250),
                'descricao'        => $texto,
                'requerente_nome'  => mb_substr($nome, 0, 200),
                'requerente_email' => $email,
                'setor_origem'     => 'E-mail',
                'status'           => 'aberto',
                'prioridade'       => 'normal',
                'canal'            => 'email',
                'identificacao'    => 'aberta',
            ]);
            $t = Tramitacao::create([
                'processo_id' => $p->id, 'tipo_etapa_id' => $etapa?->id, 'ordem' => 1, 'setor_origem' => 'E-mail',
                'setor_destino' => $unidade->nome, 'destino_unidade_id' => $unidade->id, 'destinatario_id' => $etapa?->responsavel_id,
                'status' => 'pendente', 'despachado_em' => now(), 'sla_horas' => $etapa?->sla_horas ?? $tipo->sla_padrao_horas,
                'prazo' => Expediente::prazo($tipo, $etapa?->sla_horas ?? $tipo->sla_padrao_horas ?? 72),
            ]);
            $p->update(['etapa_atual_id' => $t->id]);

            foreach ($anexos as $a) {
                $bytes = base64_decode((string) ($a['conteudo'] ?? ''), true);
                if ($bytes === false || $bytes === '' || strlen($bytes) > self::MAX_ANEXO) {
                    continue;
                }
                $nomeArq = mb_substr(basename((string) ($a['nome'] ?? 'anexo')), 0, 200) ?: 'anexo';
                $path = TenantStorage::pasta('processos') . '/' . Str::random(40) . '.' . (pathinfo($nomeArq, PATHINFO_EXTENSION) ?: 'bin');
                Storage::disk('documentos')->put($path, $bytes);
                ProcessoAnexo::create(['processo_id' => $p->id, 'tramitacao_id' => $t->id, 'nome' => $nomeArq, 'arquivo_path' => $path,
                    'tamanho' => strlen($bytes), 'mime_type' => $a['tipo'] ?? 'application/octet-stream', 'hash_sha256' => hash('sha256', $bytes)]);
            }
            DB::table('proc_historico')->insert(['processo_id' => $p->id, 'usuario_id' => null, 'acao' => 'aberto_por_email',
                'detalhes' => json_encode(['de' => $email, 'anexos' => count($anexos)], JSON_UNESCAPED_UNICODE), 'created_at' => now()]);

            return $p;
        });
    }
}
