<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\Contrassenha;
use App\Models\Notificacao;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use RuntimeException;

/**
 * Envio de documento em nome de outro servidor por contrassenha (TR 4.18).
 * Usado na criação de ofício, memorando e circular:
 *
 *   $contrassenha = EmNomeDe::conferir($request);        // antes da transação (erro no campo)
 *   ... DB::beginTransaction();
 *   $origem = EmNomeDe::consumir($contrassenha);         // dentro: o uso só conta se gravar
 *   Documento::create([... 'remetente_id' => $origem['remetente_id'], 'digitador_id' => ..., 'contrassenha_id' => ...]);
 *
 * Sem "em_nome_de" na requisição, o remetente é quem está logado.
 */
final class EmNomeDe
{
    /** Confere autorizador + contrassenha informados; null = documento do próprio usuário. */
    public static function conferir(Request $request): ?Contrassenha
    {
        if (! $request->filled('em_nome_de')) {
            return null;
        }
        $request->validate([
            'em_nome_de'   => ['required', 'integer'],
            'contrassenha' => ['required', 'string', 'max:20'],
        ], ['contrassenha.required' => 'Informe a contrassenha que o autorizador gerou para você.']);

        $codigo = self::normalizar((string) $request->input('contrassenha'));
        $achada = Contrassenha::vigentes()
            ->where('autorizador_id', (int) $request->input('em_nome_de'))
            ->where('digitador_id', Auth::id())
            ->get()
            ->first(fn (Contrassenha $c) => Hash::check($codigo, $c->codigo_hash));

        if (! $achada) {
            throw ValidationException::withMessages([
                'contrassenha' => 'Contrassenha inválida, vencida, cancelada ou sem usos restantes para este autorizador.',
            ]);
        }

        return $achada;
    }

    /**
     * Gasta um uso (com trava da linha: dois envios ao mesmo tempo não passam
     * do limite) e devolve os campos de origem do documento.
     *
     * @return array{remetente_id: int, digitador_id: ?int, contrassenha_id: ?int}
     */
    public static function consumir(?Contrassenha $c): array
    {
        if (! $c) {
            return ['remetente_id' => (int) Auth::id(), 'digitador_id' => null, 'contrassenha_id' => null];
        }

        $travada = Contrassenha::whereKey($c->id)->lockForUpdate()->first();
        if (! $travada || $travada->situacao() !== 'ativa') {
            throw new RuntimeException('A contrassenha deixou de valer enquanto o documento era enviado.');
        }
        $travada->increment('usos');

        return ['remetente_id' => (int) $travada->autorizador_id, 'digitador_id' => (int) Auth::id(), 'contrassenha_id' => (int) $travada->id];
    }

    /** Avisa o autorizador de que um documento saiu em nome dele. */
    public static function avisarAutorizador(array $origem, string $tipoRef, int $refId, string $descricao): void
    {
        if (empty($origem['contrassenha_id'])) {
            return;
        }
        Notificacao::create([
            'usuario_id'      => $origem['remetente_id'],
            'tipo'            => 'contrassenha_usada',
            'titulo'          => 'Documento emitido em seu nome',
            'mensagem'        => "{$descricao} — digitado por " . (Auth::user()?->name ?? 'servidor') . ' com a sua contrassenha.',
            'referencia_tipo' => $tipoRef,
            'referencia_id'   => $refId,
        ]);
    }

    /**
     * Quem autorizou o usuário logado (contrassenhas vigentes), para o
     * seletor "Enviar em nome de" das telas de criação.
     *
     * @return array<int, array{id: int, nome: string, restantes: int, ate: string}>
     */
    public static function autorizacoes(): array
    {
        return Contrassenha::vigentes()->where('digitador_id', Auth::id())->with('autorizador:id,name')
            ->get()->groupBy('autorizador_id')
            ->map(fn ($grupo) => [
                'id'        => (int) $grupo->first()->autorizador_id,
                'nome'      => (string) $grupo->first()->autorizador?->name,
                'restantes' => (int) $grupo->sum(fn (Contrassenha $c) => $c->usos_max - $c->usos),
                'ate'       => $grupo->max('valida_ate')->setTimezone('America/Sao_Paulo')->format('d/m/Y'),
            ])->values()->all();
    }

    /** "k7p2 m9qx" → "K7P2M9QX" (sem espaço/hífen, maiúsculas). */
    public static function normalizar(string $codigo): string
    {
        return strtoupper((string) preg_replace('/[^A-Za-z0-9]/', '', $codigo));
    }
}
