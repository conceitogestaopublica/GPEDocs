<?php

declare(strict_types=1);

namespace App\Integracoes\Tramita;

use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Barramento simulado para homologação interna (sem credencial): guarda os trâmites em tramita_simulador.
 * Os entes de destino são fictícios; o "Órgão que recusa" sempre recusa, os demais concluem na primeira
 * sincronização. simularEntrada() faz um ente externo enviar um ofício ou processo para nós.
 */
class Simulador implements Barramento
{
    private const ENTES = [
        ['repositorio' => '1', 'estrutura' => 'SIM-0001', 'nome' => 'Ministério da Gestão e da Inovação — Protocolo Central (simulado)'],
        ['repositorio' => '1', 'estrutura' => 'SIM-0002', 'nome' => 'Governo do Estado — Secretaria da Fazenda (simulado)'],
        ['repositorio' => '2', 'estrutura' => 'SIM-0003', 'nome' => 'Prefeitura Municipal vizinha — Gabinete (simulado)'],
        ['repositorio' => '1', 'estrutura' => 'SIM-0099', 'nome' => 'Órgão que recusa tudo (simulado, para testar a recusa)'],
    ];

    public function testar(): string
    {
        return 'Simulador ativo: o envio, o recebimento e a recusa funcionam sem o barramento real.';
    }

    public function estruturas(string $busca): array
    {
        $b = Str::lower(Str::ascii($busca));

        return array_values(array_filter(self::ENTES, fn ($e) => $b === '' || str_contains(Str::lower(Str::ascii($e['nome'])), $b)));
    }

    public function enviar(array $metadados, array $componentes, array $destino): array
    {
        if (! collect(self::ENTES)->contains('estrutura', $destino['estrutura'] ?? null)) {
            throw new FalhaTramita('Estrutura de destino não encontrada no barramento.');
        }
        foreach ($componentes as $c) {
            if (hash('sha256', $c['conteudo']) !== $c['hash']) {
                throw new FalhaTramita("Hash do componente {$c['nome']} não confere.");
            }
        }
        $idt = (string) random_int(100000000, 999999999);
        $id = DB::table('tramita_simulador')->insertGetId(['idt' => $idt, 'nre' => '', 'sentido' => 'para_fora', 'destino_estrutura' => $destino['estrutura'],
            'status' => 'pendente', 'pacote' => json_encode(['metadados' => $metadados, 'componentes' => array_map(fn ($c) => array_diff_key($c, ['conteudo' => 1]), $componentes)], JSON_UNESCAPED_UNICODE),
            'created_at' => now(), 'updated_at' => now()]);
        $nre = $this->nre($id);
        DB::table('tramita_simulador')->where('id', $id)->update(['nre' => $nre]);

        return ['nre' => $nre, 'idt' => $idt, 'enviado_em' => now()->toIso8601String()];
    }

    public function situacao(string $idt): array
    {
        $r = DB::table('tramita_simulador')->where('idt', $idt)->first();
        if (! $r) {
            throw new FalhaTramita("Trâmite {$idt} não encontrado no barramento.");
        }
        if ($r->status === 'pendente') {
            // o destino processa na primeira consulta
            if ($r->destino_estrutura === 'SIM-0099') {
                DB::table('tramita_simulador')->where('id', $r->id)->update(['status' => 'recusado', 'updated_at' => now()]);

                return ['status' => 'recusado', 'motivo' => 'destino_incorreto', 'justificativa' => 'O órgão simulado recusa todos os trâmites (teste da recusa).'];
            }
            $pacote = json_decode($r->pacote, true);
            $recibo = ['data_hora' => now()->toIso8601String(), 'nre' => $r->nre, 'idt' => $r->idt,
                'hashes' => array_column($pacote['componentes'] ?? [], 'hash'), 'recebido_por' => collect(self::ENTES)->firstWhere('estrutura', $r->destino_estrutura)['nome'] ?? ''];
            DB::table('tramita_simulador')->where('id', $r->id)->update(['status' => 'recebido', 'recibo' => json_encode($recibo, JSON_UNESCAPED_UNICODE), 'updated_at' => now()]);

            return ['status' => 'concluido', 'recibo' => $recibo];
        }

        return $r->status === 'recusado' ? ['status' => 'recusado', 'motivo' => 'destino_incorreto', 'justificativa' => 'Recusado pelo destino (simulado).']
            : ['status' => 'concluido', 'recibo' => json_decode((string) $r->recibo, true) ?: []];
    }

    public function pendencias(): array
    {
        return DB::table('tramita_simulador')->where('sentido', 'para_nos')->where('status', 'pendente')->orderBy('id')->get(['idt', 'nre'])
            ->map(fn ($r) => ['idt' => $r->idt, 'nre' => $r->nre])->all();
    }

    public function receber(string $idt): array
    {
        $r = DB::table('tramita_simulador')->where('idt', $idt)->where('sentido', 'para_nos')->first();
        if (! $r) {
            throw new FalhaTramita("Trâmite {$idt} não está disponível para recebimento.");
        }
        $p = json_decode($r->pacote, true);

        return ['nre' => $r->nre, 'metadados' => $p['metadados'], 'remetente' => $p['remetente'],
            'componentes' => array_map(fn ($c) => ['nome' => $c['nome'], 'mime' => $c['mime'], 'conteudo' => base64_decode($c['base64']), 'hash' => $c['hash']], $p['componentes'])];
    }

    public function confirmarRecebimento(string $idt, array $hashes): array
    {
        $r = DB::table('tramita_simulador')->where('idt', $idt)->first();
        $recibo = ['data_hora' => now()->toIso8601String(), 'nre' => $r?->nre, 'idt' => $idt, 'hashes' => $hashes];
        DB::table('tramita_simulador')->where('idt', $idt)->update(['status' => 'recebido', 'recibo' => json_encode($recibo), 'updated_at' => now()]);

        return $recibo;
    }

    public function recusar(string $idt, string $motivo, string $justificativa): void
    {
        DB::table('tramita_simulador')->where('idt', $idt)->update(['status' => 'recusado',
            'recibo' => json_encode(['recusa' => $motivo, 'justificativa' => $justificativa, 'data_hora' => now()->toIso8601String()], JSON_UNESCAPED_UNICODE), 'updated_at' => now()]);
    }

    /** Um ente externo envia um ofício ou processo para nós (para testar o recebimento). */
    public function simularEntrada(string $tipo, array $nosso): string
    {
        $ente = self::ENTES[array_rand(array_slice(self::ENTES, 0, 3))];
        $numero = ($tipo === 'oficio' ? 'Ofício ' : 'Processo ') . random_int(100, 999) . '/' . now()->year;
        $assunto = $tipo === 'oficio' ? 'Solicitação de informações sobre convênio' : 'Repasse de documentação do convênio estadual';
        $html = '<h2 style="font-family:Helvetica">' . e($ente['nome']) . '</h2><p style="font-family:Helvetica">' . e($numero) . ' — ' . e($assunto)
            . '</p><p style="font-family:Helvetica">Documento de teste enviado pelo simulador do Tramita GOV.BR em ' . now('America/Sao_Paulo')->format('d/m/Y H:i') . '.</p>';
        $pdf = Pdf::loadHTML($html)->output();
        $componentes = [['nome' => Str::slug($numero) . '.pdf', 'mime' => 'application/pdf', 'base64' => base64_encode($pdf), 'hash' => hash('sha256', $pdf), 'tamanho' => strlen($pdf)]];
        $idt = (string) random_int(100000000, 999999999);
        $id = DB::table('tramita_simulador')->insertGetId(['idt' => $idt, 'nre' => '', 'sentido' => 'para_nos', 'destino_estrutura' => $nosso['estrutura'] ?? null, 'status' => 'pendente',
            'pacote' => json_encode(['remetente' => $ente, 'metadados' => ['tipo' => $tipo, 'numero' => $numero, 'assunto' => $assunto,
                'descricao' => "Encaminhado pelo {$ente['nome']} via Tramita GOV.BR.", 'interessados' => [$ente['nome']], 'documentos' => [['nome' => $componentes[0]['nome'], 'hash' => $componentes[0]['hash']]]],
                'componentes' => $componentes], JSON_UNESCAPED_UNICODE), 'created_at' => now(), 'updated_at' => now()]);
        DB::table('tramita_simulador')->where('id', $id)->update(['nre' => $this->nre($id)]);

        return $idt;
    }

    private function nre(int $id): string
    {
        return now()->format('Y') . str_pad((string) $id, 12, '0', STR_PAD_LEFT);
    }
}
