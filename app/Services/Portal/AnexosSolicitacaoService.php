<?php

declare(strict_types=1);

namespace App\Services\Portal;

use App\Models\Portal\Cidadao;
use App\Models\Portal\Solicitacao;
use App\Models\Portal\SolicitacaoAnexo;
use App\Models\Processo\ProcessoAnexo;
use App\Tenant\TenantStorage;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Anexos da solicitação do portal (ex.: atestado médico). Chegam em base64 pela
 * API (o portal é um BFF, sem upload multipart) e vão para o disco `documentos`,
 * igual aos anexos de processo. Se a solicitação virou processo, o anexo entra
 * também no processo (com "enviado por" quando quem pediu é usuário do GPE Docs).
 */
class AnexosSolicitacaoService
{
    /** 3 × 10 MB em base64 (+33%) cabe no limite de 50 MB do nginx/PHP. */
    public const MAX_ARQUIVOS = 3;

    public const MAX_BYTES = 10 * 1024 * 1024;

    public const TIPOS = ['application/pdf' => 'pdf', 'image/jpeg' => 'jpg', 'image/png' => 'png'];

    /**
     * Decodifica e confere ANTES de registrar a solicitação.
     *
     * @param  list<array{nome:string,mime:string,conteudo:string}>  $anexos
     * @return list<array{nome:string,mime:string,bytes:string}>
     *
     * @throws CidadaoPortalException
     */
    public function preparar(array $anexos): array
    {
        if (count($anexos) > self::MAX_ARQUIVOS) {
            throw new CidadaoPortalException('Envie no máximo ' . self::MAX_ARQUIVOS . ' arquivos.');
        }

        $prontos = [];
        foreach ($anexos as $a) {
            $bytes = base64_decode((string) ($a['conteudo'] ?? ''), true);
            if ($bytes === false || $bytes === '') {
                throw new CidadaoPortalException('Arquivo inválido.');
            }
            if (strlen($bytes) > self::MAX_BYTES) {
                throw new CidadaoPortalException('Cada arquivo pode ter no máximo 10 MB.');
            }
            // O tipo vale pelo conteúdo, não pelo que o cliente declarou.
            $mime = (new \finfo(FILEINFO_MIME_TYPE))->buffer($bytes) ?: '';
            if (! isset(self::TIPOS[$mime])) {
                throw new CidadaoPortalException('Envie PDF, JPG ou PNG.');
            }
            $prontos[] = ['nome' => $this->nome((string) ($a['nome'] ?? ''), $mime), 'mime' => $mime, 'bytes' => $bytes];
        }

        return $prontos;
    }

    /** Soma dos arquivos de uma solicitação (avulsos + exigidos): em base64 cabe nos 50 MB do nginx/PHP. */
    public const MAX_BYTES_TOTAL = 30 * 1024 * 1024;

    /**
     * Documentos exigidos pelo assunto (TR 4.32.14, 4.38.18): confere os obrigatórios e
     * a extensão de cada um — pelo conteúdo, não pelo nome. O nome do documento vai na
     * frente do arquivo, como na abertura pelo GED.
     *
     * @param  array<int, array<string, mixed>>|null  $exigidos  proc_tipos_processo.anexos_exigidos
     * @param  list<array{indice:int,nome?:string,conteudo:string}>  $enviados
     * @return list<array{nome:string,mime:string,bytes:string}>
     *
     * @throws CidadaoPortalException
     */
    public function prepararExigidos(?array $exigidos, array $enviados): array
    {
        $exigidos ??= [];
        $porIndice = [];
        foreach ($enviados as $e) {
            $porIndice[(int) ($e['indice'] ?? -1)] = $e;
        }

        $prontos = [];
        foreach ($exigidos as $i => $doc) {
            $nomeDoc = (string) ($doc['nome'] ?? 'Documento');
            $enviado = $porIndice[$i] ?? null;
            if (! $enviado) {
                if ($doc['obrigatorio'] ?? true) {
                    throw new CidadaoPortalException("Envie o documento \"{$nomeDoc}\".");
                }
                continue;
            }
            [$pronto] = $this->preparar([$enviado]);
            $permitidas = array_filter(array_map(fn ($e) => strtolower(trim($e, " .\t")), explode(',', (string) ($doc['extensoes'] ?? ''))));
            $ext = self::TIPOS[$pronto['mime']];
            if ($permitidas && ! in_array($ext, $permitidas, true) && ! ($ext === 'jpg' && in_array('jpeg', $permitidas, true))) {
                throw new CidadaoPortalException("\"{$nomeDoc}\" deve ser " . implode(', ', array_map('strtoupper', $permitidas)) . '.');
            }
            $pronto['nome'] = Str::limit("{$nomeDoc} — {$pronto['nome']}", 250, '');
            $prontos[] = $pronto;
        }
        if (array_diff(array_keys($porIndice), array_keys($exigidos))) {
            throw new CidadaoPortalException('Documento enviado não corresponde ao assunto.');
        }

        return $prontos;
    }

    /** @param  list<array{nome:string,mime:string,bytes:string}>  $prontos */
    public function conferirTotal(array $prontos): void
    {
        if (array_sum(array_map(fn ($a) => strlen($a['bytes']), $prontos)) > self::MAX_BYTES_TOTAL) {
            throw new CidadaoPortalException('Os arquivos juntos passam de 30 MB.');
        }
    }

    /** @param  list<array{nome:string,mime:string,bytes:string}>  $prontos */
    public function gravar(Solicitacao $solicitacao, ?Cidadao $cidadao, array $prontos): void
    {
        foreach ($prontos as $a) {
            $path = TenantStorage::pasta('portal-solicitacoes') . '/' . Str::uuid() . '.' . self::TIPOS[$a['mime']];
            Storage::disk('documentos')->put($path, $a['bytes']);
            $hash = hash('sha256', $a['bytes']);

            SolicitacaoAnexo::create([
                'solicitacao_id' => $solicitacao->id,
                'nome'           => $a['nome'],
                'arquivo_path'   => $path,
                'tamanho'        => strlen($a['bytes']),
                'mime_type'      => $a['mime'],
                'hash_sha256'    => $hash,
            ]);

            // Vai também para o processo; sem usuário do GED (cidadão comum) fica sem "enviado por"
            if ($solicitacao->processo_id) {
                ProcessoAnexo::create([
                    'processo_id'  => $solicitacao->processo_id,
                    'nome'         => $a['nome'],
                    'arquivo_path' => $path,
                    'tamanho'      => strlen($a['bytes']),
                    'mime_type'    => $a['mime'],
                    'hash_sha256'  => $hash,
                    'enviado_por'  => $cidadao?->user_id,
                ]);
            }
        }
    }

    /** Processo aberto depois (pelo atendente): leva os anexos que já vieram com o pedido. */
    public function copiarParaProcesso(Solicitacao $solicitacao, int $enviadoPor): void
    {
        foreach ($solicitacao->anexos as $a) {
            ProcessoAnexo::create([
                'processo_id'  => $solicitacao->processo_id,
                'nome'         => $a->nome,
                'arquivo_path' => $a->arquivo_path,
                'tamanho'      => $a->tamanho,
                'mime_type'    => $a->mime_type,
                'hash_sha256'  => $a->hash_sha256,
                'enviado_por'  => $enviadoPor,
            ]);
        }
    }

    private function nome(string $nome, string $mime): string
    {
        $base = trim(preg_replace('/[^\pL\pN ._()-]/u', '', pathinfo($nome, PATHINFO_FILENAME)) ?? '');

        return Str::limit($base !== '' ? $base : 'anexo', 150, '') . '.' . self::TIPOS[$mime];
    }
}
