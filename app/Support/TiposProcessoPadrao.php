<?php

declare(strict_types=1);

namespace App\Support;

use Illuminate\Support\Facades\DB;

/**
 * Tipos de processo padrão de todo município (decisão do responsável pelo produto,
 * 2026-10-27). As etapas não têm setor fixo — o organograma muda de um município
 * para outro; quem tramita escolhe o setor. Idempotente pela sigla: tipo que já
 * existe não é tocado (a Prefeitura pode ter ajustado).
 */
final class TiposProcessoPadrao
{
    public const TIPOS = [
        [
            'sigla' => 'REQ', 'nome' => 'Requerimento Geral', 'categoria' => 'administrativo', 'dias' => 15,
            'descricao' => 'Requerimentos do cidadão ou do servidor que não têm tipo próprio.',
            'etapas' => [['Triagem', 'analise'], ['Análise do setor', 'analise'], ['Decisão', 'aprovacao'], ['Arquivamento', 'arquivamento']],
        ],
        [
            'sigla' => 'RH', 'nome' => 'Requerimento de RH', 'categoria' => 'rh', 'dias' => 10,
            'descricao' => 'Férias, declarações, vida funcional e demais pedidos do servidor ao RH.',
            'etapas' => [['Recebimento no RH', 'analise'], ['Manifestação da chefia', 'parecer'], ['Decisão', 'aprovacao'], ['Registro e arquivamento', 'arquivamento']],
        ],
        [
            'sigla' => 'LIC', 'nome' => 'Licença e Afastamento', 'categoria' => 'rh', 'dias' => 5,
            'descricao' => 'Atestados médicos e licenças do servidor.',
            'etapas' => [['Recebimento no RH', 'analise'], ['Avaliação / perícia', 'parecer'], ['Decisão', 'aprovacao'], ['Registro e arquivamento', 'arquivamento']],
        ],
        [
            'sigla' => 'OUV', 'nome' => 'Manifestação de Ouvidoria', 'categoria' => 'administrativo', 'dias' => 30,
            'descricao' => 'Reclamações, denúncias, sugestões e elogios (Lei 13.460/2017: resposta em até 30 dias, prorrogáveis).',
            'etapas' => [['Triagem', 'analise'], ['Apuração no setor', 'analise'], ['Resposta ao manifestante', 'despacho'], ['Arquivamento', 'arquivamento']],
        ],
        [
            'sigla' => 'LAI', 'nome' => 'Pedido de Acesso à Informação', 'categoria' => 'juridico', 'dias' => 20,
            'descricao' => 'Pedidos pela Lei 12.527/2011 (LAI): resposta em até 20 dias, prorrogáveis por mais 10.',
            'etapas' => [['Triagem', 'analise'], ['Busca da informação', 'analise'], ['Resposta ao requerente', 'despacho'], ['Arquivamento', 'arquivamento']],
        ],
        [
            'sigla' => 'TRIB', 'nome' => 'Requerimento Tributário', 'categoria' => 'financeiro', 'dias' => 30,
            'descricao' => 'Isenção, revisão de lançamento, restituição e demais pedidos tributários.',
            'etapas' => [['Triagem', 'analise'], ['Análise fiscal', 'analise'], ['Parecer', 'parecer'], ['Decisão', 'aprovacao'], ['Arquivamento', 'arquivamento']],
        ],
    ];

    /**
     * Cria no banco corrente os tipos que faltam. Devolve quantos criou; null se o banco
     * ainda não tem usuário para constar como autor (rodar de novo depois do primeiro).
     */
    public static function garantir(): ?int
    {
        $autor = DB::table('users')->orderByDesc('super_admin')->orderBy('id')->value('id');
        if (! $autor) {
            return null;
        }

        $criados = 0;
        foreach (self::TIPOS as $t) {
            if (DB::table('proc_tipos_processo')->whereRaw('upper(sigla) = ?', [$t['sigla']])->exists()) {
                continue;
            }
            DB::transaction(function () use ($t, $autor) {
                $horas = $t['dias'] * 24;
                $tipoId = DB::table('proc_tipos_processo')->insertGetId([
                    'nome'             => $t['nome'],
                    'descricao'        => $t['descricao'],
                    'sigla'            => $t['sigla'],
                    'categoria'        => $t['categoria'],
                    'sla_padrao_horas' => $horas,
                    'ativo'            => true,
                    'criado_por'       => $autor,
                    'created_at'       => now(),
                    'updated_at'       => now(),
                ]);
                foreach ($t['etapas'] as $i => [$nome, $tipo]) {
                    DB::table('proc_tipo_etapas')->insert([
                        'tipo_processo_id' => $tipoId,
                        'nome'             => $nome,
                        'ordem'            => $i + 1,
                        'tipo'             => $tipo,
                        'obrigatorio'      => true,
                        'ativo'            => true,
                        'created_at'       => now(),
                        'updated_at'       => now(),
                    ]);
                }
            });
            $criados++;
        }

        return $criados;
    }
}
