<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/** Lista de usuários de uma UG: os dela, os externos e os sem gestora informada. */
class UsuariosVisiveisNaUgTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Só as colunas que a regra usa (SQLite em memória — phpunit.xml).
        Schema::create('ugs', function ($t) {
            $t->id();
            $t->string('nome');
        });
        Schema::create('users', function ($t) {
            $t->id();
            $t->string('name');
            $t->string('tipo', 10)->default('interno');
            $t->unsignedBigInteger('ug_id')->nullable();
        });
        Schema::create('user_ugs', function ($t) {
            $t->id();
            $t->unsignedBigInteger('user_id');
            $t->unsignedBigInteger('ug_id');
            $t->boolean('principal')->default(false);
            $t->timestamps();
        });
        DB::table('ugs')->insert([['id' => 1, 'nome' => 'Prefeitura'], ['id' => 2, 'nome' => 'Câmara']]);
    }

    private function usuario(string $nome, ?int $ugId, array $vinculos = [], string $tipo = 'interno'): void
    {
        $id = DB::table('users')->insertGetId(['name' => $nome, 'tipo' => $tipo, 'ug_id' => $ugId]);
        foreach ($vinculos as $ug) {
            DB::table('user_ugs')->insert(['user_id' => $id, 'ug_id' => $ug]);
        }
    }

    private function nomes(int $ugId): array
    {
        return User::visiveisNaUg($ugId)->orderBy('name')->pluck('name')->all();
    }

    public function test_usuario_sem_gestora_aparece_em_todas_as_ugs(): void
    {
        $this->usuario('sem gestora', null);

        $this->assertSame(['sem gestora'], $this->nomes(1));
        $this->assertSame(['sem gestora'], $this->nomes(2));
    }

    public function test_usuario_vinculado_so_aparece_nas_suas_ugs(): void
    {
        $this->usuario('da câmara', 2, [2]);
        $this->usuario('das duas', 2, [1, 2]);

        $this->assertSame(['das duas'], $this->nomes(1));
        $this->assertSame(['da câmara', 'das duas'], $this->nomes(2));
    }

    public function test_conta_antiga_sem_vinculo_vale_pela_ug_primaria(): void
    {
        $this->usuario('antiga da prefeitura', 1);

        $this->assertSame(['antiga da prefeitura'], $this->nomes(1));
        $this->assertSame([], $this->nomes(2));
    }

    public function test_externo_continua_em_todas(): void
    {
        $this->usuario('externo', 2, [], 'externo');

        $this->assertSame(['externo'], $this->nomes(1));
    }
}
