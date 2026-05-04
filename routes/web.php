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

// Rastreio de oficio (publico, sem auth)
Route::get('oficios/rastrear/{token}', [OficioController::class, 'rastrear'])->name('oficios.rastrear');

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
        Route::get('documentos/{numero}', [\App\Http\Controllers\Api\IntegracaoDocumentoController::class, 'show'])
            ->where('numero', '.+')
            ->name('api.integracoes.documentos.show');
        Route::post('documentos/{numero}/versao', [\App\Http\Controllers\Api\IntegracaoDocumentoController::class, 'novaVersao'])
            ->where('numero', '.+')
            ->name('api.integracoes.documentos.versao');
        Route::post('documentos/{numero}/reenviar-webhook', [\App\Http\Controllers\Api\IntegracaoDocumentoController::class, 'reenviarWebhook'])
            ->where('numero', '.+')
            ->name('api.integracoes.documentos.reenviar-webhook');
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

// Verificacao publica de documento (sem auth)
Route::get('verificar/{token}', [VerificacaoController::class, 'verificar'])->name('verificar');

// Validacao publica de assinatura ICP-Brasil em PDF (sem auth)
Route::get('validar-assinatura', [VerificacaoController::class, 'validarPdfPagina'])->name('validar-assinatura');
Route::post('validar-assinatura', [VerificacaoController::class, 'validarPdf'])->name('validar-assinatura.upload');

// Guest
Route::middleware('guest')->group(function () {
    Route::get('/login', [LoginController::class, 'index'])->name('login');
    Route::post('/login', [LoginController::class, 'store']);
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
    Route::get('/dashboard', DashboardController::class)->name('dashboard');

    // Documentos
    Route::resource('documentos', DocumentoController::class)->except(['create', 'edit']);
    Route::post('documentos/mover-pasta', [DocumentoController::class, 'moverPasta'])->name('documentos.mover-pasta');
    Route::get('documentos/{id}/download', [DocumentoController::class, 'download'])->name('documentos.download');
    Route::get('documentos/{id}/preview', [DocumentoController::class, 'preview'])->name('documentos.preview');
    Route::post('documentos/{id}/favorito', [DocumentoController::class, 'toggleFavorito'])->name('documentos.favorito');
    Route::post('documentos/{id}/status', [DocumentoController::class, 'alterarStatus'])->name('documentos.status');

    // Pastas / Repositorio
    Route::get('repositorio', [PastaController::class, 'index'])->name('repositorio');
    Route::get('pastas/tree', [PastaController::class, 'tree'])->name('pastas.tree');
    Route::resource('pastas', PastaController::class)->except(['index', 'show']);
    Route::post('pastas/{id}/inativar', [PastaController::class, 'inativar'])->name('pastas.inativar');
    Route::post('pastas/{id}/reativar', [PastaController::class, 'reativar'])->name('pastas.reativar');

    // Captura
    Route::get('capturar', [CapturaController::class, 'index'])->name('capturar');
    Route::post('capturar/upload', [CapturaController::class, 'upload'])->name('capturar.upload');

    // Fluxos
    Route::resource('fluxos', FluxoController::class);
    Route::post('fluxos/{id}/iniciar', [FluxoController::class, 'iniciar'])->name('fluxos.iniciar');

    // Busca
    Route::get('busca', [BuscaController::class, 'index'])->name('busca');
    Route::post('busca/salvar', [BuscaController::class, 'salvar'])->name('busca.salvar');
    Route::delete('busca/salvar/{id}', [BuscaController::class, 'destroy'])->name('busca.destroy');

    // Configuracoes — modulo dedicado para usuarios, perfis, UGs e organograma
    Route::prefix('configuracoes')->name('configuracoes.')->group(function () {
        // Visao geral do modulo
        Route::get('/', fn () => Inertia\Inertia::render('Configuracao/Index'))->name('index');

        // Usuarios e perfis (movidos de /admin)
        Route::resource('usuarios', UsuarioController::class)->except(['show']);
        Route::resource('perfis', RoleController::class)->parameters(['perfis' => 'role'])->except(['create', 'edit', 'show']);

        // Unidades Gestoras + organograma
        Route::resource('ugs', \App\Http\Controllers\Configuracao\UgController::class)->except(['show']);
        Route::post('ugs/{id}/toggle-ativo', [\App\Http\Controllers\Configuracao\UgController::class, 'toggleAtivo'])->name('ugs.toggle-ativo');
        Route::get('ugs/{id}/brasao', [\App\Http\Controllers\Configuracao\UgController::class, 'brasao'])->name('ug.brasao');
        Route::get('ugs/{id}/banner', [\App\Http\Controllers\Configuracao\UgController::class, 'banner'])->name('ug.banner');

        // Banners do Portal Cidadao (carrossel)
        Route::get('ugs/{ug}/banners',                [\App\Http\Controllers\Configuracao\BannerPortalController::class, 'index'])->name('ug.banners.index');
        Route::post('ugs/{ug}/banners',               [\App\Http\Controllers\Configuracao\BannerPortalController::class, 'store'])->name('ug.banners.store');
        Route::put('ugs/{ug}/banners/{banner}',       [\App\Http\Controllers\Configuracao\BannerPortalController::class, 'update'])->name('ug.banners.update');
        Route::delete('ugs/{ug}/banners/{banner}',    [\App\Http\Controllers\Configuracao\BannerPortalController::class, 'destroy'])->name('ug.banners.destroy');
        Route::post('ugs/{ug}/banners/{banner}/move/{direcao}', [\App\Http\Controllers\Configuracao\BannerPortalController::class, 'move'])->name('ug.banners.move');
        Route::get('ugs/{ug}/banners/{banner}/imagem', [\App\Http\Controllers\Configuracao\BannerPortalController::class, 'imagem'])->name('ug.banners.imagem');

        Route::get('ugs/{ug}/organograma', [\App\Http\Controllers\Configuracao\UgOrganogramaController::class, 'show'])->name('ugs.organograma');
        Route::post('ugs/{ug}/organograma/labels', [\App\Http\Controllers\Configuracao\UgOrganogramaController::class, 'updateLabels'])->name('ugs.organograma.labels');

        // Tela dedicada de cadastro de no
        Route::get('ugs/{ug}/organograma/nodes/create', [\App\Http\Controllers\Configuracao\UgOrganogramaController::class, 'createNode'])->name('ugs.organograma.nodes.create');
        Route::get('ugs/{ug}/organograma/nodes/{node}/edit', [\App\Http\Controllers\Configuracao\UgOrganogramaController::class, 'editNode'])->name('ugs.organograma.nodes.edit');

        Route::post('ugs/{ug}/organograma/nodes', [\App\Http\Controllers\Configuracao\UgOrganogramaController::class, 'storeNode'])->name('ugs.organograma.nodes.store');
        Route::put('ugs/{ug}/organograma/nodes/{node}', [\App\Http\Controllers\Configuracao\UgOrganogramaController::class, 'updateNode'])->name('ugs.organograma.nodes.update');
        Route::delete('ugs/{ug}/organograma/nodes/{node}', [\App\Http\Controllers\Configuracao\UgOrganogramaController::class, 'destroyNode'])->name('ugs.organograma.nodes.destroy');
        Route::post('ugs/{ug}/organograma/nodes/{node}/toggle-ativo', [\App\Http\Controllers\Configuracao\UgOrganogramaController::class, 'toggleAtivoNode'])->name('ugs.organograma.nodes.toggle-ativo');

        // Sistemas integrados (API tokens para sistemas externos)
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

        // Solicitacoes do Portal Cidadao (atendimento pelos servidores)
        Route::prefix('solicitacoes-portal')->name('solicitacoes-portal.')->group(function () {
            Route::get('/',                       [\App\Http\Controllers\Configuracao\PortalSolicitacoesController::class, 'index'])->name('index');
            Route::get('/{id}',                   [\App\Http\Controllers\Configuracao\PortalSolicitacoesController::class, 'show'])->name('show');
            Route::post('/{id}/status',           [\App\Http\Controllers\Configuracao\PortalSolicitacoesController::class, 'alterarStatus'])->name('status');
            Route::post('/{id}/comentar',         [\App\Http\Controllers\Configuracao\PortalSolicitacoesController::class, 'comentar'])->name('comentar');
        });

        // Carta de Servicos (admin) — gestor da UG mantem catalogo publicado no /portal
        Route::prefix('carta-servicos')->name('carta-servicos.')->group(function () {
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
        Route::resource('tipos-documentais', TipoDocumentalController::class)->except(['create', 'edit', 'show']);
        Route::post('tipos-documentais/{id}/toggle-ativo', [TipoDocumentalController::class, 'toggleAtivo'])->name('tipos-documentais.toggle-ativo');
        Route::resource('tipos-processo', TipoProcessoController::class)->except(['create', 'edit', 'show']);
        Route::post('tipos-processo/{id}/toggle-ativo', [TipoProcessoController::class, 'toggleAtivo'])->name('tipos-processo.toggle-ativo');

        // Redirects das URLs antigas para o modulo Configuracoes
        Route::redirect('usuarios', '/configuracoes/usuarios');
        Route::redirect('roles', '/configuracoes/perfis');
    });

    // Memorandos
    Route::resource('memorandos', MemorandoController::class)->only(['index', 'create', 'store', 'show']);
    Route::post('memorandos/{id}/responder', [MemorandoController::class, 'responder'])->name('memorandos.responder');
    Route::post('memorandos/{id}/arquivar', [MemorandoController::class, 'arquivar'])->name('memorandos.arquivar');
    Route::post('memorandos/{id}/receber', [MemorandoController::class, 'receber'])->name('memorandos.receber');
    Route::post('memorandos/{id}/tramitar', [MemorandoController::class, 'tramitar'])->name('memorandos.tramitar');
    Route::post('memorandos/{id}/arquivar-no-ged', [MemorandoController::class, 'arquivarNoGed'])->name('memorandos.arquivar-no-ged');
    Route::get('memorandos/{id}/pdf', [MemorandoController::class, 'downloadPdf'])->name('memorandos.pdf');

    // Oficios
    Route::resource('oficios', OficioController::class)->only(['index', 'create', 'store', 'show']);
    Route::post('oficios/{id}/responder', [OficioController::class, 'responder'])->name('oficios.responder');
    Route::post('oficios/{id}/arquivar', [OficioController::class, 'arquivar'])->name('oficios.arquivar');
    Route::post('oficios/{id}/arquivar-no-ged', [OficioController::class, 'arquivarNoGed'])->name('oficios.arquivar-no-ged');
    Route::get('oficios/{id}/pdf', [OficioController::class, 'downloadPdf'])->name('oficios.pdf');

    // Circulares
    Route::resource('circulares', CircularController::class)->only(['index', 'create', 'store', 'show']);
    Route::post('circulares/{id}/arquivar', [CircularController::class, 'arquivar'])->name('circulares.arquivar');
    Route::post('circulares/{id}/arquivar-no-ged', [CircularController::class, 'arquivarNoGed'])->name('circulares.arquivar-no-ged');
    Route::get('circulares/{id}/pdf', [CircularController::class, 'downloadPdf'])->name('circulares.pdf');

    // GPE Flow — Inbox unificada por ESTADO
    Route::prefix('flow')->name('flow.')->group(function () {
        // Entrada (preciso agir)
        Route::get('inbox-pessoal',         [\App\Http\Controllers\Flow\InboxController::class, 'pessoal'])->name('inbox-pessoal');
        Route::get('inbox-setor',           [\App\Http\Controllers\Flow\InboxController::class, 'setor'])->name('inbox-setor');
        Route::get('aguardando-assinatura', [\App\Http\Controllers\Flow\InboxController::class, 'aguardandoAssinatura'])->name('aguardando-assinatura');

        // Em andamento
        Route::get('em-tramitacao', [\App\Http\Controllers\Flow\InboxController::class, 'emTramitacao'])->name('em-tramitacao');

        // Concluidos
        Route::get('concluidos',    [\App\Http\Controllers\Flow\InboxController::class, 'concluidos'])->name('concluidos');

        // Privado
        Route::get('saida',         [\App\Http\Controllers\Flow\InboxController::class, 'saida'])->name('saida');
        Route::get('rascunhos',     [\App\Http\Controllers\Flow\InboxController::class, 'rascunhos'])->name('rascunhos');

        // Compat: URLs antigas
        Route::redirect('encaminhados', '/flow/saida');
        Route::redirect('tramitacao',   '/flow/em-tramitacao');
        Route::redirect('arquivados',   '/flow/concluidos');
    });

    // Processos (GEPSP)
    Route::get('processos/dashboard', [ProcessoDashboardController::class, '__invoke'])->name('processos.dashboard');
    Route::get('processos/inbox', [TramitacaoController::class, 'inbox'])->name('processos.inbox');
    Route::resource('processos', ProcessoController::class)->except(['edit', 'update', 'destroy']);
    Route::post('processos/{id}/concluir', [ProcessoController::class, 'concluir'])->name('processos.concluir');
    Route::post('processos/{id}/cancelar', [ProcessoController::class, 'cancelar'])->name('processos.cancelar');
    Route::post('processos/{id}/arquivar-no-ged', [ProcessoController::class, 'arquivarNoGed'])->name('processos.arquivar-no-ged');

    // Tramitacoes
    Route::post('tramitacoes/{id}/receber', [TramitacaoController::class, 'receber'])->name('tramitacoes.receber');
    Route::post('tramitacoes/{id}/despachar', [TramitacaoController::class, 'despachar'])->name('tramitacoes.despachar');
    Route::post('tramitacoes/{id}/devolver', [TramitacaoController::class, 'devolver'])->name('tramitacoes.devolver');
    Route::post('tramitacoes/{id}/comentar', [TramitacaoController::class, 'comentar'])->name('tramitacoes.comentar');
    Route::post('tramitacoes/{id}/anexar', [TramitacaoController::class, 'anexar'])->name('tramitacoes.anexar');

    // Assinaturas
    Route::get('assinaturas', [AssinaturaController::class, 'index'])->name('assinaturas');
    Route::post('assinaturas/classificar-arquivar', [AssinaturaController::class, 'classificarArquivar'])->name('assinaturas.classificar-arquivar');
    Route::post('documentos/{id}/solicitar-assinatura', [AssinaturaController::class, 'solicitar'])->name('assinaturas.solicitar');
    Route::post('assinaturas/solicitar-lote', [AssinaturaController::class, 'solicitarLote'])->name('assinaturas.solicitar-lote');
    Route::post('assinaturas/{id}/assinar', [AssinaturaController::class, 'assinar'])->name('assinaturas.assinar');
    Route::post('assinaturas/{id}/assinar-icp', [AssinaturaController::class, 'assinarIcp'])->name('assinaturas.assinar-icp');
    Route::post('assinaturas/{id}/preparar-icp-a3', [AssinaturaController::class, 'prepararIcpA3'])->name('assinaturas.preparar-icp-a3');
    Route::post('assinaturas/{id}/finalizar-icp-a3', [AssinaturaController::class, 'finalizarIcpA3'])->name('assinaturas.finalizar-icp-a3');
    Route::get('assinaturas/{id}/download-assinado', [AssinaturaController::class, 'downloadAssinado'])->name('assinaturas.download-assinado');
    Route::post('assinaturas/{id}/recusar', [AssinaturaController::class, 'recusar'])->name('assinaturas.recusar');
    Route::post('assinaturas/{id}/simular-restantes', [AssinaturaController::class, 'simularAssinaturasRestantes'])
        ->name('assinaturas.simular-restantes');
    Route::get('assinaturas/{id}/manifesto', [AssinaturaController::class, 'manifesto'])->name('assinaturas.manifesto');

    // Meus Certificados ICP-Brasil (perfil do usuario)
    Route::get('perfil/certificados', [CertificadoController::class, 'index'])->name('certificados.index');
    Route::post('perfil/certificados', [CertificadoController::class, 'store'])->name('certificados.store');
    Route::post('perfil/certificados/{id}/inativar', [CertificadoController::class, 'inativar'])->name('certificados.inativar');
    Route::post('perfil/certificados/{id}/reativar', [CertificadoController::class, 'reativar'])->name('certificados.reativar');

    // Notificacoes
    Route::get('notificacoes', [NotificacaoController::class, 'index'])->name('notificacoes');
    Route::post('notificacoes/{id}/lida', [NotificacaoController::class, 'marcarLida'])->name('notificacoes.lida');
});
