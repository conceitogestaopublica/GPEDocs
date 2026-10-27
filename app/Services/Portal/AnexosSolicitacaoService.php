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
 * igual aos anexos de processo. Se a solicitação virou processo e quem pediu é
 * usuário do GPE Docs, o anexo entra também no processo.
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

            // No processo, o anexo precisa de um usuário que o enviou.
            if ($solicitacao->processo_id && $cidadao?->user_id) {
                ProcessoAnexo::create([
                    'processo_id'  => $solicitacao->processo_id,
                    'nome'         => $a['nome'],
                    'arquivo_path' => $path,
                    'tamanho'      => strlen($a['bytes']),
                    'mime_type'    => $a['mime'],
                    'hash_sha256'  => $hash,
                    'enviado_por'  => $cidadao->user_id,
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
