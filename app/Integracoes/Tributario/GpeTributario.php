<?php

declare(strict_types=1);

namespace App\Integracoes\Tributario;

use App\Models\IntegracaoTributaria;

/**
 * GPE Tributário: o REST genérico já configurado com as rotas de integração do Tributário
 * (/api/v1/integracao/ged/*, token de consumidor no header X-Integracao-Token, município pelo
 * subdomínio do endereço). Quem configura só informa o endereço e o token; "config" pode
 * sobrescrever recursos ou acrescentar headers (ex.: x-tenant-subdomain).
 * Contrato do lado do Tributário: docs/integracao-tributaria.md
 */
final class GpeTributario
{
    public static function configurar(IntegracaoTributaria $cfg): IntegracaoTributaria
    {
        $c = clone $cfg;
        $c->nome_sistema = $cfg->nome_sistema ?: 'GPE Tributário';
        if (in_array($cfg->auth_tipo, ['bearer', 'nenhum', null], true)) {
            $c->auth_tipo = 'header';
            $c->auth_header = 'X-Integracao-Token';
        }
        $proprio = $cfg->config ?? [];
        $c->config = ['teste' => $proprio['teste'] ?? ['metodo' => 'GET', 'caminho' => '/integracao/ged/status'],
            'headers' => $proprio['headers'] ?? [],
            'recursos' => array_merge(self::recursos(), $proprio['recursos'] ?? [])];

        return $c;
    }

    /** As respostas do Tributário já vêm com os nomes de campo do GED (Normalizado), dentro de "data". */
    public static function recursos(): array
    {
        $igual = fn (array $modelo) => array_combine(array_keys($modelo), array_keys($modelo));

        return [
            'imovel' => ['metodo' => 'GET', 'caminho' => '/integracao/ged/imoveis/{inscricao}', 'raiz' => 'data', 'campos' => $igual(Normalizado::IMOVEL)],
            'guia_emitir' => ['metodo' => 'POST', 'caminho' => '/integracao/ged/guias', 'raiz' => 'data', 'campos' => $igual(Normalizado::GUIA),
                'corpo' => ['taxa' => '{taxa}', 'valor' => '{valor}', 'documento' => '{documento}', 'nome' => '{nome}', 'inscricao' => '{inscricao}',
                    'processo' => '{processo}', 'descricao' => '{descricao}', 'vencimento' => '{vencimento}']],
            'guia_consultar' => ['metodo' => 'GET', 'caminho' => '/integracao/ged/guias/{id}', 'raiz' => 'data', 'campos' => $igual(Normalizado::GUIA)],
            'documento_solicitar' => ['metodo' => 'POST', 'caminho' => '/integracao/ged/documentos/{tipo}', 'raiz' => 'data', 'campos' => $igual(Normalizado::DOCUMENTO),
                'corpo' => ['inscricao' => '{inscricao}', 'documento' => '{documento}', 'nome' => '{nome}', 'processo' => '{processo}', 'assunto' => '{assunto}',
                    'area' => '{area}', 'responsavel_tecnico' => '{responsavel_tecnico}', 'registro_profissional' => '{registro_profissional}', 'observacao' => '{observacao}']],
            'documento_consultar' => ['metodo' => 'GET', 'caminho' => '/integracao/ged/documentos/{tipo}/{id}', 'raiz' => 'data', 'campos' => $igual(Normalizado::DOCUMENTO)],
            'fiscalizacao_abrir' => ['metodo' => 'POST', 'caminho' => '/integracao/ged/fiscalizacoes', 'raiz' => 'data',
                'campos' => ['id_externo' => 'id_externo', 'numero' => 'numero', 'situacao' => 'situacao'],
                'corpo' => ['motivo' => '{motivo}', 'inscricao' => '{inscricao}', 'endereco' => '{endereco}', 'processo' => '{processo}', 'descricao' => '{descricao}',
                    'latitude' => '{latitude}', 'longitude' => '{longitude}']],
        ];
    }
}