<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\Assinatura;
use App\Models\Documento;
use App\Models\Processo\Circular;
use App\Models\Processo\Memorando;
use App\Models\Processo\Oficio;
use App\Models\SolicitacaoAssinatura;
use App\Models\User;
use App\Models\Versao;
use App\Services\ConviteAssinaturaService;
use App\Services\FluxoAssinaturaService;
use App\Tenant\TenantStorage;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

/**
 * Memorando, circular e ofício como "documento redigido no editor": o mesmo PDF da tela de
 * download, o rótulo e o endereço — e o envio desse PDF para assinatura quando o texto pede
 * (marcador "!", TR 4.16.18).
 */
final class DocumentoRedigido
{
    /** tipo => [modelo, view do PDF, prefixo da URL, rótulo, relações do PDF] */
    public const TIPOS = [
        'memorando' => [Memorando::class, 'pdf.memorando', '/memorandos', 'Memorando', ['remetente', 'destinatarios.usuario', 'destinatarios.unidade', 'respostas.usuario']],
        'circular'  => [Circular::class, 'pdf.circular', '/circulares', 'Circular', ['remetente', 'destinatarios.usuario']],
        'oficio'    => [Oficio::class, 'pdf.oficio', '/oficios', 'Ofício', ['remetente', 'respostas.usuario']],
        'parecer'   => [\App\Models\Processo\Parecer::class, 'pdf.parecer', '/pareceres', 'Parecer', ['remetente', 'processo', 'memorando', 'anexos']],
    ];

    public static function buscar(string $tipo, int $id): ?Model
    {
        $cfg = self::TIPOS[$tipo] ?? null;

        return $cfg ? $cfg[0]::query()->with($cfg[4])->find($id) : null;
    }

    public static function rotulo(string $tipo): string
    {
        return self::TIPOS[$tipo][3] ?? ($tipo === 'processo' ? 'Processo' : ucfirst($tipo));
    }

    public static function url(string $tipo, int $id): string
    {
        return ($tipo === 'processo' ? '/processos' : (self::TIPOS[$tipo][2] ?? '/')) . "/{$id}";
    }

    public static function pdfBytes(string $tipo, Model $doc): string
    {
        [, $view, $prefixo] = self::TIPOS[$tipo];

        return Relatorio::pdf($view, [
            $tipo       => $doc,
            'qrCodeUrl' => url("{$prefixo}/verificar/{$doc->qr_code_token}"),
            'ug'        => \App\Models\Ug::find($doc->ug_id),
        ])->output();
    }

    /**
     * Leva o PDF do documento ao GED (sem pasta, como a decisão do processo) e pede a assinatura
     * dos servidores e dos externos. Devolve o id do documento do GED.
     *
     * @param list<int> $servidores
     * @param list<array{nome: string, cpf: string, email: ?string, telefone: ?string}> $externos
     */
    public static function pedirAssinatura(string $tipo, Model $doc, array $servidores, array $externos, User $autor): int
    {
        $nome = self::rotulo($tipo) . ' ' . $doc->numero;

        return self::pedirAssinaturaDoPdf(self::pdfBytes($tipo, $doc), $nome, (string) $doc->assunto, self::rotulo($tipo),
            collect([$nome, $doc->assunto, Variaveis::textoPuro($doc->conteudoFinal())])->filter()->implode(' | '), $servidores, $externos, $autor);
    }

    /**
     * Mesmo envio, com o PDF já pronto (ex.: o PDF/A do ato oficial).
     *
     * @param list<int> $servidores
     * @param list<array{nome: string, cpf: string, email: ?string, telefone: ?string}> $externos
     */
    public static function pedirAssinaturaDoPdf(string $bytes, string $nome, string $descricao, string $tipoDocumental, string $textoBusca,
        array $servidores, array $externos, User $autor): int
    {
        $path = TenantStorage::pasta('documentos') . '/' . date('Y/m') . '/' . uniqid() . '-' . str_replace('/', '-', $nome) . '.pdf';
        Storage::disk('documentos')->put($path, $bytes);

        $documento = Documento::create([
            'nome' => $nome, 'descricao' => $descricao,
            'tipo_documental_id' => TiposDocumentais::id($tipoDocumental),
            'pasta_id' => null, 'versao_atual' => 1, 'tamanho' => strlen($bytes), 'mime_type' => 'application/pdf',
            'autor_id' => $autor->id, 'status' => 'rascunho',
            'ocr_texto' => $textoBusca,
        ]);
        Versao::create([
            'documento_id' => $documento->id, 'versao' => 1, 'arquivo_path' => $path, 'tamanho' => strlen($bytes),
            'hash_sha256' => hash('sha256', $bytes), 'autor_id' => $autor->id,
            'comentario' => "Gerado do {$nome} para as assinaturas pedidas no texto",
        ]);
        $solicitacao = SolicitacaoAssinatura::create([
            'documento_id' => $documento->id, 'solicitante_id' => $autor->id, 'status' => 'pendente',
            'mensagem' => "Assinatura pedida no texto do {$nome}.",
        ]);
        $ordem = 0;
        foreach (array_unique($servidores) as $userId) {
            Assinatura::create([
                'solicitacao_id' => $solicitacao->id, 'documento_id' => $documento->id, 'signatario_id' => $userId,
                'ordem' => ++$ordem, 'status' => 'pendente', 'email_signatario' => User::find($userId)?->email,
            ]);
        }
        foreach ($externos as $externo) {
            app(ConviteAssinaturaService::class)->criar($solicitacao, $documento, $externo, ++$ordem);
        }
        app(FluxoAssinaturaService::class)->avisarIniciais($solicitacao->setRelation('documento', $documento));

        return $documento->id;
    }
}
