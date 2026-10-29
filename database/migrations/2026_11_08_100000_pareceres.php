<?php

use App\Support\Permissoes;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Parecer (TR 4.39): peça própria, numerada por ano, emitida por servidores com permissão e,
 * se a lista estiver preenchida, só dos setores autorizados; com conclusão, anexos, QR de
 * verificação e vínculo a um processo ou memorando.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('proc_pareceres', function (Blueprint $t) {
            $t->id();
            $t->foreignId('ug_id')->constrained('ugs');
            $t->string('numero', 30);
            $t->string('assunto', 255);
            $t->text('ementa')->nullable();
            $t->longText('conteudo');
            $t->string('conclusao', 15);                     // favoravel | desfavoravel | parcial | informativo
            $t->foreignId('remetente_id')->constrained('users');
            $t->string('setor_origem', 200)->nullable();
            $t->foreignId('unidade_id')->nullable()->constrained('ug_organograma')->nullOnDelete();
            $t->foreignId('processo_id')->nullable()->constrained('proc_processos')->nullOnDelete();
            $t->foreignId('memorando_id')->nullable()->constrained('proc_memorandos')->nullOnDelete();
            $t->string('status', 12)->default('emitido');
            $t->timestamp('enviado_em')->nullable();
            $t->uuid('qr_code_token')->unique();
            $t->foreignId('documento_id')->nullable()->constrained('ged_documentos')->nullOnDelete();   // PDF levado à assinatura
            $t->timestamps();
            $t->softDeletes();
            $t->unique(['ug_id', 'numero']);
        });
        Schema::create('proc_parecer_anexos', function (Blueprint $t) {
            $t->id();
            $t->foreignId('parecer_id')->constrained('proc_pareceres')->cascadeOnDelete();
            $t->string('nome', 255);
            $t->string('arquivo_path', 500);
            $t->unsignedBigInteger('tamanho');
            $t->string('mime_type', 120);
            $t->string('hash_sha256', 64)->nullable();
            $t->foreignId('enviado_por')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamps();
        });
        // Setores autorizados a emitir; vazio = qualquer setor (com a permissão)
        Schema::create('proc_parecer_setores', function (Blueprint $t) {
            $t->foreignId('unidade_id')->primary()->constrained('ug_organograma')->cascadeOnDelete();
        });

        $ids = Permissoes::sincronizarCatalogo();
        foreach (DB::table('ged_role_permissions')->where('permission_id', $ids['admin.configuracoes'] ?? 0)->pluck('role_id') as $perfil) {
            Permissoes::concederAoPerfil((int) $perfil, ['parecer.emitir'], $ids);
        }
    }

    public function down(): void
    {
        DB::table('ged_permissions')->where('nome', 'parecer.emitir')->delete();
        Schema::dropIfExists('proc_parecer_setores');
        Schema::dropIfExists('proc_parecer_anexos');
        Schema::dropIfExists('proc_pareceres');
    }
};
