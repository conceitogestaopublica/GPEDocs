<?php

declare(strict_types=1);

namespace App\Integracoes\Tributario;

use App\Models\Processo\Processo;
use App\Models\Processo\ProcessoAnexo;
use App\Models\Processo\VinculoTributario;
use App\Models\User;
use App\Tenant\TenantStorage;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * O processo e o sistema tributário (TR 4.48.22, 4.48.42): vincula o imóvel, emite e acompanha a
 * guia, pede alvará/habite-se/certidão e anexa o PDF ao processo, abre fiscalização; sem
 * integração, registra o que o servidor informar à mão. Tudo fica no histórico do processo.
 */
final class Vinculos
{
    public static function vincularImovel(Processo $p, string $inscricao, User $u): VinculoTributario
    {
        $sis = Fabrica::para($p->ug_id);
        $imovel = $sis->consultarImovel(trim($inscricao)) ?? throw new FalhaTributario("Inscrição {$inscricao} não encontrada no {$sis->nome()}.");
        $v = VinculoTributario::updateOrCreate(['processo_id' => $p->id, 'tipo' => 'imovel'],
            ['id_externo' => (string) ($imovel['inscricao'] ?? $inscricao), 'numero' => (string) ($imovel['inscricao'] ?? $inscricao),
                'situacao' => $imovel['situacao'] ?? null, 'dados' => $imovel, 'manual' => false, 'criado_por' => $u->id]);
        // Endereço e local no mapa vêm do cadastro, se o processo ainda não tem
        $local = array_filter(['local_endereco' => $p->local_endereco ?: self::endereco($imovel),
            'latitude' => $p->latitude ?? (is_numeric($imovel['latitude'] ?? null) ? (float) $imovel['latitude'] : null),
            'longitude' => $p->longitude ?? (is_numeric($imovel['longitude'] ?? null) ? (float) $imovel['longitude'] : null)], fn ($x) => $x !== null && $x !== '');
        if ($local) {
            Processo::withoutEvents(fn () => $p->forceFill($local)->save());
        }
        self::historico($p, $u, 'tributario_imovel', ['inscricao' => $v->numero, 'sistema' => $sis->nome()]);

        return $v;
    }

    /** @param array{taxa: string, valor?: ?float, descricao?: ?string, vencimento?: ?string} $d */
    public static function emitirGuia(Processo $p, array $d, User $u): VinculoTributario
    {
        $sis = Fabrica::para($p->ug_id);
        $imovel = VinculoTributario::where('processo_id', $p->id)->where('tipo', 'imovel')->first();
        $g = $sis->emitirGuia($d + ['documento' => preg_replace('/\D/', '', (string) $p->requerente_cpf), 'nome' => (string) $p->requerente_nome,
            'inscricao' => $imovel?->numero, 'processo' => $p->numero_protocolo]);
        $v = VinculoTributario::create(['processo_id' => $p->id, 'tipo' => 'guia', 'id_externo' => (string) ($g['id_externo'] ?? ''), 'numero' => $g['numero'] ?? null,
            'situacao' => $g['situacao'] ?? 'emitida', 'valor' => is_numeric($g['valor'] ?? null) ? $g['valor'] : ($d['valor'] ?? null),
            'vencimento' => self::data($g['vencimento'] ?? null), 'dados' => $g + ['taxa' => $d['taxa']], 'criado_por' => $u->id]);
        self::anexarSePossivel($v, $sis, $g['pdf_url'] ?? null, 'Guia ' . ($v->numero ?: $v->id_externo), $u);
        self::historico($p, $u, 'tributario_guia', ['guia' => $v->numero ?: $v->id_externo, 'valor' => $v->valor, 'taxa' => $d['taxa']]);

        return $v;
    }

    public static function atualizar(VinculoTributario $v, User $u): VinculoTributario
    {
        $sis = Fabrica::para($v->processo->ug_id);
        $r = match ($v->tipo) {
            'guia' => $sis->consultarGuia((string) $v->id_externo),
            'alvara_construcao', 'habitese', 'certidao' => $sis->consultarDocumento($v->tipo, (string) $v->id_externo),
            'imovel' => $sis->consultarImovel((string) $v->id_externo) ?? [],
            default => [],
        };
        $antes = $v->situacao;
        $v->update(['situacao' => $r['situacao'] ?? $v->situacao, 'numero' => $r['numero'] ?? $v->numero, 'dados' => array_merge($v->dados ?? [], array_filter($r, fn ($x) => $x !== null))]);
        if (! $v->anexo_id && ! empty($r['pdf_url'])) {
            self::anexarSePossivel($v, $sis, $r['pdf_url'], (VinculoTributario::TIPOS[$v->tipo] ?? $v->tipo) . ' ' . ($v->numero ?: ''), $u);
        }
        if ($antes !== $v->situacao) {
            self::historico($v->processo, $u, 'tributario_situacao', ['tipo' => $v->tipo, 'numero' => $v->numero, 'de' => $antes, 'para' => $v->situacao]);
        }

        return $v;
    }

    /** Alvará de construção, habite-se ou certidão pedidos ao tributário (ex.: ao deferir). */
    public static function solicitarDocumento(Processo $p, string $tipo, array $extra, User $u): VinculoTributario
    {
        $sis = Fabrica::para($p->ug_id);
        $imovel = VinculoTributario::where('processo_id', $p->id)->where('tipo', 'imovel')->first();
        $doc = $sis->solicitarDocumento($tipo, $extra + ['inscricao' => $imovel?->numero, 'documento' => preg_replace('/\D/', '', (string) $p->requerente_cpf),
            'nome' => (string) $p->requerente_nome, 'processo' => $p->numero_protocolo, 'assunto' => $p->assunto]);
        $v = VinculoTributario::create(['processo_id' => $p->id, 'tipo' => $tipo, 'id_externo' => (string) ($doc['id_externo'] ?? ''), 'numero' => $doc['numero'] ?? null,
            'situacao' => $doc['situacao'] ?? 'solicitado', 'dados' => $doc + ['pedido' => $extra], 'criado_por' => $u->id]);
        self::anexarSePossivel($v, $sis, $doc['pdf_url'] ?? null, Normalizado::DOCUMENTOS[$tipo] . ' ' . ($v->numero ?: ''), $u);
        self::historico($p, $u, 'tributario_documento', ['tipo' => Normalizado::DOCUMENTOS[$tipo], 'numero' => $v->numero, 'sistema' => $sis->nome()]);

        return $v;
    }

    public static function abrirFiscalizacao(Processo $p, string $motivo, User $u): VinculoTributario
    {
        $sis = Fabrica::para($p->ug_id);
        $imovel = VinculoTributario::where('processo_id', $p->id)->where('tipo', 'imovel')->first();
        $f = $sis->abrirFiscalizacao(['motivo' => $motivo, 'inscricao' => $imovel?->numero, 'endereco' => $p->local_endereco, 'processo' => $p->numero_protocolo,
            'descricao' => $p->descricao ?: $p->assunto, 'latitude' => $p->latitude, 'longitude' => $p->longitude]);
        $v = VinculoTributario::create(['processo_id' => $p->id, 'tipo' => 'fiscalizacao', 'id_externo' => (string) ($f['id_externo'] ?? ''), 'numero' => $f['numero'] ?? null,
            'situacao' => $f['situacao'] ?? 'aberta', 'dados' => $f + ['motivo' => $motivo], 'criado_por' => $u->id]);
        self::historico($p, $u, 'tributario_fiscalizacao', ['numero' => $v->numero, 'motivo' => $motivo]);

        return $v;
    }

    /** Sem integração (ou o sistema não oferece o recurso): o servidor informa e anexa. */
    public static function registrarManual(Processo $p, string $tipo, array $d, ?\Illuminate\Http\UploadedFile $pdf, User $u): VinculoTributario
    {
        $v = VinculoTributario::create(['processo_id' => $p->id, 'tipo' => $tipo, 'id_externo' => $d['numero'] ?? null, 'numero' => $d['numero'] ?? null,
            'situacao' => $d['situacao'] ?? null, 'valor' => $d['valor'] ?? null, 'vencimento' => $d['vencimento'] ?? null,
            'dados' => array_filter($d, fn ($x) => $x !== null && $x !== ''), 'manual' => true, 'criado_por' => $u->id]);
        if ($pdf) {
            self::anexar($v, (string) file_get_contents($pdf->getRealPath()), (VinculoTributario::TIPOS[$tipo] ?? $tipo) . ' ' . ($v->numero ?: '') . '.pdf', $u);
        }
        self::historico($p, $u, 'tributario_manual', ['tipo' => VinculoTributario::TIPOS[$tipo] ?? $tipo, 'numero' => $v->numero]);

        return $v;
    }

    /** PDF vindo do tributário vira anexo do processo (TR 4.48.42). */
    public static function anexar(VinculoTributario $v, string $bytes, string $nome, ?User $u): void
    {
        $path = TenantStorage::pasta('processos') . '/' . Str::uuid() . '.pdf';
        Storage::disk('documentos')->put($path, $bytes);
        $a = ProcessoAnexo::create(['processo_id' => $v->processo_id, 'tramitacao_id' => $v->processo?->etapa_atual_id,
            'nome' => mb_substr(str_ends_with($nome, '.pdf') ? $nome : "{$nome}.pdf", 0, 255), 'arquivo_path' => $path, 'tamanho' => strlen($bytes),
            'mime_type' => 'application/pdf', 'hash_sha256' => hash('sha256', $bytes), 'enviado_por' => $u?->id]);
        $v->update(['anexo_id' => $a->id]);
    }

    private static function anexarSePossivel(VinculoTributario $v, SistemaTributario $sis, ?string $url, string $nome, ?User $u): void
    {
        if (! $url) {
            return;
        }
        try {
            self::anexar($v, $sis->baixarPdf($url), trim($nome), $u);
        } catch (FalhaTributario $e) {
            report($e);   // o vínculo fica; o PDF chega depois (atualizar ou evento)
        }
    }

    private static function historico(Processo $p, ?User $u, string $acao, array $detalhes): void
    {
        DB::table('proc_historico')->insert(['processo_id' => $p->id, 'usuario_id' => $u?->id, 'acao' => $acao,
            'detalhes' => json_encode($detalhes, JSON_UNESCAPED_UNICODE), 'ip' => request()?->ip(), 'created_at' => now()]);
    }

    private static function endereco(array $i): ?string
    {
        $e = trim(implode(', ', array_filter([trim(($i['logradouro'] ?? '') . ' ' . ($i['numero'] ?? '')), $i['bairro'] ?? null])));

        return $e !== '' ? $e : null;
    }

    private static function data(mixed $v): ?string
    {
        if (! $v) {
            return null;
        }
        try {
            return \Carbon\Carbon::parse((string) $v)->format('Y-m-d');
        } catch (\Throwable) {
            return null;
        }
    }
}