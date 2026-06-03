<?php

use App\Http\Controllers\Landlord\LandlordLoginController;
use App\Http\Controllers\Landlord\LandlordTenantSSOController;
use App\Http\Controllers\Landlord\SuperAdminTenantLinkController;
use App\Http\Controllers\Landlord\TenantsController as LandlordTenantsController;
use Illuminate\Support\Facades\Route;

/**
 * Rotas Landlord (super-admin) — login + CRUD de tenants.
 *
 * Incluído por bootstrap/app.php com:
 *   middleware('web') + prefix('landlord') + name('landlord.')
 *
 * Acesso transversal (sem subdomain de tenant): www.gpe.com.br / admin.gpe.com.br
 * O ResolveTenant deixa passar nesses hosts. Autorização:
 *   - Login/logout: públicas (sem middleware).
 *   - Demais rotas: middleware 'super-admin' (EnsureSuperAdmin) — verifica
 *     session('super_admin_id') a cada request e revalida active=true contra
 *     o banco landlord.
 */

// ─── Login do super-admin (públicas) ──────────────────────────────────────
Route::get('login', [LandlordLoginController::class, 'show'])->name('landlord.login');
Route::post('login', [LandlordLoginController::class, 'store'])->name('login.store');
Route::post('logout', [LandlordLoginController::class, 'destroy'])->name('logout');

// ─── Tenants CRUD — protegidas por EnsureSuperAdmin ───────────────────────
Route::middleware('super-admin')->group(function () {
    Route::get('tenants', [LandlordTenantsController::class, 'index'])->name('tenants.index');
    Route::post('tenants', [LandlordTenantsController::class, 'store'])->name('tenants.store');
    Route::get('tenants/{id}', [LandlordTenantsController::class, 'show'])->name('tenants.show')->where('id', '[0-9]+');
    Route::put('tenants/{id}', [LandlordTenantsController::class, 'update'])->name('tenants.update')->where('id', '[0-9]+');
    Route::delete('tenants/{id}', [LandlordTenantsController::class, 'destroy'])->name('tenants.destroy')->where('id', '[0-9]+');
    Route::post('tenants/{id}/toggle', [LandlordTenantsController::class, 'toggle'])->name('tenants.toggle')->where('id', '[0-9]+');
    Route::get('tenants/{id}/test', [LandlordTenantsController::class, 'testConnection'])->name('tenants.test')->where('id', '[0-9]+');

    // SSO emit: entra como o usuário vinculado no tenant alvo.
    // Use {link?} para forçar um vínculo específico (quando há múltiplos no mesmo tenant).
    Route::post('sso/{tenant}/{link?}', [LandlordTenantSSOController::class, 'create'])
        ->name('sso.emit')
        ->where('tenant', '[0-9]+')->where('link', '[0-9]+');

    // ─── Vínculos super-admin × usuário de tenant ────────────────────────
    Route::get('links', [SuperAdminTenantLinkController::class, 'index'])->name('links.index');
    Route::post('links', [SuperAdminTenantLinkController::class, 'store'])->name('links.store');
    Route::get('links/{id}', [SuperAdminTenantLinkController::class, 'show'])->name('links.show')->where('id', '[0-9]+');
    Route::put('links/{id}', [SuperAdminTenantLinkController::class, 'update'])->name('links.update')->where('id', '[0-9]+');
    Route::delete('links/{id}', [SuperAdminTenantLinkController::class, 'destroy'])->name('links.destroy')->where('id', '[0-9]+');
});
