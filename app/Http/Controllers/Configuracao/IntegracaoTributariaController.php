<?php

declare(strict_types=1);

namespace App\Http\Controllers\Configuracao;

use App\Http\Controllers\Controller;
use App\Integracoes\Tributario\Fabrica;
use App\Integracoes\Tributario\FalhaTributario;
use App\Integracoes\Tributario\Normalizado;
use App\Integracoes\Tributario\SistemaTributario;
use App\Models\IntegracaoTributaria;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

/**
 * Configuração do sistema tributário integrado (GPE Tributário, outro fornecedor por REST ou
 * nenhum), geral do município ou por UG, com teste de conexão e o modelo do REST genérico.
 */
class IntegracaoTributariaController extends Controller
{
    public function index()
    {
        return Inertia::render('Configuracao/IntegracaoTributaria/Index', [
            'configuracoes' => IntegracaoTributaria::orderByRaw('ug_id is not null')->orderBy('ug_id')->get()->map(fn (IntegracaoTributaria $c) => [
                'id' => $c->id, 'ug_id' => $c->ug_id, 'driver' => $c->driver, 'nome_sistema' => $c->nome_sistema, 'base_url' => $c->base_url,
                'auth_tipo' => $c->auth_tipo, 'auth_header' => $c->auth_header, 'tem_credencial' => (string) $c->credencial !== '',
                'config' => $c->config ? json_encode($c->config, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : '',
                'timeout' => $c->timeout, 'ativo' => $c->ativo, 'recursos' => Fabrica::de($c)->recursos(),
                'ultimo_teste' => $c->ultimo_teste_em ? ['em' => $c->ultimo_teste_em->toIso8601String(), 'ok' => $c->ultimo_teste_ok, 'msg' => $c->ultimo_teste_msg] : null,
            ]),
            'ugs' => DB::table('ugs')->where('ativo', true)->orderBy('codigo')->get(['id', 'codigo', 'nome']),
            'drivers' => IntegracaoTributaria::DRIVERS, 'recursos' => SistemaTributario::RECURSOS,
            'modelo_rest' => json_encode(self::modeloRest(), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            'campos' => ['imovel' => Normalizado::IMOVEL, 'guia' => Normalizado::GUIA, 'documento' => Normalizado::DOCUMENTO],
            'eventos_url' => url('/api/integracoes/tributario/eventos'),
            'eventos' => DB::table('ged_eventos_tributarios')->orderByDesc('id')->limit(15)->get(['evento', 'sistema', 'resultado', 'mensagem', 'created_at']),
        ]);
    }

    public function salvar(Request $request, ?int $id = null)
    {
        $d = $request->validate([
            'ug_id' => ['nullable', 'integer', 'exists:ugs,id'], 'driver' => ['required', 'in:' . implode(',', array_keys(IntegracaoTributaria::DRIVERS))],
            'nome_sistema' => ['nullable', 'string', 'max:100'], 'base_url' => ['nullable', 'required_unless:driver,nenhum', 'url', 'max:300'],
            'auth_tipo' => ['required', 'in:bearer,header,basic,nenhum'], 'auth_header' => ['nullable', 'required_if:auth_tipo,header', 'string', 'max:60'],
            'credencial' => ['nullable', 'string', 'max:2000'], 'config' => ['nullable', 'string', 'max:100000'],
            'timeout' => ['nullable', 'integer', 'between:3,120'], 'ativo' => ['nullable', 'boolean'],
        ]);
        $config = null;
        if (trim((string) ($d['config'] ?? '')) !== '') {
            $config = json_decode($d['config'], true);
            if (! is_array($config)) {
                return back()->withErrors(['config' => 'Configuração dos recursos: JSON inválido (' . json_last_error_msg() . ').']);
            }
        }
        $c = $id ? IntegracaoTributaria::findOrFail($id) : new IntegracaoTributaria();
        $c->fill(['ug_id' => $d['ug_id'] ?? null, 'driver' => $d['driver'], 'nome_sistema' => $d['nome_sistema'] ?? null, 'base_url' => $d['base_url'] ?? null,
            'auth_tipo' => $d['auth_tipo'], 'auth_header' => $d['auth_header'] ?? null, 'config' => $config, 'timeout' => $d['timeout'] ?? 20,
            'ativo' => $request->boolean('ativo', true)]);
        if (($d['credencial'] ?? '') !== '') {   // em branco = mantém a atual
            $c->credencial = $d['credencial'];
        }
        if (IntegracaoTributaria::where('ug_id', $c->ug_id)->when($c->id, fn ($q) => $q->where('id', '<>', $c->id))->exists()) {
            return back()->withErrors(['ug_id' => 'Já existe configuração para ' . ($c->ug_id ? 'esta UG' : 'todas as UGs') . '.']);
        }
        $c->save();

        return back()->with('success', 'Integração com o sistema tributário salva.');
    }

    public function testar(int $id)
    {
        $c = IntegracaoTributaria::findOrFail($id);
        try {
            $r = Fabrica::de($c)->testarConexao();
        } catch (FalhaTributario $e) {
            $r = ['ok' => false, 'mensagem' => $e->getMessage()];
        }
        $c->update(['ultimo_teste_em' => now(), 'ultimo_teste_ok' => $r['ok'], 'ultimo_teste_msg' => mb_substr($r['mensagem'], 0, 500)]);

        return back()->with($r['ok'] ? 'success' : 'error', $r['mensagem']);
    }

    public function excluir(int $id)
    {
        IntegracaoTributaria::findOrFail($id)->delete();

        return back()->with('success', 'Configuração excluída.');
    }

    /** Modelo de mapeamento para um sistema de terceiros (REST genérico). */
    public static function modeloRest(): array
    {
        return [
            'teste' => ['metodo' => 'GET', 'caminho' => '/health'],
            'recursos' => [
                'imovel' => ['metodo' => 'GET', 'caminho' => '/imoveis/{inscricao}', 'raiz' => 'data', 'campos' => [
                    'inscricao' => 'inscricao', 'proprietario_nome' => 'proprietario.nome', 'proprietario_documento' => 'proprietario.cpf_cnpj',
                    'logradouro' => 'endereco.logradouro', 'numero' => 'endereco.numero', 'bairro' => 'endereco.bairro', 'cep' => 'endereco.cep',
                    'area_terreno' => 'area_terreno', 'area_construida' => 'area_construida', 'latitude' => 'lat', 'longitude' => 'lng', 'situacao' => 'situacao']],
                'guia_emitir' => ['metodo' => 'POST', 'caminho' => '/guias', 'corpo' => ['taxa' => '{taxa}', 'valor' => '{valor}', 'cpf_cnpj' => '{documento}',
                    'nome' => '{nome}', 'inscricao' => '{inscricao}', 'referencia' => 'Processo {processo}'], 'raiz' => 'data', 'campos' => [
                    'id_externo' => 'id', 'numero' => 'numero', 'valor' => 'valor', 'vencimento' => 'vencimento', 'situacao' => 'situacao',
                    'linha_digitavel' => 'linha_digitavel', 'codigo_barras' => 'codigo_barras', 'pix' => 'pix', 'pdf_url' => 'pdf']],
                'guia_consultar' => ['metodo' => 'GET', 'caminho' => '/guias/{id}', 'raiz' => 'data', 'campos' => ['id_externo' => 'id', 'situacao' => 'situacao', 'pdf_url' => 'pdf']],
                'documento_solicitar' => ['metodo' => 'POST', 'caminho' => '/documentos/{tipo}', 'corpo' => ['inscricao' => '{inscricao}', 'cpf_cnpj' => '{documento}',
                    'processo' => '{processo}', 'area' => '{area}'], 'raiz' => 'data', 'campos' => ['id_externo' => 'id', 'numero' => 'numero', 'situacao' => 'situacao', 'pdf_url' => 'pdf']],
                'documento_consultar' => ['metodo' => 'GET', 'caminho' => '/documentos/{tipo}/{id}', 'raiz' => 'data', 'campos' => ['id_externo' => 'id', 'numero' => 'numero', 'situacao' => 'situacao', 'pdf_url' => 'pdf']],
                'fiscalizacao_abrir' => ['metodo' => 'POST', 'caminho' => '/fiscalizacoes', 'corpo' => ['motivo' => '{motivo}', 'inscricao' => '{inscricao}',
                    'endereco' => '{endereco}', 'referencia' => '{processo}'], 'raiz' => 'data', 'campos' => ['id_externo' => 'id', 'numero' => 'numero', 'situacao' => 'situacao']],
            ],
        ];
    }
}