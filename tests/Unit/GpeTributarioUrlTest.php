<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Integracoes\Tributario\Fabrica;
use App\Integracoes\Tributario\GpeTributario;
use App\Models\IntegracaoTributaria;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/** GPE Tributário: o usuário informa só o endereço base; o /api/v1 é acrescentado sozinho. */
class GpeTributarioUrlTest extends TestCase
{
    public static function enderecos(): array
    {
        return [
            'sem barra'        => ['https://x.tributario.gpe.cloud'],
            'com barra'        => ['https://x.tributario.gpe.cloud/'],
            'com espaços'      => ['  https://x.tributario.gpe.cloud//  '],
            'já com /api'      => ['https://x.tributario.gpe.cloud/api'],
            'já com /api/v1'   => ['https://x.tributario.gpe.cloud/api/v1'],
            'já com /api/v1/'  => ['https://x.tributario.gpe.cloud/api/v1/'],
        ];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('enderecos')]
    public function test_guarda_o_base_e_chama_com_api_v1(string $digitado): void
    {
        $this->assertSame('https://x.tributario.gpe.cloud', GpeTributario::urlBase($digitado));
        $this->assertSame('https://x.tributario.gpe.cloud/api/v1', GpeTributario::urlApi($digitado));
    }

    public function test_vazio_continua_vazio(): void
    {
        $this->assertNull(GpeTributario::urlBase(''));
        $this->assertNull(GpeTributario::urlApi(null));
    }

    public function test_o_teste_de_conexao_chama_o_endereco_com_api_v1(): void
    {
        Http::fake(['*' => Http::response(['ok' => true], 200)]);
        $cfg = new IntegracaoTributaria(['driver' => 'gpe', 'base_url' => 'https://x.tributario.gpe.cloud/', 'auth_tipo' => 'bearer',
            'credencial' => 'tok', 'timeout' => 5]);

        $r = Fabrica::de($cfg)->testarConexao();

        $this->assertTrue($r['ok']);
        Http::assertSent(fn (Request $req) => $req->url() === 'https://x.tributario.gpe.cloud/api/v1/integracao/ged/status'
            && $req->header('X-Integracao-Token') === ['tok']);
    }
}
