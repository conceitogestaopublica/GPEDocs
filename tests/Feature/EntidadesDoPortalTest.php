<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Http\Controllers\Api\Portal\AtoController;
use App\Http\Controllers\Api\Portal\DiarioController;
use App\Http\Controllers\Api\Portal\UgController;
use App\Models\Diario\Edicao;
use App\Models\Diario\Materia;
use App\Models\Processo\Ato;
use App\Models\Processo\AtoTipo;
use App\Models\Ug;
use App\Models\User;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Prefeitura e Câmara no mesmo portal: a lista é de cada entidade, e a edição (pelo id) vale para
 * qualquer entidade do portal — nunca para a UG que não está nele.
 *
 * Precisa de Postgres migrado — ver o comentário de ExcluirContaCidadaoTest (mesmos comandos).
 */
class EntidadesDoPortalTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        if (config('database.connections.'.config('database.default').'.driver') !== 'pgsql') {
            $this->markTestSkipped('Precisa de Postgres migrado (ver ExcluirContaCidadaoTest).');
        }
        DB::beginTransaction();
        Ug::query()->update(['portal_slug' => null]);
    }

    protected function tearDown(): void
    {
        if (DB::transactionLevel() > 0) {
            DB::rollBack();
        }
        parent::tearDown();
    }

    private function ug(string $codigo, ?string $slug, bool $ativo = true): Ug
    {
        return Ug::create(['codigo' => $codigo, 'nome' => "Entidade {$codigo}", 'portal_slug' => $slug, 'ativo' => $ativo, 'email_institucional' => "{$codigo}@example.com"]);
    }

    /** Edição publicada com um ato. */
    private function edicao(Ug $ug): Edicao
    {
        $autor = User::forceCreate(['name' => 'Autor', 'email' => Str::random(8).'@example.com', 'password' => 'x']);
        $tipo = AtoTipo::create(['nome' => 'Decreto '.$ug->codigo, 'sigla' => 'D', 'publico' => true, 'ativo' => true]);
        $ato = Ato::create(['ug_id' => $ug->id, 'tipo_id' => $tipo->id, 'numero' => 1, 'ano' => 2026, 'ementa' => "Ato {$ug->codigo}", 'data_ato' => '2026-11-24',
            'status' => 'publicado', 'autor_id' => $autor->id, 'qr_code_token' => (string) Str::uuid()]);
        $edicao = Edicao::create(['ug_id' => $ug->id, 'numero' => 1, 'data' => '2026-11-24', 'tipo' => 'ordinaria', 'status' => 'publicada',
            'publicada_em' => now(), 'qr_code_token' => (string) Str::uuid()]);
        Materia::create(['ug_id' => $ug->id, 'ato_id' => $ato->id, 'edicao_id' => $edicao->id, 'ordem' => 1]);

        return $edicao;
    }

    private function pedir(string $ug): Request
    {
        return Request::create('/', 'GET', ['ug' => $ug]);
    }

    public function test_lista_so_as_entidades_ativas_com_portal(): void
    {
        $this->ug('PM', 'pm');
        $this->ug('CM', 'camara');
        $this->ug('FP', null);
        $this->ug('EX', 'extinta', false);

        $this->assertSame([['slug' => 'pm', 'nome' => 'Entidade PM'], ['slug' => 'camara', 'nome' => 'Entidade CM']],
            app(UgController::class)->index()->getData(true)['ugs']);
    }

    public function test_diario_e_atos_sao_de_cada_entidade(): void
    {
        [$pm, $camara] = [$this->ug('PM', 'pm'), $this->ug('CM', 'camara')];
        [$daPm, $daCamara] = [$this->edicao($pm), $this->edicao($camara)];

        foreach (['pm' => [$daPm, 'Ato PM'], 'camara' => [$daCamara, 'Ato CM']] as $slug => [$edicao, $ementa]) {
            $dia = app(DiarioController::class)->dia($this->pedir($slug))->getData(true);
            $this->assertSame([$edicao->id], array_column($dia['edicoes'], 'id'));
            $this->assertSame([$ementa], array_column(app(AtoController::class)->index($this->pedir($slug))->getData(true)['atos'], 'ementa'));
            $this->assertSame([$ementa], array_column(app(DiarioController::class)->busca($this->pedir($slug))->getData(true)['resultados'], 'ementa'));
        }
    }

    public function test_edicao_pelo_id_vale_para_qualquer_entidade_do_portal(): void
    {
        $this->ug('PM', 'pm');
        $daCamara = $this->edicao($this->ug('CM', 'camara'));

        $edicao = app(DiarioController::class)->edicao($this->pedir('pm'), $daCamara->id)->getData(true)['edicao'];

        $this->assertSame(['Ato CM'], array_column($edicao['atos'], 'ementa'));
    }

    public function test_edicao_de_ug_fora_do_portal_nao_aparece(): void
    {
        $this->ug('PM', 'pm');
        $interna = $this->edicao($this->ug('FP', null));

        $this->expectException(ModelNotFoundException::class);
        app(DiarioController::class)->edicao($this->pedir('pm'), $interna->id);
    }

    public function test_pdf_de_ato_de_ug_fora_do_portal_nao_aparece(): void
    {
        $this->ug('PM', 'pm');
        $this->edicao($this->ug('FP', null));

        $this->expectException(ModelNotFoundException::class);
        app(AtoController::class)->pdf($this->pedir('pm'), (int) Ato::withoutGlobalScopes()->where('ementa', 'Ato FP')->value('id'));
    }
}
