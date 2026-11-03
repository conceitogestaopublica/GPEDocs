<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;

/**
 * Política de senha do servidor (config auth.senha): composição mínima, expiração com aviso prévio,
 * troca obrigatória no próximo acesso (senha definida pelo administrador ou vencida) e vedação de
 * reutilizar as últimas senhas. Vale para toda troca: recuperação, perfil, administrador e expirada.
 */
final class PoliticaSenha
{
    /** Regra de validação da senha nova (tamanho e composição). */
    public static function regra(): Password
    {
        $exige = self::exige();
        $r = Password::min(max(6, (int) config('auth.senha.minimo', 8)));
        if (in_array('letra', $exige, true) || in_array('maiuscula', $exige, true)) {
            $r = in_array('maiuscula', $exige, true) ? $r->mixedCase() : $r->letters();
        }
        if (in_array('numero', $exige, true)) {
            $r = $r->numbers();
        }
        if (in_array('especial', $exige, true)) {
            $r = $r->symbols();
        }

        return $r;
    }

    /** Texto para a tela: o que a senha precisa ter. */
    public static function descricao(): string
    {
        $partes = ['no mínimo ' . max(6, (int) config('auth.senha.minimo', 8)) . ' caracteres'];
        $nomes = ['letra' => 'letras', 'maiuscula' => 'maiúsculas e minúsculas', 'numero' => 'números', 'especial' => 'um símbolo (!@#…)'];
        foreach (self::exige() as $e) {
            if (isset($nomes[$e]) && ! ($e === 'letra' && in_array('maiuscula', self::exige(), true))) {
                $partes[] = $nomes[$e];
            }
        }
        $hist = (int) config('auth.senha.historico', 5);

        return 'A senha precisa ter ' . implode(', ', $partes) . '.' . ($hist > 0 ? " Não pode repetir as últimas {$hist} senhas." : '');
    }

    /** Recusa senha igual à atual ou a uma das últimas (ValidationException no campo informado). */
    public static function conferirHistorico(User $u, string $nova, string $campo = 'password'): void
    {
        $n = (int) config('auth.senha.historico', 5);
        if ($n <= 0) {
            return;
        }
        $anteriores = DB::table('ged_senhas_anteriores')->where('user_id', $u->id)->orderByDesc('id')->limit(max(0, $n - 1))->pluck('hash');
        foreach ($anteriores->prepend($u->password) as $hash) {
            if ($hash && Hash::check($nova, $hash)) {
                throw ValidationException::withMessages([$campo => "A nova senha não pode repetir a atual nem as últimas {$n}."]);
            }
        }
    }

    /**
     * Grava a senha nova guardando a anterior no histórico.
     * @param  bool  $trocarNoProximoAcesso  senha definida por outra pessoa (administrador): o usuário troca ao entrar
     */
    public static function gravar(User $u, string $nova, bool $trocarNoProximoAcesso = false): void
    {
        if ($u->password) {
            DB::table('ged_senhas_anteriores')->insert(['user_id' => $u->id, 'hash' => $u->password, 'created_at' => now()]);
            $guardar = max(1, (int) config('auth.senha.historico', 5));
            $velhas = DB::table('ged_senhas_anteriores')->where('user_id', $u->id)->orderByDesc('id')->skip($guardar)->take(1000)->pluck('id');
            if ($velhas->isNotEmpty()) {
                DB::table('ged_senhas_anteriores')->whereIn('id', $velhas)->delete();
            }
        }
        $u->forceFill(['password' => Hash::make($nova), 'senha_alterada_em' => now(), 'senha_trocar' => $trocarNoProximoAcesso])->save();
    }

    /** A senha venceu (ou foi marcada para troca)? Super admin e contas sem data não expiram por prazo. */
    public static function precisaTrocar(User $u): bool
    {
        if ($u->senha_trocar) {
            return true;
        }
        $dias = (int) config('auth.senha.validade', 0);

        return $dias > 0 && ! $u->super_admin && $u->senha_alterada_em && $u->senha_alterada_em->lte(now()->subDays($dias));
    }

    /** Dias até vencer, quando já está dentro do período de aviso; senão null. */
    public static function diasParaVencer(User $u): ?int
    {
        $dias = (int) config('auth.senha.validade', 0);
        if ($dias <= 0 || $u->super_admin || ! $u->senha_alterada_em) {
            return null;
        }
        $restam = (int) ceil(now()->diffInDays($u->senha_alterada_em->copy()->addDays($dias), false));

        return $restam >= 0 && $restam <= (int) config('auth.senha.aviso', 10) ? $restam : null;
    }

    /** @return list<string> */
    private static function exige(): array
    {
        return array_values(array_filter(array_map('trim', explode(',', (string) config('auth.senha.exige', 'letra,numero')))));
    }
}
