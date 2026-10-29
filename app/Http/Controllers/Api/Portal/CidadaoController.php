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
    /** Nomes anteriores e endereço — opcionais no cadastro e em Meus dados. */
    private const REGRAS_COMPLEMENTO = [
        'nomes_anteriores'     => ['nullable', 'array', 'max:10'],
        'nomes_anteriores.*'   => ['nullable', 'string', 'max:200'],
        'endereco'             => ['nullable', 'array'],
        'endereco.cep'         => ['nullable', 'string', 'max:10'],
        'endereco.logradouro'  => ['nullable', 'string', 'max:200'],
        'endereco.numero'      => ['nullable', 'string', 'max:20'],
        'endereco.complemento' => ['nullable', 'string', 'max:100'],
        'endereco.bairro'      => ['nullable', 'string', 'max:100'],
        'endereco.municipio'   => ['nullable', 'string', 'max:100'],
        'endereco.uf'          => ['nullable', 'string', 'size:2'],
    ];

    public function __construct(private readonly CidadaoPortalService $cidadaos)
    {
    }

    /** Tem cadastro? — para a porta única do portal decidir entre senha e cadastro. */
    public function identificar(Request $request): JsonResponse
    {
        $dados = $request->validate(['documento' => ['required', 'string', 'max:18']]);
        $cidadao = $this->cidadaos->porDocumento($dados['documento']);
        // Usuário do GPE Docs sem cadastro de cidadão também "tem conta": entra com a senha de lá.
        $existe = $this->cidadaos->temAcesso($dados['documento']);

        return response()->json([
            'existe'           => $existe,
            'email_confirmado' => $cidadao ? $cidadao->email_verificado_em !== null : $existe,
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
        ] + self::REGRAS_COMPLEMENTO);

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
            'documento' => ['required_without:email', 'nullable', 'string', 'max:18'],
            'email'     => ['required_without:documento', 'nullable', 'string', 'max:150'],
            'senha'     => ['required', 'string', 'max:200'],
        ]);
        $cidadao = $this->cidadaos->autenticar((string) ($dados['documento'] ?? $dados['email']), $dados['senha']);

        if (! $cidadao) {
            return response()->json(['erro' => 'Dados de acesso ou senha inválidos.'], 401);
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

    /** Meus dados. E-mail novo fica pendente até o código mandado a ele. */
    public function atualizar(Request $request, int $id): JsonResponse
    {
        $cidadao = Cidadao::where('id', $id)->where('ativo', true)->first();
        if (! $cidadao) {
            return response()->json(['erro' => 'Cidadão não encontrado.'], 404);
        }
        $dados = $request->validate([
            'nome'     => ['sometimes', 'required', 'string', 'min:3', 'max:200'],
            'telefone' => ['sometimes', 'nullable', 'string', 'max:30'],
            'email'    => ['sometimes', 'required', 'email', 'max:150'],
        ] + self::REGRAS_COMPLEMENTO);
        $documento = $request->input('documento', $request->input('cpf'));
        if ($documento !== null && CidadaoPortalService::digitos((string) $documento) !== CidadaoPortalService::digitos($cidadao->cpf)) {
            return response()->json(['erro' => 'O CPF/CNPJ do cadastro não pode ser alterado.'], 422);
        }

        try {
            ['cidadao' => $cidadao, 'codigo' => $codigo] = $this->cidadaos->atualizar($cidadao, $dados);
        } catch (CidadaoPortalException $e) {
            return response()->json(['erro' => $e->getMessage()], $e->status);
        }

        $resposta = ['cidadao' => CidadaoPortalService::paraApi($cidadao->refresh())];
        if ($codigo !== null) {
            $resposta['email_pendente'] = $this->comCodigoDev([
                'email_mascarado' => CidadaoPortalService::mascararEmail($cidadao->email_novo),
                'minutos'         => CidadaoPortalService::MINUTOS_CODIGO,
            ], $codigo);
        }

        return response()->json($resposta);
    }

    public function confirmarEmailNovo(Request $request, int $id): JsonResponse
    {
        $dados = $request->validate(['codigo' => ['required', 'string', 'size:6']]);
        $cidadao = Cidadao::where('id', $id)->where('ativo', true)->first();

        if (! $cidadao || ! $this->cidadaos->confirmarEmailNovo($cidadao, $dados['codigo'])) {
            return response()->json(['erro' => 'Código incorreto ou vencido. Altere o e-mail de novo para receber outro.'], 422);
        }

        return response()->json(['cidadao' => CidadaoPortalService::paraApi($cidadao->refresh())]);
    }

    /**
     * Entrada pelo gov.br ou Google: o portal já conferiu a identidade com o
     * provedor (OIDC) e manda o CPF (gov.br) ou o e-mail confirmado (Google).
     */
    public function identificarExterno(Request $request): JsonResponse
    {
        $dados = $request->validate([
            'provedor'  => ['required', 'in:govbr,google'],
            'documento' => ['required_without:email', 'nullable', 'string', 'max:18'],
            'email'     => ['required_without:documento', 'nullable', 'email', 'max:150'],
        ]);
        $cidadao = $this->cidadaos->porIdentidadeComprovada((string) ($dados['documento'] ?? $dados['email']));

        if (! $cidadao) {
            return response()->json(['erro' => 'Não há cadastro para esta conta. Faça o cadastro no portal.'], 404);
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
