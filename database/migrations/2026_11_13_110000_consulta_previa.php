<?php

use App\Support\Permissoes;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Consulta prévia de viabilidade (TR 4.50): zonas com parâmetros urbanísticos e usos, camadas
 * próprias (viário, bairros, APP/APA, equipamentos), lotes com geometria (a zona de cada lote é
 * achada pelo ponto central) e a configuração do mapa público.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('urb_zonas', function (Blueprint $t) {
            $t->id();
            $t->string('nome', 150);
            $t->string('sigla', 20);
            $t->string('cor', 7)->default('#0ea5e9');
            $t->longText('geojson')->nullable();          // geometria (Polygon/MultiPolygon) ou Feature/FeatureCollection
            $t->jsonb('parametros')->nullable();           // to_max, ca_basico, ca_max, tp_min, gabarito, altura_max, recuo_*, lote_minimo, testada_minima
            $t->jsonb('usos')->nullable();                 // {permitidos:[], permissiveis:[], proibidos:[]}
            $t->text('observacao')->nullable();
            $t->boolean('ativo')->default(true);
            $t->timestamps();
        });
        Schema::create('urb_camadas', function (Blueprint $t) {
            $t->id();
            $t->string('nome', 150);
            $t->string('tipo', 20)->default('outra');      // viario | bairros | ambiental | equipamentos | outra
            $t->string('cor', 7)->default('#7c3aed');
            $t->longText('geojson');                       // FeatureCollection
            $t->boolean('visivel_padrao')->default(false);
            $t->unsignedInteger('ordem')->default(0);
            $t->boolean('ativo')->default(true);
            $t->timestamps();
        });
        Schema::create('urb_lotes', function (Blueprint $t) {
            $t->id();
            $t->string('inscricao', 60)->index();
            $t->string('logradouro', 200)->nullable();
            $t->string('numero', 20)->nullable();
            $t->string('bairro', 120)->nullable();
            $t->string('quadra', 20)->nullable();
            $t->string('lote', 20)->nullable();
            $t->decimal('area', 14, 2)->nullable();
            $t->text('geojson');                           // geometria do lote
            $t->decimal('centro_lat', 10, 7);
            $t->decimal('centro_lng', 10, 7);
            $t->foreignId('zona_id')->nullable()->constrained('urb_zonas')->nullOnDelete();
            $t->jsonb('dados')->nullable();
            $t->timestamps();
            $t->index(['centro_lat', 'centro_lng']);
        });
        Schema::create('urb_config', function (Blueprint $t) {
            $t->id();
            $t->decimal('centro_lat', 10, 7)->nullable();
            $t->decimal('centro_lng', 10, 7)->nullable();
            $t->unsignedTinyInteger('zoom')->default(15);
            $t->string('duvida_url', 500)->nullable();     // link do serviço de dúvida no portal; {inscricao} é trocado
            $t->text('aviso')->nullable();                 // aviso legal do mapa
            $t->timestamps();
        });

        $ids = Permissoes::sincronizarCatalogo();
        foreach (DB::table('ged_role_permissions')->where('permission_id', $ids['admin.configuracoes'] ?? 0)->pluck('role_id') as $perfil) {
            Permissoes::concederAoPerfil((int) $perfil, ['urbanismo.gerir'], $ids);
        }
    }

    public function down(): void
    {
        DB::table('ged_permissions')->where('nome', 'urbanismo.gerir')->delete();
        Schema::dropIfExists('urb_config');
        Schema::dropIfExists('urb_lotes');
        Schema::dropIfExists('urb_camadas');
        Schema::dropIfExists('urb_zonas');
    }
};