<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\Assinatura;
use App\Models\Documento;
use App\Models\Processo\Circular;
use App\Models\Processo\Memorando;
use App\Models\Processo\Oficio;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Verificação de autenticidade por código na Central (TR 4.23.14–4.23.16): código impresso na
 * assinatura, código/QR do documento do GED (certidões incluídas), de memorando, circular ou
 * ofício. Toda consulta fica registrada — achou ou não, de onde e de que aparelho.
 */
final class Verificacoes
{
    public static function registrar(Request $request, string $codigo, string $origem, bool $encontrado, ?string $tipo = null, ?int $refId = null): void
    {
        try {
            DB::table('ged_verificacoes')->insert([
                'codigo' => mb_substr($codigo, 0, 120), 'origem' => $origem, 'encontrado' => $encontrado, 'tipo' => $tipo,
                'referencia_id' => $refId, 'ip' => $request->ip(), 'user_agent' => mb_substr((string) $request->userAgent(), 0, 300),
                'created_at' => now(),
            ]);
        } catch (\Throwable) {
            // A verificação em si não pode falhar por causa do registro
        }
    }

    /** Resultado para a API do portal (sem dado sigiloso). @return array{valido: bool, tipo?: string, ...} */
    public static function consultar(Request $request, string $codigo): array
    {
        $codigo = trim($codigo);
        $resultado = self::procurar($codigo);
        self::registrar($request, $codigo, 'portal', $resultado['valido'], $resultado['tipo'] ?? null, $resultado['_id'] ?? null);
        unset($resultado['_id']);

        return $resultado;
    }

    private static function procurar(string $codigo): array
    {
        // 1. Código da assinatura (carimbo do termo)
        $a = Assinatura::where('codigo_verificacao', mb_strtoupper($codigo))->where('status', 'assinado')->first();
        if ($a) {
            $doc = Documento::withoutGlobalScopes(['ug', 'sigilo'])->with('tipoDocumental')->find($a->documento_id);

            return ['valido' => true, 'tipo' => 'assinatura', '_id' => $a->id, 'documento' => $doc ? self::documento($doc) : null,
                'assinatura' => array_filter([
                    'signatario' => $doc?->ehSigiloso() ? null : $a->nome_exibicao,
                    'modalidade' => $a->tipo_assinatura === 'qualificada' ? 'Qualificada (ICP-Brasil)' : 'Eletrônica simples',
                    'assinado_em' => \App\Services\CarimboAssinaturaService::horaBrasilia($a->assinado_em) ?: null,
                ])];
        }
        // Os códigos de QR são UUID (coluna uuid no banco): outro formato não é procurado neles
        if (! \Illuminate\Support\Str::isUuid($codigo)) {
            return ['valido' => false];
        }
        // 2. Documento do GED pelo código do QR (certidões são documentos do tipo "Certidão…")
        $doc = Documento::withoutGlobalScopes(['ug', 'sigilo'])->with('tipoDocumental')->where('qr_code_token', $codigo)->first();
        if ($doc) {
            $certidao = str_starts_with(mb_strtolower((string) $doc->tipoDocumental?->nome), 'certid');

            return ['valido' => true, 'tipo' => $certidao ? 'certidao' : 'documento', '_id' => $doc->id, 'documento' => self::documento($doc),
                'assinaturas' => Assinatura::where('documento_id', $doc->id)->where('status', 'assinado')->count()];
        }
        // 3. Memorando, circular ou ofício pelo código do QR
        foreach (['memorando' => Memorando::class, 'circular' => Circular::class, 'oficio' => Oficio::class] as $tipo => $classe) {
            $c = $classe::withoutGlobalScopes()->where('qr_code_token', $codigo)->first();
            if ($c) {
                $confidencial = (bool) ($c->confidencial ?? false);

                return ['valido' => true, 'tipo' => $tipo, '_id' => $c->id, 'comunicacao' => array_filter([
                    'numero' => $c->numero, 'assunto' => $confidencial ? null : $c->assunto, 'unidade' => $c->setor_origem,
                    'emitido_em' => ($c->enviado_em ?? $c->created_at)?->setTimezone('America/Sao_Paulo')->format('d/m/Y H:i'),
                    'situacao' => $c->status,
                ])];
            }
        }

        return ['valido' => false];
    }

    private static function documento(Documento $d): array
    {
        $sigiloso = $d->ehSigiloso();

        return array_filter([
            'nome' => $sigiloso ? null : $d->nome, 'tipo' => $d->tipoDocumental?->nome, 'sigiloso' => $sigiloso ?: null,
            'status' => $d->status, 'versao' => $d->versao_atual,
            'criado_em' => $d->created_at?->setTimezone('America/Sao_Paulo')->format('d/m/Y H:i'),
        ], fn ($v) => $v !== null);
    }
}
