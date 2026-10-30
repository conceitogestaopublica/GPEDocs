<?php

use App\Http\Controllers\Admin\RoleController;
use App\Http\Controllers\Admin\TipoDocumentalController;
use App\Http\Controllers\Admin\UsuarioController;
use App\Http\Controllers\Admin\TipoProcessoController;
use App\Http\Controllers\AssinaturaController;
use App\Http\Controllers\CertificadoController;
use App\Http\Controllers\SelecionarUgController;
use App\Http\Controllers\ProcessoController;
use App\Http\Controllers\ProcessoDashboardController;
use App\Http\Controllers\TramitacaoController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\VerificacaoController;
use App\Http\Controllers\BuscaController;
use App\Http\Controllers\CapturaController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DocumentoController;
use App\Http\Controllers\FluxoController;
use App\Http\Controllers\CircularController;
use App\Http\Controllers\MemorandoController;
use App\Http\Controllers\OficioController;
use App\Http\Controllers\ModulosController;
use App\Http\Controllers\NotificacaoController;
use App\Http\Controllers\PastaController;
use Illuminate\Support\Facades\Route;

// Captcha dos formulários públicos (sem "png" no endereço: o nginx serve imagens direto do disco)
Route::get('captcha/{contexto}', [\App\Http\Controllers\CaptchaController::class, 'imagem'])
    ->where('contexto', '[a-z-]+')->middleware('throttle:60,1')->name('captcha');

// Descadastro dos avisos por e-mail (link assinado; o POST atende também o botão de um clique)
Route::get('email/descadastrar', [\App\Http\Controllers\DescadastroEmailController::class, 'mostrar'])
    ->middleware(['signed', 'throttle:30,1'])->name('email.descadastrar');
Route::post('email/descadastrar', [\App\Http\Controllers\DescadastroEmailController::class, 'confirmar'])
    ->middleware(['signed', 'throttle:30,1'])->withoutMiddleware([\Illuminate\Foundation\Http\Middleware\ValidateCsrfToken::class]);

// Rastreio de oficio (publico, sem auth)
Route::get('oficios/rastrear/{token}', [OficioController::class, 'rastrear'])->name('oficios.rastrear');
// Anexo de processo por link seguro com validade (TR 4.20.2) — público, só com a assinatura do link
Route::get('anexos/compartilhado/{anexo}', [\App\Http\Controllers\AnexoProcessoController::class, 'compartilhado'])
    ->whereNumber('anexo')->middleware(['signed', 'throttle:30,1'])->name('anexos.compartilhado');
Route::get('oficios/rastrear/{token}/pdf', [OficioController::class, 'pdfPublico'])->middleware('throttle:30,1')->name('oficios.rastrear.pdf');
Route::get('oficios/rastrear/{token}/aberto', [OficioController::class, 'pixelAbertura'])->middleware('throttle:60,1')->name('oficios.rastrear.aberto');
// Identidade visual na tela de acesso (TR 4.22.1): brasão e fundo, antes do login
Route::get('identidade/brasao', [\App\Http\Controllers\Configuracao\IdentidadeVisualController::class, 'brasao'])->middleware('throttle:120,1')->name('identidade.brasao');
Route::get('identidade/fundo', [\App\Http\Controllers\Configuracao\IdentidadeVisualController::class, 'fundo'])->middleware('throttle:120,1')->name('identidade.fundo');
// Rastreio de e-mail externo (TR 4.11.12–14): pixel de abertura, clique e retorno do provedor
Route::get('e/{token}/a', [\App\Http\Controllers\RastreioEmailController::class, 'aberto'])->where('token', '[A-Za-z0-9]{40}')->middleware('throttle:120,1')->name('email.aberto');
Route::get('e/{token}/l', [\App\Http\Controllers\RastreioEmailController::class, 'clique'])->where('token', '[A-Za-z0-9]{40}')->middleware('throttle:120,1')->name('email.clique');
Route::post('webhooks/email/{segredo}', [\App\Http\Controllers\RastreioEmailController::class, 'webhook'])->middleware('throttle:600,1')->name('email.webhook');
// Resposta a e-mail do GED vira despacho no documento (TR 4.7.23, 4.8.8–9, 4.47.3)
Route::post('webhooks/email-entrada/{segredo}', [\App\Http\Controllers\RastreioEmailController::class, 'entrada'])->middleware('throttle:300,1')->name('email.entrada');
// Organograma público para incorporar no site da prefeitura (TR 4.12.8) — só com a chave da UG
Route::get('organograma/publico/{token}', [\App\Http\Controllers\OrganogramaController::class, 'publico'])->middleware('throttle:60,1')->name('organograma.publico');
// PDF do documento para o contato externo mencionado no texto (TR 4.16.17) — link assinado do e-mail
Route::get('documentos-redigidos/{tipo}/{id}', [\App\Http\Controllers\EditorController::class, 'publico'])->whereIn('tipo', ['memorando', 'circular', 'oficio'])->whereNumber('id')->middleware(['signed', 'throttle:30,1'])->name('documentos-redigidos.publico');

/* ─── SSO consume — recebe token emitido pelo landlord (fluxo multi-tenant) ─── */
// GET porque o landlord redireciona via 302 com ?t=<token>. O token é de uso
// único, validado contra hash sha256 — não é credencial em URL clássica.
Route::get('/sso/landlord', [\App\Http\Controllers\Tenant\SSOConsumeController::class, 'consume'])->name('sso.landlord');

/* ─── Painel landlord: vive só no gpe2 ─── */
// A rota existe só para o grupo 'web' rodar: o ResolveTenant intercepta /landlord/*
// e redireciona para LANDLORD_URL (ou 404 se não configurada) antes de chegar aqui.
Route::any('landlord/{path?}', fn () => abort(404))->where('path', '.*')->name('landlord.redirect');

// Documentacao da API de integracao (publica, leitura)
Route::get('docs/integracao-externa.md', function () {
    $path = base_path('docs/integracao-externa.md');
    if (! is_file($path)) abort(404);
    return response()->download($path, 'gpedocs-integracao-externa.md', [
        'Content-Type' => 'text/markdown; charset=utf-8',
    ]);
})->name('docs.integracao');

Route::get('docs/integracao-externa', function () {
    $path = base_path('docs/integracao-externa.md');
    if (! is_file($path)) abort(404);
    return response(file_get_contents($path))
        ->header('Content-Type', 'text/markdown; charset=utf-8');
})->name('docs.integracao.view');

// API de integracao com sistemas externos (GPE, RH, etc) — auth por Bearer token.
// Stateless: pula middlewares de sessao/auth/inertia/UG (so usa o middleware
// proprio sistema.api que valida o token de API).
Route::prefix('api/integracoes')
    ->withoutMiddleware([
        \App\Http\Middleware\HandleInertiaRequests::class,
        \App\Http\Middleware\EnsureUgSelected::class,
        \Illuminate\Auth\Middleware\Authenticate::class,
        \Illuminate\Session\Middleware\StartSession::class,
        \Illuminate\View\Middleware\ShareErrorsFromSession::class,
        \Illuminate\Foundation\Http\Middleware\VerifyCsrfToken::class,
        \Illuminate\Foundation\Http\Middleware\PreventRequestForgery::class,
        \Illuminate\Foundation\Http\Middleware\ValidateCsrfToken::class,
    ])
    ->middleware(['sistema.api'])
    ->group(function () {
        Route::post('documentos', [\App\Http\Controllers\Api\IntegracaoDocumentoController::class, 'store'])
            ->name('api.integracoes.documentos.store');
        // Eventos do sistema tributário (guia paga, alvará emitido…) — qualquer fornecedor
        Route::post('tributario/eventos', [\App\Http\Controllers\Api\IntegracaoTributariaController::class, 'eventos'])
            ->middleware('throttle:120,1')->name('api.integracoes.tributario.eventos');
        // Rotas mais especificas vem ANTES da generica `documentos/{numero}` pra
        // evitar que a regex `.+` engula segmentos (ex.: `1-2026-2770-r2/pdf-assinado`).
        // Tambem restringimos `numero` a `[^/]+` (sem barras) — nosso formato e
        // gestoraId-exercicio-numero[-rN], sempre com hifens.
        Route::post('documentos/{numero}/versao', [\App\Http\Controllers\Api\IntegracaoDocumentoController::class, 'novaVersao'])
            ->where('numero', '[^/]+')
            ->name('api.integracoes.documentos.versao');
        Route::post('documentos/{numero}/reenviar-webhook', [\App\Http\Controllers\Api\IntegracaoDocumentoController::class, 'reenviarWebhook'])
            ->where('numero', '[^/]+')
            ->name('api.integracoes.documentos.reenviar-webhook');
        Route::get('documentos/{numero}/pdf-assinado', [\App\Http\Controllers\Api\IntegracaoDocumentoController::class, 'downloadPdfAssinado'])
            ->where('numero', '[^/]+')
            ->name('api.integracoes.documentos.pdf-assinado');
        Route::post('documentos/{numero}/cancelar', [\App\Http\Controllers\Api\IntegracaoDocumentoController::class, 'cancelar'])
            ->where('numero', '[^/]+')
            ->name('api.integracoes.documentos.cancelar');
        Route::post('pacotes/{codigo}/notificar', [\App\Http\Controllers\Api\IntegracaoDocumentoController::class, 'notificarPacote'])
            ->where('codigo', '[^/]+')
            ->name('api.integracoes.pacotes.notificar');
        Route::get('documentos/{numero}', [\App\Http\Controllers\Api\IntegracaoDocumentoController::class, 'show'])
            ->where('numero', '[^/]+')
            ->name('api.integracoes.documentos.show');

        // Arquivamento SEM assinatura (imagens, anexos) — ex.: foto do servidor no RH.
        Route::post('arquivos', [\App\Http\Controllers\Api\IntegracaoDocumentoController::class, 'arquivar'])
            ->name('api.integracoes.arquivos.store');
        Route::get('arquivos/{id}/conteudo', [\App\Http\Controllers\Api\IntegracaoDocumentoController::class, 'conteudo'])
            ->where('id', '[0-9]+')
            ->name('api.integracoes.arquivos.conteudo');
    });

// API do Portal do Cidadao (Next.js): Carta de Servicos, cadastro unico do
// cidadao (portal_cidadaos) e solicitacoes. Mesmo token de sistema integrado,
// mas so o sistema do portal entra (SomenteSistemaPortalCidadao). A UG vem
// do parametro `ug` (= ugs.portal_slug). Ver docs/api-portal-cidadao.md.
Route::prefix('api/portal')
    ->withoutMiddleware([
        \App\Http\Middleware\HandleInertiaRequests::class,
        \App\Http\Middleware\EnsureUgSelected::class,
        \Illuminate\Auth\Middleware\Authenticate::class,
        \Illuminate\Session\Middleware\StartSession::class,
        \Illuminate\View\Middleware\ShareErrorsFromSession::class,
        \Illuminate\Foundation\Http\Middleware\VerifyCsrfToken::class,
        \Illuminate\Foundation\Http\Middleware\PreventRequestForgery::class,
        \Illuminate\Foundation\Http\Middleware\ValidateCsrfToken::class,
    ])
    ->middleware(['sistema.api', \App\Http\Middleware\SomenteSistemaPortalCidadao::class])
    ->name('api.portal.')
    ->group(function () {
        Route::get('catalogo', [\App\Http\Controllers\Api\Portal\CatalogoController::class, 'show'])->name('catalogo');
        // Órgãos responsáveis e contador de acessos da Carta de Serviços (TR 4.30.4, 4.30.27)
        Route::get('orgaos', [\App\Http\Controllers\Api\Portal\CatalogoController::class, 'orgaos'])->name('orgaos');
        // Central de Atendimento (TR 4.23): verificação por código (registrada), textos do portal, ofício pela Central
        Route::get('verificar/{codigo}', [\App\Http\Controllers\Api\Portal\CentralController::class, 'verificar'])->where('codigo', '[A-Za-z0-9-]{4,120}')->middleware('throttle:60,1')->name('verificar');
        Route::get('textos', [\App\Http\Controllers\Api\Portal\CentralController::class, 'textos'])->name('textos');
        Route::get('oficios/{token}', [\App\Http\Controllers\Api\Portal\CentralController::class, 'oficio'])->name('oficios.show');
        Route::post('oficios/{token}/resposta', [\App\Http\Controllers\Api\Portal\CentralController::class, 'responderOficio'])->middleware('throttle:20,1')->name('oficios.resposta');
        Route::post('servicos/{slug}/visualizacao', [\App\Http\Controllers\Api\Portal\CatalogoController::class, 'visualizacao'])->name('servicos.visualizacao');
        // Imagem de ajuda dos campos do formulário do assunto (o portal repassa ao navegador)
        Route::get('ajuda-imagem/{id}', [\App\Http\Controllers\Admin\TipoProcessoController::class, 'imagemAjuda'])->whereUuid('id')->name('ajuda-imagem');

        Route::get('contatos/campos', [\App\Http\Controllers\Api\Portal\CidadaoController::class, 'camposComplementares'])->name('contatos.campos');
        Route::post('cidadaos/identificar',[\App\Http\Controllers\Api\Portal\CidadaoController::class, 'identificar'])->name('cidadaos.identificar');
        Route::post('cidadaos', [\App\Http\Controllers\Api\Portal\CidadaoController::class, 'cadastrar'])->name('cidadaos.cadastrar');
        Route::post('cidadaos/confirmar-email', [\App\Http\Controllers\Api\Portal\CidadaoController::class, 'confirmarEmail'])->name('cidadaos.confirmar-email');
        Route::post('cidadaos/reenviar-codigo', [\App\Http\Controllers\Api\Portal\CidadaoController::class, 'reenviarCodigo'])->name('cidadaos.reenviar-codigo');
        Route::post('cidadaos/autenticar', [\App\Http\Controllers\Api\Portal\CidadaoController::class, 'autenticar'])->name('cidadaos.autenticar');
        Route::post('cidadaos/senha/codigo', [\App\Http\Controllers\Api\Portal\CidadaoController::class, 'codigoSenha'])->name('cidadaos.senha.codigo');
        Route::post('cidadaos/senha/redefinir', [\App\Http\Controllers\Api\Portal\CidadaoController::class, 'redefinirSenha'])->name('cidadaos.senha.redefinir');
        Route::post('cidadaos/identificar-externo', [\App\Http\Controllers\Api\Portal\CidadaoController::class, 'identificarExterno'])->name('cidadaos.identificar-externo');
        Route::post('cidadaos/certificado/desafio', [\App\Http\Controllers\Api\Portal\CertificadoCidadaoController::class, 'desafio'])->name('cidadaos.certificado.desafio');
        Route::post('cidadaos/certificado/autenticar', [\App\Http\Controllers\Api\Portal\CertificadoCidadaoController::class, 'autenticar'])->name('cidadaos.certificado.autenticar');
        Route::get('cidadaos/{id}', [\App\Http\Controllers\Api\Portal\CidadaoController::class, 'show'])->whereNumber('id')->name('cidadaos.show');
        Route::put('cidadaos/{id}', [\App\Http\Controllers\Api\Portal\CidadaoController::class, 'atualizar'])->whereNumber('id')->name('cidadaos.atualizar');
        Route::post('cidadaos/{id}/confirmar-email-novo', [\App\Http\Controllers\Api\Portal\CidadaoController::class, 'confirmarEmailNovo'])->whereNumber('id')->name('cidadaos.confirmar-email-novo');
        Route::get('cidadaos/{id}/caixa', [\App\Http\Controllers\Api\Portal\CaixaController::class, 'index'])->whereNumber('id')->name('cidadaos.caixa');

        Route::get('solicitacoes', [\App\Http\Controllers\Api\Portal\SolicitacaoController::class, 'index'])->name('solicitacoes.index');
        Route::post('solicitacoes', [\App\Http\Controllers\Api\Portal\SolicitacaoController::class, 'store'])->name('solicitacoes.store');
        Route::get('solicitacoes/{codigo}', [\App\Http\Controllers\Api\Portal\SolicitacaoController::class, 'show'])->where('codigo', '[^/]+')->name('solicitacoes.show');
        Route::post('solicitacoes/{codigo}/avaliacao', [\App\Http\Controllers\Api\Portal\SolicitacaoController::class, 'avaliar'])->where('codigo', '[^/]+')->name('solicitacoes.avaliacao');
        Route::post('solicitacoes/{codigo}/cancelar',[\App\Http\Controllers\Api\Portal\SolicitacaoController::class, 'cancelar'])->where('codigo', '[^/]+')->name('solicitacoes.cancelar');
        // Interação depois de abrir, comprovante e processos do cidadão (TR 4.32.1, 4.32.11, 4.32.23)
        Route::post('solicitacoes/{codigo}/mensagens', [\App\Http\Controllers\Api\Portal\SolicitacaoController::class, 'mensagem'])->where('codigo', '[^/]+')->middleware('throttle:30,1')->name('solicitacoes.mensagens');
        Route::get('solicitacoes/{codigo}/comprovante', [\App\Http\Controllers\Api\Portal\SolicitacaoController::class, 'comprovante'])->where('codigo', '[^/]+')->name('solicitacoes.comprovante');
        Route::get('processos', [\App\Http\Controllers\Api\Portal\SolicitacaoController::class, 'processos'])->name('processos.index');
        // Consulta prévia de viabilidade (TR 4.50)
        Route::get('urbanismo/dados', [\App\Http\Controllers\Api\Portal\UrbanismoController::class, 'dados'])->name('urbanismo.dados');
        Route::get('urbanismo/lotes', [\App\Http\Controllers\Api\Portal\UrbanismoController::class, 'lotes'])->name('urbanismo.lotes');
        Route::get('urbanismo/lote', [\App\Http\Controllers\Api\Portal\UrbanismoController::class, 'lote'])->name('urbanismo.lote');
        Route::get('urbanismo/busca', [\App\Http\Controllers\Api\Portal\UrbanismoController::class, 'busca'])->name('urbanismo.busca');
        // Diário Oficial: área pública (TR 4.43.32–4.43.46)
        Route::get('diario/calendario', [\App\Http\Controllers\Api\Portal\DiarioController::class, 'calendario'])->name('diario.calendario');
        Route::get('diario/dia', [\App\Http\Controllers\Api\Portal\DiarioController::class, 'dia'])->name('diario.dia');
        Route::get('diario/busca', [\App\Http\Controllers\Api\Portal\DiarioController::class, 'busca'])->middleware('throttle:60,1')->name('diario.busca');
        Route::get('diario/edicoes/{id}', [\App\Http\Controllers\Api\Portal\DiarioController::class, 'edicao'])->whereNumber('id')->name('diario.edicao');
        Route::get('diario/edicoes/{id}/pdf', [\App\Http\Controllers\Api\Portal\DiarioController::class, 'pdf'])->whereNumber('id')->name('diario.edicao.pdf');
        Route::get('diario/atos/{materia}/pdf', [\App\Http\Controllers\Api\Portal\DiarioController::class, 'atoPdf'])->whereNumber('materia')->name('diario.ato.pdf');
        // Atos oficiais publicados: consulta pública (TR 4.42.6)
        Route::get('atos', [\App\Http\Controllers\Api\Portal\AtoController::class, 'index'])->name('atos.index');
        Route::get('atos/{id}/pdf', [\App\Http\Controllers\Api\Portal\AtoController::class, 'pdf'])->whereNumber('id')->name('atos.pdf');
        // Processo seletivo simplificado (TR 4.41)
        Route::get('pss', [\App\Http\Controllers\Api\Portal\PssController::class, 'index'])->name('pss.index');
        Route::get('pss/inscricoes', [\App\Http\Controllers\Api\Portal\PssController::class, 'minhas'])->name('pss.minhas');
        Route::get('pss/inscricoes/{numero}/ficha', [\App\Http\Controllers\Api\Portal\PssController::class, 'ficha'])->where('numero', '[A-Za-z0-9-]+')->name('pss.ficha');
        Route::post('pss/{id}/inscricoes', [\App\Http\Controllers\Api\Portal\PssController::class, 'inscrever'])->whereNumber('id')->middleware('throttle:20,1')->name('pss.inscrever');
        // Ouvidoria anônima/sigilosa sem login (código + chave) e estatísticas do e-SIC (TR 4.33.27, 4.34)
        Route::get('acompanhar/{codigo}', [\App\Http\Controllers\Api\Portal\SolicitacaoController::class, 'acompanhar'])->where('codigo', '[^/]+')->middleware('throttle:30,1')->name('acompanhar');
        Route::get('esic/estatisticas', [\App\Http\Controllers\Api\Portal\EsicController::class, 'estatisticas'])->name('esic.estatisticas');
        Route::get('processos/{numero}/anexos/{anexo}', [\App\Http\Controllers\Api\Portal\SolicitacaoController::class, 'anexoProcesso'])->where('numero', '[^/]+')->whereNumber('anexo')->name('processos.anexo');
        Route::get('processos/{numero}', [\App\Http\Controllers\Api\Portal\SolicitacaoController::class, 'processo'])->where('numero', '[^/]+')->name('processos.show');
    });

// Portal Cidadao — Carta de Servicos (publico, sem auth)
// Roteado por subdominio: ex. pmparaguacu.gpedocs.com.br (prod) ou pmparaguacu.lvh.me:8000 (dev)
// O parametro {ug} vem do subdominio e bate com `portal_slug` da UG.
Route::domain(config('portal.domain'))->name('portal.')->group(function () {
    // Catalogo publico
    Route::get('/',                  [\App\Http\Controllers\PortalController::class, 'home'])->name('home');
    Route::get('/buscar',            [\App\Http\Controllers\PortalController::class, 'buscar'])->name('buscar');
    Route::get('/categoria/{slug}',  [\App\Http\Controllers\PortalController::class, 'categoria'])->name('categoria');
    Route::get('/servico/{slug}',    [\App\Http\Controllers\PortalController::class, 'servico'])->name('servico');
    Route::get('/_brasao/{id}',      [\App\Http\Controllers\PortalController::class, 'brasao'])->name('brasao');
    Route::get('/_banner/{id}',      [\App\Http\Controllers\PortalController::class, 'banner'])->name('banner');
    Route::get('/_banner-img/{id}',  [\App\Http\Controllers\PortalController::class, 'bannerImagem'])->name('banner-img');

    // Auth do cidadao
    Route::get('/cadastrar',         [\App\Http\Controllers\Portal\CidadaoAuthController::class, 'showRegister'])->name('cadastrar');
    Route::post('/cadastrar',        [\App\Http\Controllers\Portal\CidadaoAuthController::class, 'register']);
    Route::get('/entrar',            [\App\Http\Controllers\Portal\CidadaoAuthController::class, 'showLogin'])->name('entrar');
    Route::post('/entrar',           [\App\Http\Controllers\Portal\CidadaoAuthController::class, 'login']);
    Route::post('/sair',             [\App\Http\Controllers\Portal\CidadaoAuthController::class, 'logout'])->name('sair');

    // Solicitacao — abre publicamente; o controller decide se exige login
    // (servicos com permite_anonimo aceitam denuncia anonima sem auth)
    Route::get('/servico/{slug}/solicitar',  [\App\Http\Controllers\Portal\SolicitacaoController::class, 'create'])->name('solicitar');
    Route::post('/servico/{slug}/solicitar', [\App\Http\Controllers\Portal\SolicitacaoController::class, 'store']);

    // Acompanhamento — apenas cidadao logado
    Route::middleware('auth.cidadao')->group(function () {
        Route::get('/minhas-solicitacoes',       [\App\Http\Controllers\Portal\SolicitacaoController::class, 'minhasSolicitacoes'])->name('minhas-solicitacoes');
        Route::get('/minhas-solicitacoes/{id}',  [\App\Http\Controllers\Portal\SolicitacaoController::class, 'show'])->name('solicitacao-show');
        Route::post('/minhas-solicitacoes/{id}/cancelar', [\App\Http\Controllers\Portal\SolicitacaoController::class, 'cancelar'])->name('solicitacao-cancelar');
        Route::get('/anexo/{anexoId}',           [\App\Http\Controllers\Portal\SolicitacaoController::class, 'baixarAnexo'])->name('anexo');
        Route::get('/minhas-solicitacoes/{id}/decisao', [\App\Http\Controllers\Portal\SolicitacaoController::class, 'baixarDecisao'])->name('decisao');
    });
});

// Pacote: vários documentos para o mesmo externo num link só. Login/cadastro reaproveitam os
// métodos da assinatura individual (o default `pacote` muda o convite e a página de volta).
Route::prefix('assinar/pacote/{token}')->where(['token' => '[A-Za-z0-9]{20,64}'])->name('assinatura-pacote.')
    ->controller(\App\Http\Controllers\AssinaturaExternaController::class)->group(function () {
        Route::get('/', 'showPacote')->name('show');
        Route::post('assinar-todos', 'assinarTodos')->middleware('throttle:10,1')->defaults('pacote', true)->name('assinar-todos');
        Route::post('entrar', 'entrar')->middleware('throttle:login')->defaults('pacote', true)->name('entrar');
        Route::post('cadastrar', 'cadastrar')->middleware('throttle:10,1')->defaults('pacote', true)->name('cadastrar');
        Route::post('confirmar-email', 'confirmarEmail')->middleware('throttle:10,1')->defaults('pacote', true)->name('confirmar-email');
        Route::post('reenviar-codigo', 'reenviarCodigo')->middleware('throttle:3,1')->defaults('pacote', true)->name('reenviar-codigo');
        Route::post('sair', 'sair')->defaults('pacote', true)->name('sair');
    });

// Assinatura por link de signatário externo (publico: autoriza pelo convite + cadastro com o CPF)
Route::prefix('assinar/{token}')->where(['token' => '[A-Za-z0-9]{20,64}'])->name('assinatura-externa.')
    ->controller(\App\Http\Controllers\AssinaturaExternaController::class)->group(function () {
        Route::get('/', 'show')->name('show');
        Route::get('pdf', 'pdf')->name('pdf');
        Route::get('baixar', 'baixar')->name('baixar');
        Route::post('entrar', 'entrar')->middleware('throttle:login')->name('entrar');
        Route::post('cadastrar', 'cadastrar')->middleware('throttle:10,1')->name('cadastrar');
        Route::post('confirmar-email', 'confirmarEmail')->middleware('throttle:10,1')->name('confirmar-email');
        Route::post('reenviar-codigo', 'reenviarCodigo')->middleware('throttle:3,1')->name('reenviar-codigo');
        Route::post('sair', 'sair')->name('sair');
        Route::post('assinar', 'assinar')->middleware('throttle:10,1')->name('assinar');
        Route::post('recusar', 'recusar')->middleware('throttle:10,1')->name('recusar');
    });

// Verificacao publica de documento (sem auth)
Route::get('verificar/codigo/{codigo}', [VerificacaoController::class, 'porCodigo'])
    ->where('codigo', '[A-Za-z0-9-]{6,20}')->name('verificar.codigo');
Route::get('verificar/{token}', [VerificacaoController::class, 'verificar'])->name('verificar');
// Mapa público da consulta prévia de viabilidade (TR 4.50)
// App offline da fiscalização (TR 4.52): arquivos do PWA ficam fora do login (o service worker se atualiza sozinho)
Route::controller(\App\Http\Controllers\AppFiscalizacaoController::class)->prefix('fiscal')->group(function () {
    Route::get('sw', 'sw')->name('fiscal.sw');
    Route::get('manifesto', 'manifesto')->name('fiscal.manifesto');
    Route::get('icone', 'icone')->name('fiscal.icone');
});

Route::controller(\App\Http\Controllers\UrbanismoController::class)->prefix('mapa-urbano')->middleware('throttle:120,1')->group(function () {
    Route::get('/', 'mapa')->name('mapa-urbano');
    Route::get('dados', 'dados')->name('mapa-urbano.dados');
    Route::get('lotes', 'lotes')->name('mapa-urbano.lotes');
    Route::get('lote', 'lote')->name('mapa-urbano.lote');
    Route::get('busca', 'busca')->name('mapa-urbano.busca');
});

// Consulta do processo sem login, pelo link (TR 4.48.27)
Route::controller(\App\Http\Controllers\ConsultaPublicaController::class)->middleware('throttle:60,1')->group(function () {
    Route::get('consulta/{token}', 'show')->name('consulta.show');
    Route::get('consulta/{token}/anexos/{anexo}', 'anexo')->whereNumber('anexo')->name('consulta.anexo');
    Route::get('consulta/{token}/pareceres/{parecer}', 'parecer')->whereNumber('parecer')->name('consulta.parecer');
});

// Convite do participante do processo para confirmar os dados, sem login (TR 4.48.31)
Route::get('participantes/convite/{token}', [\App\Http\Controllers\ParticipanteController::class, 'convite'])->middleware('throttle:60,1')->name('participantes.convite');
Route::post('participantes/convite/{token}', [\App\Http\Controllers\ParticipanteController::class, 'confirmar'])->middleware('throttle:20,1')->name('participantes.confirmar');

// Verificação pública da edição do Diário Oficial (TR 4.43.41)
Route::get('diario/verificar/{token}', [\App\Http\Controllers\DiarioController::class, 'verificar'])->middleware('throttle:60,1')->name('diario.verificar');

// QR do ato oficial (TR 4.42.8)
Route::get('atos/verificar/{token}', [\App\Http\Controllers\AtoController::class, 'verificar'])->middleware('throttle:60,1')->name('atos.verificar');

// QR dos PDFs de memorando, ofício e circular
Route::get('{tipo}/verificar/{token}', [\App\Http\Controllers\VerificacaoComunicacaoController::class, 'verificar'])
    ->whereIn('tipo', ['memorandos', 'oficios', 'circulares', 'pareceres'])
    ->name('comunicacoes.verificar');

// Validacao publica de assinatura ICP-Brasil em PDF (sem auth)
Route::get('validar-assinatura', [VerificacaoController::class, 'validarPdfPagina'])->name('validar-assinatura');
Route::post('validar-assinatura', [VerificacaoController::class, 'validarPdf'])->name('validar-assinatura.upload');

// Guest
Route::middleware('guest')->group(function () {
    Route::get('/login', [LoginController::class, 'index'])->name('login');
    // Limite de tentativas por IP: antes não havia nenhum contra força bruta.
    Route::post('/login', [LoginController::class, 'store'])->middleware('throttle:login');

    // Entrada com certificado digital ICP-Brasil (Web PKI)
    Route::get('/login/certificado/desafio', [\App\Http\Controllers\Auth\CertificadoLoginController::class, 'desafio'])->middleware('throttle:30,1');
    Route::post('/login/certificado', [\App\Http\Controllers\Auth\CertificadoLoginController::class, 'entrar'])->middleware('throttle:login');

    // Entrada pelo gov.br e pelo Google (OpenID Connect) — desligadas sem credenciais
    Route::get('/login/oidc/{provedor}', [\App\Http\Controllers\Auth\OidcLoginController::class, 'redirecionar'])
        ->whereIn('provedor', ['govbr', 'google'])->middleware('throttle:30,1')->name('login.oidc');
    Route::get('/login/oidc/{provedor}/retorno', [\App\Http\Controllers\Auth\OidcLoginController::class, 'retorno'])
        ->whereIn('provedor', ['govbr', 'google'])->middleware('throttle:login');

    // Verificação em duas etapas (código do aplicativo depois da senha)
    Route::get('/login/verificacao', [\App\Http\Controllers\Auth\DoisFatoresController::class, 'formulario'])->name('login.dois-fatores');
    Route::post('/login/verificacao', [\App\Http\Controllers\Auth\DoisFatoresController::class, 'confirmar'])->middleware('throttle:login');
    Route::post('/login/verificacao/cancelar', [\App\Http\Controllers\Auth\DoisFatoresController::class, 'cancelar'])->name('login.dois-fatores.cancelar');

    // Recuperação de senha
    Route::get('/forgot-password', [\App\Http\Controllers\Auth\SenhaController::class, 'solicitar'])->name('password.request');
    Route::post('/forgot-password', [\App\Http\Controllers\Auth\SenhaController::class, 'enviar'])->name('password.email')->middleware('throttle:senha');
    Route::get('/reset-password/{token}', [\App\Http\Controllers\Auth\SenhaController::class, 'formulario'])->name('password.reset');
    Route::post('/reset-password', [\App\Http\Controllers\Auth\SenhaController::class, 'redefinir'])->name('password.update')->middleware('throttle:senha');
});

// Selecionar UG (autenticado, mas isento do EnsureUgSelected)
Route::middleware('auth')->group(function () {
    Route::get('/selecionar-ug', [SelecionarUgController::class, 'index'])->name('selecionar-ug');
    Route::post('/selecionar-ug/{id}', [SelecionarUgController::class, 'selecionar'])->name('selecionar-ug.set');
    Route::post('/trocar-ug', [SelecionarUgController::class, 'trocar'])->name('trocar-ug');
    Route::get('/sem-ug', [SelecionarUgController::class, 'semUg'])->name('sem-ug');
});

// Auth
Route::middleware('auth')->group(function () {
    Route::post('/logout', [LoginController::class, 'destroy'])->name('logout');

    Route::get('/', fn () => redirect('/modulos'));
    Route::get('/modulos', ModulosController::class)->name('modulos');
    Route::get('/dashboard', DashboardController::class)->name('dashboard')->middleware('can:documento.visualizar');

    // Organograma (TR 4.12): todos os servidores veem; quem administra UGs desativa, reativa e publica
    Route::get('organograma', [\App\Http\Controllers\OrganogramaController::class, 'index'])->name('organograma');

    // Sugestões do editor: @ pessoas, ! quem assina, # documentos (TR 4.16.13–4.16.18)
    Route::get('editor/sugestoes', [\App\Http\Controllers\EditorController::class, 'sugestoes'])->middleware('throttle:120,1')->name('editor.sugestoes');
    Route::post('editor/previa', [\App\Http\Controllers\EditorController::class, 'previa'])->middleware('throttle:30,1')->name('editor.previa');
    // Modelos de texto do setor e da administração (TR 4.16.11)
    Route::get('modelos-texto', [\App\Http\Controllers\ModeloTextoController::class, 'index'])->name('modelos-texto.index');
    Route::post('modelos-texto', [\App\Http\Controllers\ModeloTextoController::class, 'store'])->middleware('throttle:30,1')->name('modelos-texto.store');
    Route::delete('modelos-texto/{id}', [\App\Http\Controllers\ModeloTextoController::class, 'destroy'])->whereNumber('id')->name('modelos-texto.destroy');

    // Rascunho automático do editor (TR 4.16.6–4.16.8) — chamadas da tela de criação
    Route::controller(\App\Http\Controllers\RascunhoController::class)->prefix('rascunhos')->name('rascunhos.')->group(function () {
        Route::post('/', 'store')->middleware('throttle:60,1')->name('store');
        Route::put('{id}', 'update')->whereNumber('id')->middleware('throttle:120,1')->name('update');
        Route::post('{id}/trava', 'travar')->whereNumber('id')->name('travar');
        Route::post('{id}/liberar', 'liberar')->whereNumber('id')->name('liberar');
        Route::post('{id}/anexos', 'anexar')->whereNumber('id')->name('anexar');
        Route::delete('{id}/anexos/{anexo}', 'removerAnexo')->whereNumber(['id', 'anexo'])->name('anexos.remover');
        Route::delete('{id}', 'destroy')->whereNumber('id')->name('destroy');
    });
    Route::middleware('can:admin.ugs')->group(function () {
        Route::post('organograma/publicar', [\App\Http\Controllers\OrganogramaController::class, 'publicar'])->name('organograma.publicar');
        Route::post('organograma/{id}/desativar', [\App\Http\Controllers\OrganogramaController::class, 'desativar'])->whereNumber('id')->name('organograma.desativar');
        Route::post('organograma/{id}/reativar', [\App\Http\Controllers\OrganogramaController::class, 'reativar'])->whereNumber('id')->name('organograma.reativar');
    });

    // Documentos — permissões do catálogo App\Support\Permissoes (Gate::before no AppServiceProvider)
    Route::resource('documentos', DocumentoController::class)->except(['create', 'edit'])
        ->middlewareFor(['index', 'show'], 'can:documento.visualizar')
        ->middlewareFor('store', 'can:documento.criar')
        ->middlewareFor('update', 'can:documento.editar')
        ->middlewareFor('destroy', 'can:documento.excluir');
    Route::post('documentos/mover-pasta', [DocumentoController::class, 'moverPasta'])->name('documentos.mover-pasta')->middleware('can:documento.editar');
    Route::get('documentos/{id}/download', [DocumentoController::class, 'download'])->name('documentos.download')->middleware('can:documento.download');
    Route::get('documentos/{id}/preview', [DocumentoController::class, 'preview'])->name('documentos.preview')->middleware('can:documento.visualizar');
    Route::post('documentos/{id}/favorito', [DocumentoController::class, 'toggleFavorito'])->name('documentos.favorito')->middleware('can:documento.visualizar');
    Route::post('documentos/{id}/versoes', [DocumentoController::class, 'novaVersao'])->name('documentos.versoes.store')->middleware('can:documento.editar');
    Route::post('documentos/{id}/versoes/{versao}/restaurar', [DocumentoController::class, 'restaurarVersao'])->name('documentos.versoes.restaurar')->middleware('can:documento.editar');
    Route::get('documentos/{id}/versoes/{versao}/download', [DocumentoController::class, 'downloadVersao'])->name('documentos.versoes.download')->middleware('can:documento.download');

    // Rotinas favoritas (Ctrl+K → estrela) — por usuário × UG. Não são os documentos favoritos acima.
    Route::post('rotinas/favoritos', [\App\Http\Controllers\RotinaFavoritaController::class, 'store'])->name('rotinas.favoritos.store');
    Route::post('rotinas/favoritos/remover', [\App\Http\Controllers\RotinaFavoritaController::class, 'destroy'])->name('rotinas.favoritos.destroy');
    Route::post('documentos/{id}/status', [DocumentoController::class, 'alterarStatus'])->name('documentos.status')->middleware('can:documento.editar');

    // Pastas / Repositorio
    Route::get('repositorio', [PastaController::class, 'index'])->name('repositorio')->middleware('can:documento.visualizar');
    Route::get('pastas/tree', [PastaController::class, 'tree'])->name('pastas.tree')->middleware('can:pasta.visualizar');
    Route::resource('pastas', PastaController::class)->except(['index', 'show'])
        ->middlewareFor(['create', 'store'], 'can:pasta.criar')
        ->middlewareFor(['edit', 'update'], 'can:pasta.editar')
        ->middlewareFor('destroy', 'can:pasta.excluir');
    Route::post('pastas/{id}/inativar', [PastaController::class, 'inativar'])->name('pastas.inativar')->middleware('can:pasta.editar');

    // Lixeira: documentos excluídos e pastas inativas
    Route::get('lixeira', [\App\Http\Controllers\LixeiraController::class, 'index'])->name('lixeira')->middleware('can:documento.excluir');
    Route::post('lixeira/documentos/{id}/restaurar', [\App\Http\Controllers\LixeiraController::class, 'restaurarDocumento'])->name('lixeira.documentos.restaurar')->middleware('can:documento.excluir');
    Route::post('pastas/{id}/reativar', [PastaController::class, 'reativar'])->name('pastas.reativar')->middleware('can:pasta.editar');

    // Captura
    Route::get('capturar', [CapturaController::class, 'index'])->name('capturar')->middleware('can:documento.criar');
    Route::post('capturar/upload', [CapturaController::class, 'upload'])->name('capturar.upload')->middleware('can:documento.criar');

    // Fluxos
    Route::resource('fluxos', FluxoController::class)
        ->middlewareFor(['index', 'show'], 'can:fluxo.visualizar')
        ->middlewareFor(['create', 'store'], 'can:fluxo.criar')
        ->middlewareFor(['edit', 'update', 'destroy'], 'can:fluxo.editar');
    Route::post('fluxos/{id}/iniciar', [FluxoController::class, 'iniciar'])->name('fluxos.iniciar')->middleware('can:fluxo.gerenciar');

    // Busca
    Route::middleware('can:documento.visualizar')->group(function () {
        Route::get('busca', [BuscaController::class, 'index'])->name('busca');
        Route::post('busca/salvar', [BuscaController::class, 'salvar'])->name('busca.salvar');
        Route::delete('busca/salvar/{id}', [BuscaController::class, 'destroy'])->name('busca.destroy');
    });

    // Imagens institucionais da UG — exibidas a todo usuário logado (cabeçalho, portal)
    Route::get('configuracoes/ugs/{id}/brasao', [\App\Http\Controllers\Configuracao\UgController::class, 'brasao'])->name('configuracoes.ug.brasao');
    Route::get('configuracoes/ugs/{id}/banner', [\App\Http\Controllers\Configuracao\UgController::class, 'banner'])->name('configuracoes.ug.banner');
    Route::get('configuracoes/ugs/{ug}/banners/{banner}/imagem', [\App\Http\Controllers\Configuracao\BannerPortalController::class, 'imagem'])->name('configuracoes.ug.banners.imagem');

    // Configuracoes — modulo dedicado para usuarios, perfis, UGs e organograma
    Route::prefix('configuracoes')->name('configuracoes.')->group(function () {
        // Visao geral do modulo
        Route::get('/', fn () => Inertia\Inertia::render('Configuracao/Index'))->name('index')->middleware('can:admin.configuracoes');

        // Usuarios e perfis (movidos de /admin)
        Route::resource('usuarios', UsuarioController::class)->except(['show'])->middleware('can:admin.usuarios');
        Route::post('usuarios/{id}/reativar', [UsuarioController::class, 'reativar'])->middleware('can:admin.usuarios')->name('usuarios.reativar');
        Route::post('usuarios/{id}/dois-fatores/remover', [UsuarioController::class, 'removerDoisFatores'])->middleware('can:admin.usuarios')->name('usuarios.dois-fatores.remover');
        Route::post('usuarios/{id}/foto', [UsuarioController::class, 'enviarFoto'])->middleware('can:admin.usuarios')->name('usuarios.foto');
        Route::post('usuarios/{id}/foto/remover', [UsuarioController::class, 'removerFoto'])->middleware('can:admin.usuarios')->name('usuarios.foto.remover');
        Route::resource('perfis', RoleController::class)->parameters(['perfis' => 'role'])->except(['create', 'edit', 'show'])->middleware('can:admin.roles');

        // Expediente e feriados — base do prazo em dias úteis (TR 4.13.9–4.13.11)
        // Identidade visual do município (TR 4.22.1)
        Route::get('identidade-visual', [\App\Http\Controllers\Configuracao\IdentidadeVisualController::class, 'index'])->name('identidade')->middleware('can:admin.configuracoes');
        Route::post('identidade-visual', [\App\Http\Controllers\Configuracao\IdentidadeVisualController::class, 'salvar'])->name('identidade.salvar')->middleware('can:admin.configuracoes');
        Route::prefix('expediente')->name('expediente.')->middleware('can:admin.configuracoes')
            ->controller(\App\Http\Controllers\Configuracao\ExpedienteController::class)->group(function () {
                Route::get('/', 'index')->name('index');
                Route::put('/', 'salvar')->name('salvar');
                Route::post('feriados', 'adicionarFeriado')->name('feriados.store');
                Route::delete('feriados/{id}', 'removerFeriado')->whereNumber('id')->name('feriados.destroy');
                Route::post('feriados/nacionais', 'importarNacionais')->name('feriados.nacionais');
            });

        // Unidades Gestoras + organograma + banners do portal
        Route::middleware('can:admin.ugs')->group(function () {
        Route::resource('ugs', \App\Http\Controllers\Configuracao\UgController::class)->except(['show']);
        Route::post('ugs/{id}/toggle-ativo', [\App\Http\Controllers\Configuracao\UgController::class, 'toggleAtivo'])->name('ugs.toggle-ativo');

        // Banners do Portal Cidadao (carrossel)
        Route::get('ugs/{ug}/banners',                [\App\Http\Controllers\Configuracao\BannerPortalController::class, 'index'])->name('ug.banners.index');
        Route::post('ugs/{ug}/banners',               [\App\Http\Controllers\Configuracao\BannerPortalController::class, 'store'])->name('ug.banners.store');
        Route::put('ugs/{ug}/banners/{banner}',       [\App\Http\Controllers\Configuracao\BannerPortalController::class, 'update'])->name('ug.banners.update');
        Route::delete('ugs/{ug}/banners/{banner}',    [\App\Http\Controllers\Configuracao\BannerPortalController::class, 'destroy'])->name('ug.banners.destroy');
        Route::post('ugs/{ug}/banners/{banner}/move/{direcao}', [\App\Http\Controllers\Configuracao\BannerPortalController::class, 'move'])->name('ug.banners.move');

        Route::get('ugs/{ug}/organograma', [\App\Http\Controllers\Configuracao\UgOrganogramaController::class, 'show'])->name('ugs.organograma');
        Route::post('ugs/{ug}/organograma/labels', [\App\Http\Controllers\Configuracao\UgOrganogramaController::class, 'updateLabels'])->name('ugs.organograma.labels');

        // Tela dedicada de cadastro de no
        Route::get('ugs/{ug}/organograma/nodes/create', [\App\Http\Controllers\Configuracao\UgOrganogramaController::class, 'createNode'])->name('ugs.organograma.nodes.create');
        Route::get('ugs/{ug}/organograma/nodes/{node}/edit', [\App\Http\Controllers\Configuracao\UgOrganogramaController::class, 'editNode'])->name('ugs.organograma.nodes.edit');

        Route::post('ugs/{ug}/organograma/nodes', [\App\Http\Controllers\Configuracao\UgOrganogramaController::class, 'storeNode'])->name('ugs.organograma.nodes.store');
        Route::put('ugs/{ug}/organograma/nodes/{node}', [\App\Http\Controllers\Configuracao\UgOrganogramaController::class, 'updateNode'])->name('ugs.organograma.nodes.update');
        Route::delete('ugs/{ug}/organograma/nodes/{node}', [\App\Http\Controllers\Configuracao\UgOrganogramaController::class, 'destroyNode'])->name('ugs.organograma.nodes.destroy');
        Route::post('ugs/{ug}/organograma/nodes/{node}/toggle-ativo', [\App\Http\Controllers\Configuracao\UgOrganogramaController::class, 'toggleAtivoNode'])->name('ugs.organograma.nodes.toggle-ativo');
        });

        // Certificado da prefeitura (e-CNPJ A1 por UG) — sela os PDFs assinados por link
        Route::middleware('can:admin.ugs')->group(function () {
            Route::get('certificado-prefeitura', [\App\Http\Controllers\Configuracao\CertificadoInstitucionalController::class, 'index'])
                ->name('certificado-prefeitura.index');
            Route::post('certificado-prefeitura', [\App\Http\Controllers\Configuracao\CertificadoInstitucionalController::class, 'store'])
                ->name('certificado-prefeitura.store');
            Route::post('certificado-prefeitura/{ugId}/toggle', [\App\Http\Controllers\Configuracao\CertificadoInstitucionalController::class, 'toggle'])
                ->whereNumber('ugId')->name('certificado-prefeitura.toggle');
        });

        // Sistemas integrados (API tokens para sistemas externos)
        Route::middleware('can:admin.sistemas_integrados')->group(function () {
        // Formulários do app da fiscalização (TR 4.52)
        Route::controller(\App\Http\Controllers\AppFiscalizacaoController::class)->prefix('fiscalizacao')->name('fiscalizacao.')->group(function () {
            Route::get('/', 'index')->name('index');
            Route::post('formularios', 'salvarFormulario')->name('formularios.store');
            Route::post('formularios/{id}', 'salvarFormulario')->whereNumber('id')->name('formularios.update');
            Route::delete('formularios/{id}', 'excluirFormulario')->whereNumber('id')->name('formularios.destroy');
        });
        // Consulta prévia: zonas, camadas e lotes do mapa (TR 4.50)
        Route::controller(\App\Http\Controllers\UrbanismoController::class)->prefix('urbanismo')->name('urbanismo.')->group(function () {
            Route::get('/', 'index')->name('index');
            Route::post('zonas', 'salvarZona')->name('zonas.store');
            Route::post('zonas/{id}', 'salvarZona')->whereNumber('id')->name('zonas.update');
            Route::delete('zonas/{id}', 'excluirZona')->whereNumber('id')->name('zonas.destroy');
            Route::post('camadas', 'salvarCamada')->name('camadas.store');
            Route::post('camadas/{id}', 'salvarCamada')->whereNumber('id')->name('camadas.update');
            Route::delete('camadas/{id}', 'excluirCamada')->whereNumber('id')->name('camadas.destroy');
            Route::post('lotes', 'importarLotes')->name('lotes.importar');
            Route::post('config', 'salvarConfig')->name('config');
        });
        // Sistema tributário integrado (GPE Tributário, outro fornecedor por REST ou nenhum)
        Route::controller(\App\Http\Controllers\Configuracao\IntegracaoTributariaController::class)->middleware('can:admin.sistemas_integrados')->group(function () {
            Route::get('integracao-tributaria', 'index')->name('integracao-tributaria.index');
            Route::post('integracao-tributaria', 'salvar')->name('integracao-tributaria.store');
            Route::put('integracao-tributaria/{id}', 'salvar')->whereNumber('id')->name('integracao-tributaria.update');
            Route::post('integracao-tributaria/{id}/testar', 'testar')->whereNumber('id')->name('integracao-tributaria.testar');
            Route::delete('integracao-tributaria/{id}', 'excluir')->whereNumber('id')->name('integracao-tributaria.destroy');
        });
        Route::resource('sistemas-integrados', \App\Http\Controllers\Configuracao\SistemaIntegradoController::class)
            ->except(['create', 'edit', 'show']);
        Route::post('sistemas-integrados/{id}/regenerar-token', [\App\Http\Controllers\Configuracao\SistemaIntegradoController::class, 'regenerarToken'])
            ->name('sistemas-integrados.regenerar-token');
        Route::post('sistemas-integrados/{id}/regenerar-webhook-secret', [\App\Http\Controllers\Configuracao\SistemaIntegradoController::class, 'regenerarWebhookSecret'])
            ->name('sistemas-integrados.regenerar-webhook-secret');
        Route::post('sistemas-integrados/webhook-logs/{id}/reenviar', [\App\Http\Controllers\Configuracao\SistemaIntegradoController::class, 'reenviarWebhook'])
            ->name('sistemas-integrados.reenviar-webhook');
        Route::post('sistemas-integrados/{id}/toggle-ativo', [\App\Http\Controllers\Configuracao\SistemaIntegradoController::class, 'toggleAtivo'])
            ->name('sistemas-integrados.toggle-ativo');
        });

        // Carta de Servicos (admin) — gestor da UG mantem catalogo publicado no /portal
        // Textos do portal: política de privacidade e acesso à informação (TR 4.23.1, 4.23.5)
        Route::get('portal-textos', [\App\Http\Controllers\Configuracao\PortalTextosController::class, 'index'])->name('portal-textos')->middleware('can:portal.carta_servicos');
        Route::put('portal-textos/{chave}', [\App\Http\Controllers\Configuracao\PortalTextosController::class, 'salvar'])->name('portal-textos.salvar')->middleware('can:portal.carta_servicos');
        Route::prefix('carta-servicos')->name('carta-servicos.')->middleware('can:portal.carta_servicos')->group(function () {
            Route::get('/', [\App\Http\Controllers\Configuracao\CartaServicosController::class, 'index'])->name('index');
            Route::post('categorias',           [\App\Http\Controllers\Configuracao\CartaServicosController::class, 'storeCategoria'])->name('categorias.store');
            Route::put('categorias/{id}',       [\App\Http\Controllers\Configuracao\CartaServicosController::class, 'updateCategoria'])->name('categorias.update');
            Route::delete('categorias/{id}',    [\App\Http\Controllers\Configuracao\CartaServicosController::class, 'destroyCategoria'])->name('categorias.destroy');
            Route::post('servicos',             [\App\Http\Controllers\Configuracao\CartaServicosController::class, 'storeServico'])->name('servicos.store');
            Route::put('servicos/{id}',         [\App\Http\Controllers\Configuracao\CartaServicosController::class, 'updateServico'])->name('servicos.update');
            Route::delete('servicos/{id}',      [\App\Http\Controllers\Configuracao\CartaServicosController::class, 'destroyServico'])->name('servicos.destroy');
            Route::post('servicos/{id}/toggle-publicado', [\App\Http\Controllers\Configuracao\CartaServicosController::class, 'togglePublicado'])->name('servicos.toggle-publicado');
        });
    });

    // Admin (apenas tipos documentais e tipos de processo permanecem aqui)
    Route::prefix('admin')->name('admin.')->group(function () {
        Route::middleware('can:admin.tipos_documentais')->group(function () {
            Route::resource('tipos-documentais', TipoDocumentalController::class)->except(['create', 'edit', 'show']);
            Route::post('tipos-documentais/{id}/toggle-ativo', [TipoDocumentalController::class, 'toggleAtivo'])->name('tipos-documentais.toggle-ativo');
        });
        // Plano de classificação (TR 4.27)
        Route::middleware('can:admin.classificacao')->controller(\App\Http\Controllers\Admin\ClassificacaoController::class)->group(function () {
            Route::get('classificacao', 'index')->name('classificacao');
            Route::post('classificacao', 'store')->name('classificacao.store');
            Route::put('classificacao/{id}', 'update')->whereNumber('id')->name('classificacao.update');
            Route::delete('classificacao/{id}', 'destroy')->whereNumber('id')->name('classificacao.destroy');
            Route::post('classificacao/{id}/alternar', 'alternar')->whereNumber('id')->name('classificacao.alternar');
            Route::post('classificacao/padroes', 'padroes')->name('classificacao.padroes');
            Route::post('classificacao/retroativa', 'retroativa')->name('classificacao.retroativa');
            Route::post('classificacao/conferir', 'conferir')->name('classificacao.conferir');
            Route::post('classificacao/importar', 'importar')->name('classificacao.importar');
            Route::get('classificacao/exportar', 'exportar')->name('classificacao.exportar');
            Route::post('classificacao/item/{modulo}/{id}', 'reclassificar')->whereNumber('id')->name('classificacao.reclassificar');
        });
        Route::middleware('can:admin.tipos_processo')->group(function () {
            Route::resource('tipos-processo', TipoProcessoController::class)->except(['create', 'edit', 'show']);
            Route::post('tipos-processo/{id}/toggle-ativo', [TipoProcessoController::class, 'toggleAtivo'])->name('tipos-processo.toggle-ativo');
            Route::post('tipos-processo/modelos-projetos', [TipoProcessoController::class, 'modelosProjetos'])->name('tipos-processo.modelos-projetos');
            Route::post('tipos-processo/ajuda-imagem', [TipoProcessoController::class, 'enviarImagemAjuda'])->name('tipos-processo.ajuda-imagem');
            Route::resource('oficios-modelos', \App\Http\Controllers\Admin\OficioModeloController::class)->only(['index', 'store', 'update', 'destroy']);
            Route::post('oficios-modelos/importar-docx', [\App\Http\Controllers\Admin\OficioModeloController::class, 'importarDocx'])->name('oficios-modelos.importar-docx');
        });

        // Redirects das URLs antigas para o modulo Configuracoes
        Route::redirect('usuarios', '/configuracoes/usuarios');
        Route::redirect('roles', '/configuracoes/perfis');

    });

    // As Solicitações do Portal mudaram de GPE Config para o GPE Fluxos e Processos
    // (/flow/portal/solicitacoes). O endereço antigo segue valendo.
    // Fora do grupo nomeado: redirecionamento sem nome (nome repetido quebra o route:cache).
    Route::redirect('configuracoes/solicitacoes-portal', '/flow/portal/solicitacoes');
    Route::get('configuracoes/solicitacoes-portal/{id}', fn (string $id) => redirect("/flow/portal/solicitacoes/{$id}"))->whereNumber('id');

    // GPE Fluxos e Processos — Portal do Cidadão: atendimento das solicitações
    // (ouvidoria, protocolo...), que viram processos. Mesmos nomes de rota de antes.
    Route::prefix('flow/portal')->name('configuracoes.')->group(function () {
        // Solicitacoes do Portal Cidadao (atendimento pelos servidores)
        Route::prefix('solicitacoes')->name('solicitacoes-portal.')->middleware('can:portal.atendimento')->group(function () {
            Route::get('/',                       [\App\Http\Controllers\Configuracao\PortalSolicitacoesController::class, 'index'])->name('index');
            Route::get('/{id}',                   [\App\Http\Controllers\Configuracao\PortalSolicitacoesController::class, 'show'])->name('show');
            Route::post('/{id}/status',           [\App\Http\Controllers\Configuracao\PortalSolicitacoesController::class, 'alterarStatus'])->name('status');
            Route::post('/{id}/comentar',         [\App\Http\Controllers\Configuracao\PortalSolicitacoesController::class, 'comentar'])->name('comentar');
            Route::get('/{id}/anexos/{anexoId}',  [\App\Http\Controllers\Configuracao\PortalSolicitacoesController::class, 'anexo'])->whereNumber(['id', 'anexoId'])->name('anexo');
            Route::post('/{id}/processo',         [\App\Http\Controllers\Configuracao\PortalSolicitacoesController::class, 'abrirProcesso'])
                ->whereNumber('id')->middleware('can:processo.criar')->name('processo');
        });
    });

    // Memorandos
    // Comunicações: ver/responder depende de ser participante (403 no controller); emitir exige permissão
    // Diário Oficial (TR 4.43)
    Route::controller(\App\Http\Controllers\DiarioController::class)->prefix('diario')->name('diario.')->group(function () {
        Route::get('/', 'painel')->name('painel');
        Route::get('configuracao', 'configuracao')->name('configuracao');
        Route::post('estrutura', 'salvarEstrutura')->name('estrutura.store');
        Route::put('estrutura/{id}', 'salvarEstrutura')->whereNumber('id')->name('estrutura.update');
        Route::delete('estrutura/{id}', 'excluirEstrutura')->whereNumber('id')->name('estrutura.destroy');
        Route::post('responsaveis', 'salvarResponsavel')->name('responsaveis.store');
        Route::put('responsaveis/{id}', 'salvarResponsavel')->whereNumber('id')->name('responsaveis.update');
        Route::post('materias', 'enviar')->name('materias.store');
        Route::put('materias/{id}', 'atualizarMateria')->whereNumber('id')->name('materias.update');
        Route::delete('materias/{id}', 'retirarMateria')->whereNumber('id')->name('materias.destroy');
        Route::post('edicoes', 'criarEdicao')->name('edicoes.store');
        Route::get('edicoes/{id}', 'edicao')->whereNumber('id')->name('edicoes.show');
        Route::put('edicoes/{id}', 'atualizarEdicao')->whereNumber('id')->name('edicoes.update');
        Route::delete('edicoes/{id}', 'excluirEdicao')->whereNumber('id')->name('edicoes.destroy');
        Route::post('edicoes/{id}/publicar', 'publicar')->whereNumber('id')->middleware('throttle:10,1')->name('edicoes.publicar');
        Route::get('edicoes/{id}/pdf', 'pdf')->whereNumber('id')->name('edicoes.pdf');
    });
    // Atos oficiais (TR 4.42)
    Route::controller(\App\Http\Controllers\AtoController::class)->group(function () {
        Route::get('atos', 'index')->name('atos.index');
        Route::get('atos/tipos', 'tipos')->name('atos.tipos');
        Route::post('atos/tipos', 'salvarTipo')->name('atos.tipos.store');
        Route::post('atos/tipos/{id}', 'salvarTipo')->whereNumber('id')->name('atos.tipos.update');
        Route::get('atos/create', 'create')->name('atos.create');
        Route::post('atos', 'store')->name('atos.store');
        Route::get('atos/{id}', 'show')->whereNumber('id')->name('atos.show');
        Route::get('atos/{id}/edit', 'edit')->whereNumber('id')->name('atos.edit');
        Route::put('atos/{id}', 'update')->whereNumber('id')->name('atos.update');
        Route::post('atos/{id}/gerar', 'gerar')->whereNumber('id')->middleware('throttle:20,1')->name('atos.gerar');
        Route::post('atos/{id}/assinar', 'assinar')->whereNumber('id')->name('atos.assinar');
        Route::post('atos/{id}/publicar', 'publicar')->whereNumber('id')->name('atos.publicar');
        Route::post('atos/{id}/revogar', 'revogar')->whereNumber('id')->name('atos.revogar');
        Route::get('atos/{id}/pdf', 'pdf')->whereNumber('id')->name('atos.pdf');
    });
    // Pareceres (TR 4.39)
    Route::controller(\App\Http\Controllers\ParecerController::class)->group(function () {
        Route::get('pareceres', 'index')->name('pareceres.index');
        Route::get('pareceres/create', 'create')->name('pareceres.create');
        Route::post('pareceres', 'store')->name('pareceres.store');
        Route::post('pareceres/setores', 'setores')->name('pareceres.setores');
        Route::get('pareceres/{id}', 'show')->whereNumber('id')->name('pareceres.show');
        Route::get('pareceres/{id}/pdf', 'pdf')->whereNumber('id')->name('pareceres.pdf');
        Route::get('pareceres/{id}/anexos/{anexo}', 'anexo')->whereNumber(['id', 'anexo'])->name('pareceres.anexo');
        Route::post('pareceres/{id}/assinar', 'assinar')->whereNumber('id')->name('pareceres.assinar');
    });
    Route::resource('memorandos', MemorandoController::class)->only(['index', 'create', 'store', 'show'])
        ->middlewareFor(['create', 'store'], 'can:comunicacao.enviar');
    Route::post('memorandos/{id}/responder', [MemorandoController::class, 'responder'])->name('memorandos.responder');
    Route::post('memorandos/{id}/arquivar', [MemorandoController::class, 'arquivar'])->name('memorandos.arquivar');
    Route::post('memorandos/{id}/receber', [MemorandoController::class, 'receber'])->name('memorandos.receber');
    Route::post('memorandos/{id}/tramitar', [MemorandoController::class, 'tramitar'])->name('memorandos.tramitar');
    Route::post('memorandos/{id}/arquivar-no-ged', [MemorandoController::class, 'arquivarNoGed'])->name('memorandos.arquivar-no-ged');
    Route::get('memorandos/{id}/pdf', [MemorandoController::class, 'downloadPdf'])->name('memorandos.pdf');
    Route::get('memorandos/{id}/folha-de-rosto', [MemorandoController::class, 'folhaDeRosto'])->whereNumber('id')->name('memorandos.folha-de-rosto');
    Route::get('memorandos/{id}/anexos/{anexo}/download', [MemorandoController::class, 'downloadAnexo'])->name('memorandos.anexos.download');

    // Oficios
    // Chat interno
    Route::get('chat/contatos',                [\App\Http\Controllers\ChatController::class, 'contatos'])->name('chat.contatos');
    Route::get('chat/nao-lidas',               [\App\Http\Controllers\ChatController::class, 'naoLidas'])->name('chat.nao-lidas');
    Route::get('chat/mensagens/{contatoId}',   [\App\Http\Controllers\ChatController::class, 'mensagens'])->name('chat.mensagens');
    Route::post('chat/enviar/{contatoId}',     [\App\Http\Controllers\ChatController::class, 'enviar'])->name('chat.enviar');

    Route::get('oficios/controle', [OficioController::class, 'controle'])->name('oficios.controle');
    Route::get('oficios/modelos-disponiveis', [\App\Http\Controllers\Admin\OficioModeloController::class, 'disponiveis'])->name('oficios.modelos-disponiveis');
    Route::resource('oficios', OficioController::class)->only(['index', 'create', 'store', 'show'])
        ->middlewareFor(['create', 'store'], 'can:comunicacao.enviar');
    Route::post('oficios/{id}/responder', [OficioController::class, 'responder'])->name('oficios.responder');
    Route::post('oficios/{id}/reenviar-email', [OficioController::class, 'reenviarEmail'])
        ->middleware(['can:comunicacao.enviar', 'throttle:10,1'])->name('oficios.reenviar-email');
    Route::post('oficios/{id}/arquivar', [OficioController::class, 'arquivar'])->name('oficios.arquivar');
    Route::post('oficios/{id}/arquivar-no-ged', [OficioController::class, 'arquivarNoGed'])->name('oficios.arquivar-no-ged');
    Route::get('oficios/{id}/pdf', [OficioController::class, 'downloadPdf'])->name('oficios.pdf');
    Route::get('oficios/{id}/anexos/{anexo}/download', [OficioController::class, 'downloadAnexo'])->name('oficios.anexos.download');

    // Circulares
    Route::resource('circulares', CircularController::class)->only(['index', 'create', 'store', 'show'])
        ->middlewareFor(['create', 'store'], 'can:comunicacao.enviar');
    Route::post('circulares/{id}/arquivar', [CircularController::class, 'arquivar'])->name('circulares.arquivar');
    // Respostas, arquivar/silenciar para si e encaminhar a setores novos (TR 4.46.6, 4.46.9, 4.46.10)
    Route::post('circulares/{id}/responder', [CircularController::class, 'responder'])->whereNumber('id')->name('circulares.responder');
    Route::post('circulares/{id}/arquivar-para-mim', [CircularController::class, 'arquivarParaMim'])->whereNumber('id')->name('circulares.arquivar-para-mim');
    Route::post('circulares/{id}/silenciar', [CircularController::class, 'silenciar'])->whereNumber('id')->name('circulares.silenciar');
    Route::post('circulares/{id}/encaminhar', [CircularController::class, 'encaminhar'])->whereNumber('id')->name('circulares.encaminhar');
    Route::post('circulares/{id}/arquivar-no-ged', [CircularController::class, 'arquivarNoGed'])->name('circulares.arquivar-no-ged');
    Route::get('circulares/{id}/pdf', [CircularController::class, 'downloadPdf'])->name('circulares.pdf');
    Route::get('circulares/{id}/anexos/{anexo}/download', [CircularController::class, 'downloadAnexo'])->name('circulares.anexos.download');

    // GPE Flow — Inbox unificada por ESTADO
    Route::prefix('flow')->name('flow.')->group(function () {
        // Atendimento › Consultar Protocolo (balcão/telefone): processos e solicitações do portal.
        Route::get('atendimento/consultar', \App\Http\Controllers\Flow\ConsultaProtocoloController::class)
            ->middleware('can:processo.visualizar')->name('atendimento.consultar');

        // Entrada (preciso agir)
        Route::get('inbox-pessoal',         [\App\Http\Controllers\Flow\InboxController::class, 'pessoal'])->name('inbox-pessoal');
        Route::get('inbox-setor',           [\App\Http\Controllers\Flow\InboxController::class, 'setor'])->name('inbox-setor');
        Route::get('prazos',                [\App\Http\Controllers\Flow\CalendarioPrazosController::class, 'index'])->name('prazos');
        // Busca avançada de processos, memorandos e ofícios (TR 4.15)
        Route::get('busca', [\App\Http\Controllers\Flow\BuscaController::class, 'index'])->name('busca');
        // Etiquetas do setor (TR 4.19)
        Route::controller(\App\Http\Controllers\Flow\EtiquetaController::class)->group(function () {
            Route::get('etiquetas', 'index')->name('etiquetas');
            Route::post('etiquetas', 'store')->name('etiquetas.store');
            Route::put('etiquetas/{id}', 'update')->whereNumber('id')->name('etiquetas.update');
            Route::delete('etiquetas/{id}', 'destroy')->whereNumber('id')->name('etiquetas.destroy');
            Route::post('etiquetar', 'aplicar')->name('etiquetar');
        });
        // Mapa das demandas (TR 4.32.21, 4.33.9)
        Route::get('mapa', [\App\Http\Controllers\Flow\MapaAvaliacaoController::class, 'mapa'])->middleware('can:processo.visualizar')->name('mapa');
        // Painéis da ouvidoria e do e-SIC (TR 4.33.25–26, 4.34)
        Route::get('ouvidoria', [\App\Http\Controllers\Flow\OuvidoriaController::class, 'ouvidoria'])->name('ouvidoria');
        Route::get('esic', [\App\Http\Controllers\Flow\OuvidoriaController::class, 'esic'])->name('esic');
        // Processo seletivo simplificado (TR 4.41)
        Route::middleware('can:pss.gerir')->controller(\App\Http\Controllers\Flow\PssController::class)->group(function () {
            Route::get('pss', 'index')->name('pss');
            Route::post('pss', 'salvar')->name('pss.store');
            Route::put('pss/{id}', 'salvar')->whereNumber('id')->name('pss.update');
            Route::get('pss/{id}', 'show')->whereNumber('id')->name('pss.show');
            Route::get('pss/{id}/csv', 'csv')->whereNumber('id')->name('pss.csv');
            Route::get('pss/{id}/anexos/{anexo}', 'anexo')->whereNumber(['id', 'anexo'])->name('pss.anexo');
            Route::post('pss/{id}/inscricoes/{inscricao}/resposta', 'responder')->whereNumber(['id', 'inscricao'])->name('pss.responder');
        });
        // PAD (TR 4.40)
        Route::controller(\App\Http\Controllers\Flow\PadController::class)->group(function () {
            Route::get('pad', 'index')->name('pad');
            Route::get('pad/infracoes', 'infracoes')->name('pad.infracoes');
            Route::post('pad/infracoes', 'salvarInfracao')->name('pad.infracoes.store');
            Route::put('pad/infracoes/{id}', 'salvarInfracao')->whereNumber('id')->name('pad.infracoes.update');
            Route::post('pad/{id}/comissao', 'comissao')->whereNumber('id')->name('pad.comissao');
            Route::delete('pad/{id}/comissao/{userId}', 'removerMembro')->whereNumber(['id', 'userId'])->name('pad.comissao.remover');
        });
        // Protocolo legislativo (TR 4.37)
        Route::middleware('can:legislativo.gerir')->controller(\App\Http\Controllers\Flow\LegislativoController::class)->group(function () {
            Route::get('legislativo', 'index')->name('legislativo');
            Route::get('legislativo/relatorio', 'relatorio')->name('legislativo.relatorio');
            Route::get('legislativo/vereadores', 'vereadores')->name('legislativo.vereadores');
            Route::post('legislativo/vereadores', 'salvarVereador')->name('legislativo.vereadores.store');
            Route::put('legislativo/vereadores/{id}', 'salvarVereador')->whereNumber('id')->name('legislativo.vereadores.update');
        });
        // Contatos (TR 4.21)
        Route::middleware('can:contato.gerenciar')->controller(\App\Http\Controllers\Contatos\ContatoController::class)->group(function () {
            Route::get('contatos', 'index')->name('contatos');
            Route::get('contatos/novo', 'create')->name('contatos.create');
            Route::get('contatos/buscar', 'buscar')->name('contatos.buscar');
            Route::get('contatos/exportar', 'exportar')->name('contatos.exportar');
            Route::post('contatos/conferir', 'conferir')->name('contatos.conferir');
            Route::post('contatos/importar', 'importar')->name('contatos.importar');
            Route::post('contatos/unificar', 'unificar')->name('contatos.unificar');
            Route::post('contatos', 'store')->name('contatos.store');
            Route::get('contatos/{id}', 'show')->whereNumber('id')->name('contatos.show');
            Route::put('contatos/{id}', 'update')->whereNumber('id')->name('contatos.update');
            Route::delete('contatos/{id}', 'destroy')->whereNumber('id')->name('contatos.destroy');
            Route::post('contatos/{id}/procuracoes', 'procuracao')->whereNumber('id')->name('contatos.procuracao');
            Route::post('contatos/procuracoes/{id}/revogar', 'revogarProcuracao')->whereNumber('id')->name('contatos.procuracao.revogar');
            Route::get('contatos/procuracoes/{id}/documento', 'documentoProcuracao')->whereNumber('id')->name('contatos.procuracao.documento');
            Route::post('contatos/listas', 'salvarLista')->name('contatos.listas.store');
            Route::put('contatos/listas/{id}', 'salvarLista')->whereNumber('id')->name('contatos.listas.update');
            Route::delete('contatos/listas/{id}', 'excluirLista')->whereNumber('id')->name('contatos.listas.destroy');
            Route::post('contatos/listas/{id}/itens', 'itensLista')->whereNumber('id')->name('contatos.listas.itens');
        });
        Route::middleware('can:admin.contatos')->controller(\App\Http\Controllers\Contatos\ConfiguracaoController::class)->group(function () {
            Route::get('contatos/configuracao', 'index')->name('contatos.configuracao');
            Route::post('contatos/campos', 'salvarCampo')->name('contatos.campos.store');
            Route::put('contatos/campos/{id}', 'salvarCampo')->whereNumber('id')->name('contatos.campos.update');
            Route::post('contatos/campos/{id}/alternar', 'alternarCampo')->whereNumber('id')->name('contatos.campos.alternar');
            Route::post('contatos/regras', 'regras')->name('contatos.regras');
        });
        Route::get('aguardando-assinatura', [\App\Http\Controllers\Flow\InboxController::class, 'aguardandoAssinatura'])->name('aguardando-assinatura');

        // Em andamento
        Route::get('em-tramitacao', [\App\Http\Controllers\Flow\InboxController::class, 'emTramitacao'])->name('em-tramitacao');

        // Concluidos
        Route::get('concluidos',    [\App\Http\Controllers\Flow\InboxController::class, 'concluidos'])->name('concluidos');

        // Privado
        Route::get('saida',         [\App\Http\Controllers\Flow\InboxController::class, 'saida'])->name('saida');
        // Rascunhos do editor: meus e do setor (TR 4.16.6–4.16.9)
        Route::get('rascunhos',     [\App\Http\Controllers\RascunhoController::class, 'index'])->name('rascunhos');

        // Compat: URLs antigas
        Route::redirect('encaminhados', '/flow/saida');
        Route::redirect('tramitacao',   '/flow/em-tramitacao');
        Route::redirect('arquivados',   '/flow/concluidos');
    });

    // Processos (GEPSP)
    Route::get('processos/dashboard', [ProcessoDashboardController::class, '__invoke'])->name('processos.dashboard')->middleware('can:processo.visualizar');
    Route::get('processos/inbox', [TramitacaoController::class, 'inbox'])->name('processos.inbox')->middleware('can:processo.visualizar');
    Route::get('processos/anexos/{anexo}/download', [ProcessoController::class, 'downloadAnexo'])->name('processos.anexos.download')->middleware('can:processo.visualizar');
    // Anexos: ZIP, ordem, avaliação e link seguro (TR 4.14.5, 4.20, 4.38.24–4.38.26)
    // Árvore do processo em PDF (TR 4.9.16, 4.38.27–4.38.42)
    Route::post('processos/{id}/arvores', [\App\Http\Controllers\ArvoreController::class, 'solicitar'])->whereNumber('id')->name('processos.arvores.store')->middleware(['can:processo.visualizar', 'throttle:10,1']);
    Route::get('arvores/{id}/baixar', [\App\Http\Controllers\ArvoreController::class, 'baixar'])->whereNumber('id')->name('arvores.baixar')->middleware('can:processo.visualizar');
    Route::get('processos/{id}/anexos/zip',[\App\Http\Controllers\AnexoProcessoController::class, 'zip'])->whereNumber('id')->name('processos.anexos.zip')->middleware('can:processo.visualizar');
    Route::middleware('can:processo.tramitar')->controller(\App\Http\Controllers\AnexoProcessoController::class)->group(function () {
        Route::post('processos/{id}/anexos/ordem', 'ordenar')->whereNumber('id')->name('processos.anexos.ordem');
        Route::post('processos/anexos/{anexo}/avaliar', 'avaliar')->whereNumber('anexo')->name('processos.anexos.avaliar');
        // Revisão de PDF/planta com anotações e medição (TR 4.48.16–4.48.18, 4.48.39)
        Route::get('processos/anexos/{anexo}/revisar', 'revisar')->whereNumber('anexo')->name('processos.anexos.revisar');
        Route::put('processos/anexos/{anexo}/revisao', 'salvarRevisao')->whereNumber('anexo')->name('processos.anexos.revisao');
        Route::post('processos/anexos/{anexo}/revisao/concluir', 'concluirRevisao')->whereNumber('anexo')->middleware('throttle:20,1')->name('processos.anexos.revisao.concluir');
        Route::post('processos/anexos/{anexo}/link', 'link')->whereNumber('anexo')->name('processos.anexos.link');
    });
    Route::post('processos/{id}/comentarios', [ProcessoController::class, 'comentar'])->name('processos.comentarios.store')->middleware('can:processo.visualizar');
    // Imagem de ajuda dos campos do formulário do assunto (TR 4.38.6)
    Route::get('processos/ajuda-imagem/{id}', [\App\Http\Controllers\Admin\TipoProcessoController::class, 'imagemAjuda'])
        ->whereUuid('id')->name('processos.ajuda-imagem');
    Route::resource('processos', ProcessoController::class)->except(['edit', 'update', 'destroy'])
        ->middlewareFor(['index', 'show'], 'can:processo.visualizar')
        ->middlewareFor(['create', 'store'], 'can:processo.criar');
    Route::middleware('can:processo.tramitar')->group(function () {
        Route::post('processos/{id}/concluir', [ProcessoController::class, 'concluir'])->name('processos.concluir');
        Route::post('processos/{id}/cancelar', [ProcessoController::class, 'cancelar'])->name('processos.cancelar');
        Route::post('processos/{id}/assunto', [ProcessoController::class, 'alterarAssunto'])->name('processos.assunto');
        // Ouvidoria e e-SIC (TR 4.33.22–23; Lei 13.460 art. 16; LAI art. 11)
        Route::post('processos/{id}/prorrogar-prazo', [ProcessoController::class, 'prorrogarPrazo'])->whereNumber('id')->name('processos.prorrogar-prazo');
        Route::post('processos/{id}/resolver-ouvidoria', [ProcessoController::class, 'resolverOuvidoria'])->whereNumber('id')->name('processos.resolver-ouvidoria');
        // Localização, avaliação no balcão e reabertura (TR 4.32.21, 4.32.25–27, 4.33.9)
        Route::post('processos/{id}/localizacao', [\App\Http\Controllers\Flow\MapaAvaliacaoController::class, 'localizacao'])->whereNumber('id')->name('processos.localizacao');
    // Recebimento de documento físico com comprovante (TR 4.32.28)
    Route::post('processos/{id}/recebimentos', [\App\Http\Controllers\RecebimentoFisicoController::class, 'store'])->whereNumber('id')->middleware('can:processo.tramitar')->name('processos.recebimentos.store');
    // Participantes do processo e o que o externo vê (TR 4.48.20, 4.48.30, 4.48.31)
    Route::controller(\App\Http\Controllers\ParticipanteController::class)->group(function () {
        Route::post('processos/{id}/participantes', 'salvar')->whereNumber('id')->name('processos.participantes.store');
        Route::put('processos/{id}/participantes/{participante}', 'salvar')->whereNumber(['id', 'participante'])->name('processos.participantes.update');
        Route::delete('processos/{id}/participantes/{participante}', 'remover')->whereNumber(['id', 'participante'])->name('processos.participantes.destroy');
        Route::post('processos/{id}/participantes/{participante}/convite', 'convidar')->whereNumber(['id', 'participante'])->middleware('throttle:20,1')->name('processos.participantes.convite');
        Route::post('processos/anexos/{anexo}/externo', 'visibilidade')->whereNumber('anexo')->name('processos.anexos.externo');
        Route::post('processos/{id}/consulta-link', [\App\Http\Controllers\ConsultaPublicaController::class, 'link'])->whereNumber('id')->name('processos.consulta-link');
    });
    // App offline da fiscalização (TR 4.52): tela do fiscal, sincronização e relatório da vistoria
    Route::controller(\App\Http\Controllers\AppFiscalizacaoController::class)->prefix('fiscal')->name('fiscal.')->group(function () {
        Route::get('/', 'app')->name('app');
        Route::get('api/sessao', 'sessao')->name('sessao');
        Route::get('api/vistorias', 'minhas')->name('minhas');
        Route::post('api/vistorias', 'sincronizar')->middleware('throttle:60,1')->name('sincronizar');
        Route::get('vistorias/{id}/pdf', 'pdf')->whereNumber('id')->name('pdf');
        Route::get('vistorias/{id}/fotos/{indice}', 'foto')->whereNumber(['id', 'indice'])->name('foto');
    });
    // Fiscalização de obras e posturas (TR 4.49)
    Route::controller(\App\Http\Controllers\FiscalizacaoController::class)->group(function () {
        Route::post('processos/{id}/fiscalizacao-interna', 'gerar')->whereNumber('id')->name('processos.fiscalizacao-interna');
        Route::post('processos/{id}/diligencias', 'diligencia')->whereNumber('id')->name('processos.diligencias');
        Route::post('processos/anexos/{anexo}/copia', 'enviarCopia')->whereNumber('anexo')->middleware('throttle:20,1')->name('processos.anexos.copia');
    });
    // Sistema tributário no processo (TR 4.48.22, 4.48.42, 4.49)
    Route::controller(\App\Http\Controllers\ProcessoTributarioController::class)->prefix('processos/{id}/tributario')->whereNumber('id')->name('processos.tributario.')->group(function () {
        Route::post('imovel', 'imovel')->name('imovel');
        Route::post('guias', 'guia')->name('guia');
        Route::post('documentos', 'documento')->name('documento');
        Route::post('fiscalizacao', 'fiscalizacao')->name('fiscalizacao');
        Route::post('manual', 'manual')->name('manual');
        Route::post('{vinculo}/atualizar', 'atualizar')->whereNumber('vinculo')->name('atualizar');
        Route::delete('{vinculo}', 'remover')->whereNumber('vinculo')->name('remover');
    });
    Route::get('processos/{id}/folha-rosto', [\App\Http\Controllers\ProcessoController::class, 'folhaDeRosto'])->whereNumber('id')->middleware('can:processo.visualizar')->name('processos.folha-rosto');
    Route::get('processos/{id}/recebimentos/{recebimento}/comprovante', [\App\Http\Controllers\RecebimentoFisicoController::class, 'comprovante'])->whereNumber(['id', 'recebimento'])->middleware('can:processo.visualizar')->name('processos.recebimentos.comprovante');
        Route::post('processos/{id}/avaliacao', [\App\Http\Controllers\Flow\MapaAvaliacaoController::class, 'avaliar'])->whereNumber('id')->name('processos.avaliacao');
        Route::post('processos/{id}/reabrir', [\App\Http\Controllers\Flow\MapaAvaliacaoController::class, 'reabrir'])->whereNumber('id')->name('processos.reabrir');
        // Prazos e atividades lançados à mão (TR 4.13, 4.32.16)
        Route::post('processos/{id}/prazos', [\App\Http\Controllers\PrazoController::class, 'store'])->whereNumber('id')->name('processos.prazos.store');
        Route::put('prazos/{id}', [\App\Http\Controllers\PrazoController::class, 'update'])->whereNumber('id')->name('prazos.update');
        Route::post('prazos/{id}/concluir', [\App\Http\Controllers\PrazoController::class, 'concluir'])->whereNumber('id')->name('prazos.concluir');
        Route::delete('prazos/{id}', [\App\Http\Controllers\PrazoController::class, 'destroy'])->whereNumber('id')->name('prazos.destroy');
        Route::post('processos/{id}/arquivar-no-ged', [ProcessoController::class, 'arquivarNoGed'])->name('processos.arquivar-no-ged');
        Route::post('processos/anexos/{anexo}/enviar-assinatura', [ProcessoController::class, 'enviarAnexoParaAssinatura'])
            ->name('processos.anexos.enviar-assinatura');

        // Tramitacoes
        Route::post('tramitacoes/{id}/receber', [TramitacaoController::class, 'receber'])->name('tramitacoes.receber');
        Route::post('tramitacoes/{id}/despachar', [TramitacaoController::class, 'despachar'])->name('tramitacoes.despachar');
        Route::post('tramitacoes/{id}/devolver', [TramitacaoController::class, 'devolver'])->name('tramitacoes.devolver');
        Route::post('tramitacoes/{id}/cuidados', [TramitacaoController::class, 'aosCuidados'])->name('tramitacoes.cuidados');
        Route::post('tramitacoes/{id}/resolver', [TramitacaoController::class, 'resolver'])->name('tramitacoes.resolver');
        Route::post('tramitacoes/{id}/estagio', [TramitacaoController::class, 'estagio'])->name('tramitacoes.estagio');
        Route::post('tramitacoes/{id}/ciente', [TramitacaoController::class, 'ciente'])->name('tramitacoes.ciente');
        Route::post('tramitacoes/{id}/comentar', [TramitacaoController::class, 'comentar'])->name('tramitacoes.comentar');
        Route::post('tramitacoes/{id}/anexar', [TramitacaoController::class, 'anexar'])->name('tramitacoes.anexar');
    });

    // Assinaturas
    Route::get('assinaturas', [AssinaturaController::class, 'index'])->name('assinaturas');
    Route::post('assinaturas/classificar-arquivar', [AssinaturaController::class, 'classificarArquivar'])->name('assinaturas.classificar-arquivar');
    Route::post('documentos/{id}/solicitar-assinatura', [AssinaturaController::class, 'solicitar'])->name('assinaturas.solicitar')->middleware('can:assinatura.solicitar');
    Route::post('assinaturas/solicitar-lote', [AssinaturaController::class, 'solicitarLote'])->name('assinaturas.solicitar-lote')->middleware('can:assinatura.solicitar');
    Route::post('assinaturas/{id}/assinar', [AssinaturaController::class, 'assinar'])->name('assinaturas.assinar');
    Route::post('assinaturas/{id}/assinar-icp', [AssinaturaController::class, 'assinarIcp'])->name('assinaturas.assinar-icp');
    Route::post('assinaturas/{id}/preparar-icp-a3', [AssinaturaController::class, 'prepararIcpA3'])->name('assinaturas.preparar-icp-a3');
    Route::post('assinaturas/{id}/finalizar-icp-a3', [AssinaturaController::class, 'finalizarIcpA3'])->name('assinaturas.finalizar-icp-a3');
    Route::get('assinaturas/{id}/download-assinado', [AssinaturaController::class, 'downloadAssinado'])->name('assinaturas.download-assinado');
    Route::post('assinaturas/{id}/recusar', [AssinaturaController::class, 'recusar'])->name('assinaturas.recusar');
    Route::post('assinaturas/{id}/reenviar-convite', [AssinaturaController::class, 'reenviarConvite'])->name('assinaturas.reenviar-convite');
    Route::post('assinaturas/assinar-lote', [AssinaturaController::class, 'assinarLote'])->name('assinaturas.assinar-lote');
    Route::post('assinaturas/{id}/simular-restantes', [AssinaturaController::class, 'simularAssinaturasRestantes'])
        ->name('assinaturas.simular-restantes');
    Route::get('assinaturas/{id}/manifesto', [AssinaturaController::class, 'manifesto'])->name('assinaturas.manifesto');

    // Meu perfil › Meus dados (e-mail, celular, foto, senha) e foto de qualquer servidor
    Route::prefix('perfil')->name('perfil.')->controller(\App\Http\Controllers\Perfil\PerfilController::class)->group(function () {
        Route::get('/', 'index')->name('dados');
        Route::post('/dados', 'atualizar')->middleware('throttle:20,1')->name('dados.salvar');
        Route::post('/senha', 'alterarSenha')->middleware('throttle:10,1')->name('senha');
        Route::post('/foto', 'enviarFoto')->middleware('throttle:20,1')->name('foto');
        Route::post('/foto/remover', 'removerFoto')->name('foto.remover');
        Route::get('/historico', 'historico')->name('historico');
        Route::get('/administradores', 'administradores')->name('administradores');
    });
    Route::get('usuarios/{id}/foto', [\App\Http\Controllers\Perfil\PerfilController::class, 'foto'])->whereNumber('id')->name('usuarios.foto');
    Route::get('perfil/contrassenhas', [\App\Http\Controllers\Perfil\ContrassenhasController::class, 'index'])->name('perfil.contrassenhas');
    Route::post('perfil/contrassenhas', [\App\Http\Controllers\Perfil\ContrassenhasController::class, 'store'])->middleware('throttle:20,1')->name('perfil.contrassenhas.gerar');
    Route::post('perfil/contrassenhas/{id}/cancelar', [\App\Http\Controllers\Perfil\ContrassenhasController::class, 'cancelar'])->whereNumber('id')->name('perfil.contrassenhas.cancelar');
    Route::get('perfil/equipe', [\App\Http\Controllers\Perfil\EquipeController::class, 'index'])->name('perfil.equipe');
    Route::post('perfil/equipe/{id}/reativar', [\App\Http\Controllers\Perfil\EquipeController::class, 'reativar'])->whereNumber('id')->name('perfil.equipe.reativar');
    Route::get('perfil/preferencias', [\App\Http\Controllers\Perfil\PreferenciasController::class, 'index'])->name('perfil.preferencias');
    Route::post('perfil/preferencias', [\App\Http\Controllers\Perfil\PreferenciasController::class, 'salvar'])->name('perfil.preferencias.salvar');
    Route::get('perfil/ausencias', [\App\Http\Controllers\Perfil\AusenciasController::class, 'index'])->name('perfil.ausencias');
    Route::post('perfil/ausencias', [\App\Http\Controllers\Perfil\AusenciasController::class, 'store'])->name('perfil.ausencias.salvar');
    Route::delete('perfil/ausencias/{id}', [\App\Http\Controllers\Perfil\AusenciasController::class, 'destroy'])->whereNumber('id')->name('perfil.ausencias.remover');

    // Meu perfil › Segurança: verificação em duas etapas
    Route::prefix('perfil/seguranca')->name('perfil.seguranca')->controller(\App\Http\Controllers\Perfil\SegurancaController::class)->group(function () {
        Route::get('/', 'index');
        Route::post('/dois-fatores/iniciar', 'iniciar')->name('.iniciar');
        Route::post('/dois-fatores/ativar', 'ativar')->middleware('throttle:10,1')->name('.ativar');
        Route::post('/dois-fatores/desativar', 'desativar')->middleware('throttle:10,1')->name('.desativar');
        Route::post('/dois-fatores/codigos', 'regerarCodigos')->middleware('throttle:10,1')->name('.codigos');
    });

    // Meus Certificados ICP-Brasil (perfil do usuario)
    Route::get('perfil/certificados', [CertificadoController::class, 'index'])->name('certificados.index');
    Route::post('perfil/certificados', [CertificadoController::class, 'store'])->name('certificados.store');
    Route::post('perfil/certificados/{id}/inativar', [CertificadoController::class, 'inativar'])->name('certificados.inativar');
    Route::post('perfil/certificados/{id}/reativar', [CertificadoController::class, 'reativar'])->name('certificados.reativar');

    // Notificacoes
    Route::get('notificacoes', [NotificacaoController::class, 'index'])->name('notificacoes');
    Route::get('notificacoes/recentes', [NotificacaoController::class, 'recentes'])->name('notificacoes.recentes');
    Route::post('notificacoes/marcar-todas', [NotificacaoController::class, 'marcarTodas'])->name('notificacoes.marcar-todas');
    Route::get('notificacoes/{id}/abrir', [NotificacaoController::class, 'abrir'])->name('notificacoes.abrir');
    Route::post('notificacoes/{id}/lida', [NotificacaoController::class, 'marcarLida'])->name('notificacoes.lida');
});
