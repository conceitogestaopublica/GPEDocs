<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\User;
use App\Tenant\TenantStorage;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * Rascunho automático do editor (TR 4.16.6–4.16.9). Fica com o setor de quem começou:
 * qualquer servidor do setor continua. Um edita por vez — a trava dura TRAVA_SEGUNDOS e é
 * renovada enquanto a tela está aberta; se a pessoa sai, expira e outro assume.
 */
final class Rascunhos
{
    public const MODULOS = ['memorando' => 'Memorando', 'circular' => 'Circular', 'oficio' => 'Ofício'];

    public const TRAVA_SEGUNDOS = 120;

    public const CRIAR = ['memorando' => '/memorandos/create', 'circular' => '/circulares/create', 'oficio' => '/oficios/create'];

    public static function buscar(int $id): ?object
    {
        return DB::table('ged_rascunhos')->where('id', $id)->where('ug_id', session('ug_id'))->first();
    }

    /** Autor, ou quem atende o setor do rascunho. */
    public static function pode(object $r, User $u): bool
    {
        return (int) $r->autor_id === (int) $u->id || ($r->unidade_id && Setores::atende($u, (int) $r->unidade_id));
    }

    public static function exigir(int $id, User $u): object
    {
        $r = self::buscar($id);
        abort_unless($r && self::pode($r, $u), 404);

        return $r;
    }

    /** Trava com outra pessoa e ainda válida? Devolve o nome de quem está editando. */
    public static function travadoPorOutro(object $r, User $u): ?string
    {
        if (! $r->travado_por || (int) $r->travado_por === (int) $u->id || ! $r->travado_ate || now()->gte($r->travado_ate)) {
            return null;
        }

        return (string) DB::table('users')->where('id', $r->travado_por)->value('name');
    }

    /** Pega ou renova a trava. false = está com outro. */
    public static function travar(object $r, User $u): bool
    {
        if (self::travadoPorOutro($r, $u)) {
            return false;
        }
        DB::table('ged_rascunhos')->where('id', $r->id)
            ->update(['travado_por' => $u->id, 'travado_ate' => now()->addSeconds(self::TRAVA_SEGUNDOS)]);

        return true;
    }

    public static function liberar(object $r, User $u): void
    {
        if ((int) $r->travado_por === (int) $u->id) {
            DB::table('ged_rascunhos')->where('id', $r->id)->update(['travado_ate' => null]);
        }
    }

    public static function criar(string $modulo, array $dados, ?string $titulo, User $u): int
    {
        return (int) DB::table('ged_rascunhos')->insertGetId([
            'ug_id' => session('ug_id'), 'modulo' => $modulo, 'titulo' => self::titulo($titulo), 'dados' => json_encode($dados),
            'unidade_id' => $u->unidade_id, 'autor_id' => $u->id, 'atualizado_por' => $u->id,
            'travado_por' => $u->id, 'travado_ate' => now()->addSeconds(self::TRAVA_SEGUNDOS),
            'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    public static function salvar(object $r, array $dados, ?string $titulo, User $u): void
    {
        DB::table('ged_rascunhos')->where('id', $r->id)->update([
            'dados' => json_encode($dados), 'titulo' => self::titulo($titulo), 'atualizado_por' => $u->id, 'updated_at' => now(),
            'travado_por' => $u->id, 'travado_ate' => now()->addSeconds(self::TRAVA_SEGUNDOS),
        ]);
    }

    /** Para a tela de criação abrir o rascunho (já com a trava, se livre). */
    public static function abrir(?int $id, User $u, string $modulo): ?array
    {
        if (! $id) {
            return null;
        }
        $r = self::exigir($id, $u);
        abort_unless($r->modulo === $modulo, 404);
        $outro = self::travadoPorOutro($r, $u);
        if (! $outro) {
            self::travar($r, $u);
        }

        return [
            'id' => $r->id, 'dados' => json_decode((string) $r->dados, true) ?: [], 'anexos' => self::anexos($r->id),
            'travado_por' => $outro, 'atualizado_em' => \Illuminate\Support\Carbon::parse($r->updated_at, 'UTC')->toIso8601String(),
            'atualizado_por' => DB::table('users')->where('id', $r->atualizado_por)->value('name'),
        ];
    }

    public static function anexos(int $id): array
    {
        return DB::table('ged_rascunho_anexos')->where('rascunho_id', $id)->orderBy('id')
            ->get(['id', 'nome', 'tamanho', 'mime_type'])->map(fn ($a) => (array) $a)->all();
    }

    public static function anexar(object $r, \Illuminate\Http\UploadedFile $arquivo, User $u): array
    {
        $path = $arquivo->store(TenantStorage::pasta('rascunhos'), 'documentos');
        $id = DB::table('ged_rascunho_anexos')->insertGetId([
            'rascunho_id' => $r->id, 'nome' => $arquivo->getClientOriginalName(), 'arquivo_path' => $path,
            'tamanho' => $arquivo->getSize(), 'mime_type' => $arquivo->getMimeType(), 'enviado_por' => $u->id,
        ]);
        DB::table('ged_rascunhos')->where('id', $r->id)->update(['atualizado_por' => $u->id, 'updated_at' => now()]);

        return ['id' => $id, 'nome' => $arquivo->getClientOriginalName(), 'tamanho' => $arquivo->getSize(), 'mime_type' => $arquivo->getMimeType()];
    }

    public static function removerAnexo(object $r, int $anexoId): void
    {
        $a = DB::table('ged_rascunho_anexos')->where('rascunho_id', $r->id)->where('id', $anexoId)->first();
        if ($a) {
            Storage::disk('documentos')->delete($a->arquivo_path);
            DB::table('ged_rascunho_anexos')->where('id', $a->id)->delete();
        }
    }

    /**
     * No envio do documento: copia os anexos do rascunho para a pasta do módulo e devolve os
     * dados para criar os anexos do documento. Cópia, e não move: se o envio falhar, o rascunho
     * continua inteiro (os originais saem em concluir). Sem rascunho (ou sem acesso), lista vazia.
     *
     * @return list<array{nome: string, arquivo_path: string, tamanho: int, mime_type: ?string, enviado_por: ?int}>
     */
    public static function entregarAnexos(?int $id, string $pasta, User $u): array
    {
        $r = $id ? self::buscar($id) : null;
        if (! $r || ! self::pode($r, $u)) {
            return [];
        }
        $saida = [];
        foreach (DB::table('ged_rascunho_anexos')->where('rascunho_id', $r->id)->orderBy('id')->get() as $a) {
            $novo = TenantStorage::pasta($pasta) . '/' . basename($a->arquivo_path);
            if (! Storage::disk('documentos')->exists($a->arquivo_path)) {
                continue;
            }
            Storage::disk('documentos')->copy($a->arquivo_path, $novo);
            $saida[] = ['nome' => $a->nome, 'arquivo_path' => $novo, 'tamanho' => (int) $a->tamanho,
                'mime_type' => $a->mime_type, 'enviado_por' => $a->enviado_por];
        }

        return $saida;
    }

    /** Documento enviado: o rascunho e os arquivos originais somem (as cópias ficaram no documento). */
    public static function concluir(?int $id, User $u): void
    {
        $r = $id ? self::buscar($id) : null;
        if ($r && self::pode($r, $u)) {
            self::descartar($r);
        }
    }

    public static function descartar(object $r): void
    {
        foreach (DB::table('ged_rascunho_anexos')->where('rascunho_id', $r->id)->pluck('arquivo_path') as $p) {
            Storage::disk('documentos')->delete($p);
        }
        DB::table('ged_rascunhos')->where('id', $r->id)->delete();
    }

    /** Rascunhos que o servidor pode continuar: os dele e os dos setores que atende. */
    public static function visiveis(User $u)
    {
        $setores = Setores::doUsuario($u);

        return DB::table('ged_rascunhos as r')->where('r.ug_id', session('ug_id'))
            ->where(fn ($q) => $q->where('r.autor_id', $u->id)->when($setores, fn ($w) => $w->orWhereIn('r.unidade_id', $setores)));
    }

    private static function titulo(?string $t): ?string
    {
        $t = trim((string) $t);

        return $t === '' ? null : mb_substr($t, 0, 300);
    }
}
