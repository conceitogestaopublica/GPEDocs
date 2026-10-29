<?php

declare(strict_types=1);

namespace App\Integracoes\Tributario;

/**
 * Sistema tributário de terceiros por REST, sem código: para cada recurso a configuração diz o
 * método, o caminho (com {marcadores}), o corpo (JSON com {marcadores}), onde fica o resultado na
 * resposta ("raiz", notação de ponto) e de onde vem cada campo normalizado. Recurso sem
 * configuração não é oferecido. Exemplo de config["recursos"]["imovel"]:
 *
 *   {"metodo": "GET", "caminho": "/imoveis/{inscricao}", "raiz": "data",
 *    "campos": {"inscricao": "inscricao", "proprietario_nome": "proprietario.nome", "logradouro": "endereco.rua", ...}}
 */
final class RestGenerico extends AdaptadorHttp
{
    private const MODELOS = [
        self::IMOVEL => Normalizado::IMOVEL, self::GUIA_EMITIR => Normalizado::GUIA, self::GUIA_CONSULTAR => Normalizado::GUIA,
        self::DOCUMENTO_SOLICITAR => Normalizado::DOCUMENTO, self::DOCUMENTO_CONSULTAR => Normalizado::DOCUMENTO,
        self::FISCALIZACAO_ABRIR => ['id_externo' => '', 'numero' => '', 'situacao' => ''],
    ];

    public function recursos(): array
    {
        return array_values(array_filter(array_keys(self::RECURSOS), fn ($r) => ! empty($this->recurso($r)['caminho'])));
    }

    public function testarConexao(): array
    {
        $t = $this->cfg->config['teste'] ?? null;
        if (empty($t['caminho'])) {
            return ['ok' => true, 'mensagem' => 'Sem chamada de teste configurada; recursos configurados: ' . (implode(', ', $this->recursos()) ?: 'nenhum') . '.'];
        }
        $r = $this->chamar($t['metodo'] ?? 'GET', $t['caminho']);

        return ['ok' => $r->successful(), 'mensagem' => "Resposta {$r->status()} de {$this->nome()}."];
    }

    public function consultarImovel(string $inscricao): ?array
    {
        return $this->executar(self::IMOVEL, ['inscricao' => $inscricao], true);
    }

    public function emitirGuia(array $dados): array
    {
        $g = $this->executar(self::GUIA_EMITIR, $dados) ?? throw new FalhaTributario('A guia não foi emitida.');

        return ['situacao' => Normalizado::situacaoGuia($g['situacao'] ?? null)] + $g;
    }

    public function consultarGuia(string $idExterno): array
    {
        $g = $this->executar(self::GUIA_CONSULTAR, ['id' => $idExterno]) ?? throw new FalhaTributario('Guia não encontrada.');

        return ['situacao' => Normalizado::situacaoGuia($g['situacao'] ?? null)] + $g;
    }

    public function solicitarDocumento(string $tipo, array $dados): array
    {
        return self::minusculas($this->executar(self::DOCUMENTO_SOLICITAR, ['tipo' => $tipo] + $dados, false, $tipo) ?? throw new FalhaTributario('O documento não foi solicitado.'));
    }

    public function consultarDocumento(string $tipo, string $idExterno): array
    {
        return self::minusculas($this->executar(self::DOCUMENTO_CONSULTAR, ['tipo' => $tipo, 'id' => $idExterno], false, $tipo) ?? throw new FalhaTributario('Documento não encontrado.'));
    }

    public function abrirFiscalizacao(array $dados): array
    {
        return self::minusculas($this->executar(self::FISCALIZACAO_ABRIR, $dados) ?? throw new FalhaTributario('A fiscalização não foi aberta.'));
    }

    /** Config do recurso; documentos podem ter config própria por tipo: documento_solicitar.habitese */
    private function recurso(string $recurso, ?string $tipo = null): array
    {
        $r = $this->cfg->config['recursos'] ?? [];

        return ($tipo ? ($r["{$recurso}.{$tipo}"] ?? null) : null) ?? ($r[$recurso] ?? []);
    }

    private function executar(string $recurso, array $dados, bool $nuloSe404 = false, ?string $tipo = null): ?array
    {
        $c = $this->recurso($recurso, $tipo);
        if (empty($c['caminho'])) {
            throw new FalhaTributario("{$this->nome()} não oferece: " . (self::RECURSOS[$recurso] ?? $recurso) . '.');
        }
        $caminho = preg_replace_callback('/\{(\w+)\}/', fn ($m) => rawurlencode((string) ($dados[$m[1]] ?? '')), (string) $c['caminho']);
        $corpo = isset($c['corpo']) && is_array($c['corpo']) ? self::preencher($c['corpo'], $dados) : [];
        $r = $this->chamar($c['metodo'] ?? 'GET', $caminho, $corpo);
        if ($r->status() === 404) {
            return $nuloSe404 ? null : throw new FalhaTributario("{$this->nome()}: não encontrado.");
        }
        $raiz = ($c['raiz'] ?? '') !== '' ? data_get($r->json(), $c['raiz']) : $r->json();
        if (! is_array($raiz) || $raiz === []) {
            return $nuloSe404 ? null : throw new FalhaTributario("{$this->nome()} respondeu sem dados.");
        }

        return Normalizado::mapear($raiz, (array) ($c['campos'] ?? []), self::MODELOS[$recurso]);
    }

    /** Situação em minúsculas, como as do GED (emitido, cancelado…). */
    private static function minusculas(array $r): array
    {
        return isset($r['situacao']) && is_string($r['situacao']) ? ['situacao' => mb_strtolower($r['situacao'])] + $r : $r;
    }

    /** Troca {marcador} pelos dados; marcador sozinho mantém o tipo (número, lista). */
    private static function preencher(mixed $modelo, array $dados): mixed
    {
        if (is_array($modelo)) {
            return array_map(fn ($v) => self::preencher($v, $dados), $modelo);
        }
        if (is_string($modelo) && preg_match('/^\{(\w+)\}$/', $modelo, $m)) {
            return $dados[$m[1]] ?? null;
        }

        return is_string($modelo) ? preg_replace_callback('/\{(\w+)\}/', fn ($m) => (string) ($dados[$m[1]] ?? ''), $modelo) : $modelo;
    }
}