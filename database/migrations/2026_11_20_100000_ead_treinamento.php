<?php

use App\Support\Permissoes;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Treinamento EAD (TR 4.24, 4.25.2): cursos por módulo com aulas em vídeo (arquivo ou YouTube), progresso
 * de cada aula pelo trecho realmente assistido, ordem obrigatória opcional, avaliação objetiva com nota
 * mínima e tentativas, certificado em PDF com código de verificação e o progresso de cada servidor.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ead_cursos', function (Blueprint $t) {
            $t->id();
            $t->string('titulo', 200);
            $t->text('descricao')->nullable();
            $t->string('modulo', 40)->nullable();                     // ged | fluxos | ouvidoria | … (filtro "por módulo")
            $t->decimal('carga_horaria', 5, 1)->default(1);           // horas, vai no certificado
            $t->boolean('ordem_obrigatoria')->default(true);
            $t->unsignedTinyInteger('percentual_minimo')->default(90);// da aula assistido para contar como concluída
            $t->unsignedTinyInteger('nota_minima')->default(70);      // 0–100
            $t->unsignedTinyInteger('tentativas')->nullable();        // null = ilimitadas
            $t->boolean('publicado')->default(false);
            $t->foreignId('criado_por')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamps();
        });
        Schema::create('ead_aulas', function (Blueprint $t) {
            $t->id();
            $t->foreignId('curso_id')->constrained('ead_cursos')->cascadeOnDelete();
            $t->unsignedSmallInteger('ordem')->default(1);
            $t->string('titulo', 200);
            $t->text('descricao')->nullable();
            $t->string('video_tipo', 10);                             // arquivo | youtube
            $t->string('video_path')->nullable();                     // arquivo no disco de documentos
            $t->string('video_url', 300)->nullable();                 // YouTube
            $t->string('video_mime', 60)->nullable();
            $t->unsignedInteger('duracao_seg')->nullable();           // lida do vídeo pelo navegador
            $t->timestamps();
        });
        Schema::create('ead_questoes', function (Blueprint $t) {
            $t->id();
            $t->foreignId('curso_id')->constrained('ead_cursos')->cascadeOnDelete();
            $t->unsignedSmallInteger('ordem')->default(1);
            $t->text('enunciado');
            $t->jsonb('alternativas');                                // ["texto", …]
            $t->unsignedTinyInteger('correta');                       // índice em alternativas
            $t->unsignedTinyInteger('peso')->default(1);
            $t->timestamps();
        });
        Schema::create('ead_matriculas', function (Blueprint $t) {
            $t->id();
            $t->foreignId('curso_id')->constrained('ead_cursos')->cascadeOnDelete();
            $t->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $t->timestamp('iniciado_em')->nullable();
            $t->timestamp('aulas_concluidas_em')->nullable();
            $t->decimal('nota', 5, 1)->nullable();                    // melhor nota
            $t->boolean('aprovado')->default(false);
            $t->timestamp('concluido_em')->nullable();                // aprovação na avaliação
            $t->uuid('certificado_codigo')->nullable()->unique();
            $t->timestamps();
            $t->unique(['curso_id', 'user_id']);
        });
        Schema::create('ead_progresso', function (Blueprint $t) {
            $t->id();
            $t->foreignId('matricula_id')->constrained('ead_matriculas')->cascadeOnDelete();
            $t->foreignId('aula_id')->constrained('ead_aulas')->cascadeOnDelete();
            $t->unsignedInteger('assistido_ate')->default(0);         // segundo mais adiantado assistido sem pular
            $t->unsignedInteger('posicao')->default(0);               // onde parou (para continuar)
            $t->unsignedTinyInteger('percentual')->default(0);
            $t->timestamp('concluida_em')->nullable();
            $t->unsignedSmallInteger('vezes_revista')->default(0);
            $t->timestamps();
            $t->unique(['matricula_id', 'aula_id']);
        });
        Schema::create('ead_tentativas', function (Blueprint $t) {
            $t->id();
            $t->foreignId('matricula_id')->constrained('ead_matriculas')->cascadeOnDelete();
            $t->jsonb('respostas');
            $t->decimal('nota', 5, 1);
            $t->boolean('aprovado');
            $t->timestamp('created_at')->useCurrent();
        });

        $ids = Permissoes::sincronizarCatalogo();
        foreach (DB::table('ged_role_permissions')->where('permission_id', $ids['admin.configuracoes'] ?? 0)->pluck('role_id') as $perfil) {
            Permissoes::concederAoPerfil((int) $perfil, ['ead.gerir'], $ids);
        }
    }

    public function down(): void
    {
        DB::table('ged_permissions')->where('nome', 'ead.gerir')->delete();
        foreach (['ead_tentativas', 'ead_progresso', 'ead_matriculas', 'ead_questoes', 'ead_aulas', 'ead_cursos'] as $t) {
            Schema::dropIfExists($t);
        }
    }
};
