<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Servidores importados recebiam o e-mail provisório srv-{CPF}@servidor.local: o CPF inteiro aparecia
 * na lista de usuários e uma busca por parte do número achava a pessoa pelo e-mail (LGPD).
 * Troca o número por um código aleatório. O login por CPF continua igual (resolve o e-mail pelo CPF).
 */
return new class extends Migration
{
    public function up(): void
    {
        $ids = DB::table('users')
            ->where('email', 'like', 'srv-%@servidor.local')
            ->pluck('email', 'id');

        foreach ($ids as $id => $email) {
            if (! preg_match('/^srv-\d{11}@servidor\.local$/', (string) $email)) {
                continue;
            }
            DB::table('users')->where('id', $id)->update([
                'email' => 'srv-' . strtolower(\Illuminate\Support\Str::random(16)) . '@servidor.local',
            ]);
        }
    }

    public function down(): void
    {
        // Sem volta: o CPF não deve voltar para o e-mail.
    }
};
