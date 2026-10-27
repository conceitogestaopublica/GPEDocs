<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Portal;

use App\Models\Portal\Cidadao;
use App\Services\Portal\CidadaoPortalException;
use App\Services\Portal\CidadaoPortalService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Cadastro único do cidadão (`portal_cidadaos`) para o Portal do Cidadão:
 * identificar, cadastrar + confirmar e-mail, autenticar e redefinir a senha.
 * A senha mora só aqui — o portal nunca a guarda.
 *
 * Limite de tentativas: fica no portal (por documento + município), que é a
 * única porta para esta API (SomenteSistemaPortalCidadao).
 */
class CidadaoController extends PortalApiController
{
    public function __construct(private readonly CidadaoPortalService $cidadaos)
    {
    }

    /** Tem cadastro? — para a porta única do portal decidir entre senha e cadastro. */
    public function identificar(Request $request): JsonResponse
    {
        $dados = $request->validate(['documento' => ['required', 'string', 'max:18']]);
        $cidadao = $this->cidadaos->porDocumento($dados['documento']);

        return response()->json([
            'existe'           => $cidadao !== null && $cidadao->ativo,
            'email_confirmado' => $cidadao?->email_verificado_em !== null,
        ]);
    }

    public function cadastrar(Request $request): JsonResponse
    {
        $dados = $request->validate([
            'documento' => ['required', 'string', 'max:18'],
            'nome'      => ['required', 'string', 'min:3', 'max:200'],
            'email'     => ['required', 'email', 'max:150'],
            'telefone'  => ['nullable', 'string', 'max:30'],
            'senha'     => ['required', 'string', 'min:8', 'max:200'],
        ]);

        try {
            ['cidadao' => $cidadao, 'codigo' => $codigo] = $this->cidadaos->cadastrar($dados);
        } catch (CidadaoPortalException $e) {
            return response()->json(['erro' => $e->getMessage()], $e->status);
        }

        return response()->json($this->comCodigoDev([
            'cidadao'         => CidadaoPortalService::paraApi($cidadao),
            'email_mascarado' => CidadaoPortalService::mascararEmail($cidadao->email),
            'minutos'         => CidadaoPortalService::MINUTOS_CODIGO,
        ], $codigo), 201);
    }

    public function confirmarEmail(Request $request): JsonResponse
    {
        $dados = $request->validate([
            'documento' => ['required', 'string', 'max:18'],
            'codigo'    => ['required', 'string', 'size:6'],
        ]);
        $cidadao = $this->cidadaos->porDocumento($dados['documento']);

        if (! $cidadao || ! $this->cidadaos->confirmarEmail($cidadao, $dados['codigo'])) {
            return response()->json(['erro' => 'Código incorreto ou vencido. Peça um novo código.'], 422);
        }

        return response()->json(['cidadao' => CidadaoPortalService::paraApi($cidadao->refresh())]);
    }

    /** Novo código de confirmação do e-mail (cadastro ainda não confirmado). */
    public function reenviarCodigo(Request $request): JsonResponse
    {
        $dados = $request->validate(['documento' => ['required', 'string', 'max:18']]);
        $cidadao = $this->cidadaos->porDocumento($dados['documento']);

        if (! $cidadao || $cidadao->email_verificado_em) {
            return response()->json(['erro' => 'Não há confirmação pendente para este CPF/CNPJ.'], 422);
        }

        return response()->json($this->comCodigoDev([
            'email_mascarado' => CidadaoPortalService::mascararEmail($cidadao->email),
            'minutos'         => CidadaoPortalService::MINUTOS_CODIGO,
        ], $this->cidadaos->enviarCodigo($cidadao)));
    }

    /**
     * Documento + senha. 401 genérico para qualquer falha; 403 quando a senha
     * confere mas o e-mail ainda não foi confirmado (o portal oferece o código).
     */
    public function autenticar(Request $request): JsonResponse
    {
        $dados = $request->validate([
            'documento' => ['required', 'string', 'max:18'],
            'senha'     => ['required', 'string', 'max:200'],
        ]);
        $cidadao = $this->cidadaos->autenticar($dados['documento'], $dados['senha']);

        if (! $cidadao) {
            return response()->json(['erro' => 'CPF/CNPJ ou senha inválidos.'], 401);
        }
        if (! $cidadao->email_verificado_em) {
            return response()->json([
                'erro'            => 'Confirme o seu e-mail para entrar.',
                'email_mascarado' => CidadaoPortalService::mascararEmail($cidadao->email),
            ], 403);
        }

        return response()->json(['cidadao' => CidadaoPortalService::paraApi($cidadao)]);
    }

    /**
     * Esqueci a senha: código para o e-mail CADASTRADO. Responde igual exista
     * ou não o documento (não revela quem tem cadastro).
     */
    public function codigoSenha(Request $request): JsonResponse
    {
        $dados = $request->validate(['documento' => ['required', 'string', 'max:18']]);
        $cidadao = $this->cidadaos->porDocumento($dados['documento']);

        $codigo = null;
        if ($cidadao && $cidadao->ativo && $cidadao->email) {
            $codigo = $this->cidadaos->enviarCodigo($cidadao);
        }

        return response()->json($this->comCodigoDev([
            'email_mascarado' => $cidadao ? CidadaoPortalService::mascararEmail($cidadao->email) : null,
            'minutos'         => CidadaoPortalService::MINUTOS_CODIGO,
        ], $codigo));
    }

    public function redefinirSenha(Request $request): JsonResponse
    {
        $dados = $request->validate([
            'documento' => ['required', 'string', 'max:18'],
            'codigo'    => ['required', 'string', 'size:6'],
            'senha'     => ['required', 'string', 'min:8', 'max:200'],
        ]);
        $cidadao = $this->cidadaos->porDocumento($dados['documento']);

        if (! $cidadao || ! $this->cidadaos->redefinirSenha($cidadao, $dados['codigo'], $dados['senha'])) {
            return response()->json(['erro' => 'Código incorreto ou vencido. Peça um novo código.'], 422);
        }

        return response()->json(['cidadao' => CidadaoPortalService::paraApi($cidadao->refresh())]);
    }

    public function show(int $id): JsonResponse
    {
        $cidadao = Cidadao::where('id', $id)->where('ativo', true)->first();
        if (! $cidadao) {
            return response()->json(['erro' => 'Cidadão não encontrado.'], 404);
        }

        return response()->json(['cidadao' => CidadaoPortalService::paraApi($cidadao)]);
    }

    /** Desenvolvimento: o e-mail local vai só para o log — devolve o código. */
    private function comCodigoDev(array $resposta, ?string $codigo): array
    {
        if ($codigo !== null && app()->environment('local') && config('portal.api_eco_codigo')) {
            $resposta['codigo_dev'] = $codigo;
        }

        return $resposta;
    }
}
