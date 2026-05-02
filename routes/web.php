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
    ])
    ->middleware(['sistema.api'])
    ->group(function () {
        Route::post('documentos', [\App\Http\Controllers\Api\IntegracaoDocumentoController::class, 'store'])
            ->name('api.integracoes.documentos.store');
        Route::get('documentos/{numero}', [\App\Http\Controllers\Api\IntegracaoDocumentoController::class, 'show'])
            ->where('numero', '.*')
            ->name('api.integracoes.documentos.show');
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
    Route::post('documentos/{id}/solicitar-assinatura', [AssinaturaController::class, 'solicitar'])->name('assinaturas.solicitar');
    Route::post('assinaturas/solicitar-lote', [AssinaturaController::class, 'solicitarLote'])->name('assinaturas.solicitar-lote');
    Route::post('assinaturas/{id}/assinar', [AssinaturaController::class, 'assinar'])->name('assinaturas.assinar');
    Route::post('assinaturas/{id}/assinar-icp', [AssinaturaController::class, 'assinarIcp'])->name('assinaturas.assinar-icp');
    Route::post('assinaturas/{id}/preparar-icp-a3', [AssinaturaController::class, 'prepararIcpA3'])->name('assinaturas.preparar-icp-a3');
    Route::post('assinaturas/{id}/finalizar-icp-a3', [AssinaturaController::class, 'finalizarIcpA3'])->name('assinaturas.finalizar-icp-a3');
    Route::get('assinaturas/{id}/download-assinado', [AssinaturaController::class, 'downloadAssinado'])->name('assinaturas.download-assinado');
    Route::post('assinaturas/{id}/recusar', [AssinaturaController::class, 'recusar'])->name('assinaturas.recusar');
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
