<?php

use App\Http\Controllers\Admin\RoleController;
use App\Http\Controllers\Admin\TipoDocumentalController;
use App\Http\Controllers\Admin\UsuarioController;
use App\Http\Controllers\Admin\TipoProcessoController;
use App\Http\Controllers\AssinaturaController;
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
use App\Http\Controllers\ModulosController;
use App\Http\Controllers\NotificacaoController;
use App\Http\Controllers\PastaController;
use Illuminate\Support\Facades\Route;

// Verificacao publica de documento (sem auth)
Route::get('verificar/{token}', [VerificacaoController::class, 'verificar'])->name('verificar');

// Guest
Route::middleware('guest')->group(function () {
    Route::get('/login', [LoginController::class, 'index'])->name('login');
    Route::post('/login', [LoginController::class, 'store']);
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

    // Admin
    Route::prefix('admin')->name('admin.')->group(function () {
        Route::resource('usuarios', UsuarioController::class)->except(['create', 'edit', 'show']);
        Route::resource('roles', RoleController::class)->except(['create', 'edit', 'show']);
        Route::resource('tipos-documentais', TipoDocumentalController::class)->except(['create', 'edit', 'show']);
        Route::post('tipos-documentais/{id}/toggle-ativo', [TipoDocumentalController::class, 'toggleAtivo'])->name('tipos-documentais.toggle-ativo');
        Route::resource('tipos-processo', TipoProcessoController::class)->except(['create', 'edit', 'show']);
        Route::post('tipos-processo/{id}/toggle-ativo', [TipoProcessoController::class, 'toggleAtivo'])->name('tipos-processo.toggle-ativo');
    });

    // Processos (GEPSP)
    Route::get('processos/dashboard', [ProcessoDashboardController::class, '__invoke'])->name('processos.dashboard');
    Route::get('processos/inbox', [TramitacaoController::class, 'inbox'])->name('processos.inbox');
    Route::resource('processos', ProcessoController::class)->except(['edit', 'update', 'destroy']);
    Route::post('processos/{id}/concluir', [ProcessoController::class, 'concluir'])->name('processos.concluir');
    Route::post('processos/{id}/cancelar', [ProcessoController::class, 'cancelar'])->name('processos.cancelar');

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
    Route::post('assinaturas/{id}/recusar', [AssinaturaController::class, 'recusar'])->name('assinaturas.recusar');
    Route::get('assinaturas/{id}/manifesto', [AssinaturaController::class, 'manifesto'])->name('assinaturas.manifesto');

    // Notificacoes
    Route::get('notificacoes', [NotificacaoController::class, 'index'])->name('notificacoes');
    Route::post('notificacoes/{id}/lida', [NotificacaoController::class, 'marcarLida'])->name('notificacoes.lida');
});
