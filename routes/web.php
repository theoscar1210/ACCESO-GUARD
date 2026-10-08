<?php

use App\Http\Controllers\Admin\AnnouncementController as AdminAnnouncementController;
use App\Http\Controllers\Admin\DashboardController as AdminDashboardController;
use App\Http\Controllers\Admin\EntryController as AdminEntryController;
use App\Http\Controllers\Admin\FamilyMemberController;
use App\Http\Controllers\Admin\PropertyController;
use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\Admin\WorkSettingsController;
use App\Http\Controllers\AnnouncementController;
use App\Http\Controllers\CarnetController;
use App\Http\Controllers\Settings\AvatarController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\MaterialExitController;
use App\Http\Controllers\Propietario\AuthorizationController as PropietarioAuthorizationController;
use App\Http\Controllers\Propietario\DashboardController as PropietarioDashboardController;
use App\Http\Controllers\Propietario\HistoryController as PropietarioHistoryController;
use App\Http\Controllers\PushSubscriptionController;
use App\Http\Controllers\Residente\AuthorizationController as ResidenteAuthorizationController;
use App\Http\Controllers\Residente\DashboardController as ResidenteDashboardController;
use App\Http\Controllers\Residente\HistoryController as ResidenteHistoryController;
use App\Http\Controllers\Vigilante\AuthorizationController as VigilanteAuthorizationController;
use App\Http\Controllers\Vigilante\DashboardController as VigDashboardController;
use App\Http\Controllers\Vigilante\EntryController;
use App\Http\Controllers\Vigilante\ExitController;
use App\Http\Controllers\Vigilante\ReportController;
use App\Http\Controllers\WorkController;
use Illuminate\Support\Facades\Route;

Route::inertia('/', 'Welcome')->name('home');

Route::middleware(['auth', 'verified'])->group(function () {

    Route::get('dashboard', [DashboardController::class, 'index'])->name('dashboard');

    // Carnet digital (propietario y residente)
    Route::get('carnet', [CarnetController::class, 'show'])->name('carnet');

    // Comunicados (propietario y residente)
    Route::get('announcements', [AnnouncementController::class, 'index'])->name('announcements.index');
    Route::post('announcements/{announcement}/read', [AnnouncementController::class, 'markRead'])->name('announcements.read');

    // Avatar / foto de perfil
    Route::post('settings/avatar', [AvatarController::class, 'update'])->name('settings.avatar.update');
    Route::delete('settings/avatar', [AvatarController::class, 'destroy'])->name('settings.avatar.destroy');

    // Push subscriptions (cualquier usuario autenticado)
    Route::post('push/subscribe', [PushSubscriptionController::class, 'store'])->name('push.subscribe');
    Route::delete('push/subscribe', [PushSubscriptionController::class, 'destroy'])->name('push.unsubscribe');

    // Administrador
    Route::middleware('role:Administrador|Superusuario')->prefix('admin')->name('admin.')->group(function () {
        Route::get('dashboard', [AdminDashboardController::class, 'index'])->name('dashboard');
        Route::resource('users', UserController::class)->only(['index', 'create', 'store', 'edit', 'update', 'destroy']);
        Route::get('entries', [AdminEntryController::class, 'index'])->name('entries.index');

        // Inmuebles
        Route::resource('properties', PropertyController::class)->only(['index', 'create', 'store', 'show', 'update', 'destroy']);
        Route::post('properties/{property}/assign-owner', [PropertyController::class, 'assignOwner'])->name('properties.assign-owner');
        Route::delete('properties/{property}/owners/{user}', [PropertyController::class, 'removeOwner'])->name('properties.remove-owner');
        Route::post('properties/{property}/assign-tenant', [PropertyController::class, 'assignTenant'])->name('properties.assign-tenant');
        Route::post('properties/{property}/end-rental', [PropertyController::class, 'endRental'])->name('properties.end-rental');

        // Núcleo familiar
        Route::post('family-members', [FamilyMemberController::class, 'store'])->name('family-members.store');
        Route::put('family-members/{familyMember}', [FamilyMemberController::class, 'update'])->name('family-members.update');
        Route::delete('family-members/{familyMember}', [FamilyMemberController::class, 'destroy'])->name('family-members.destroy');

        // Comunicados
        Route::get('announcements', [AdminAnnouncementController::class, 'index'])->name('announcements.index');
        Route::get('announcements/create', [AdminAnnouncementController::class, 'create'])->name('announcements.create');
        Route::post('announcements', [AdminAnnouncementController::class, 'store'])->name('announcements.store');

        // Configuración de obras: solo superusuario
        Route::middleware('role:Superusuario')->group(function () {
            Route::get('settings/works', [WorkSettingsController::class, 'edit'])->name('settings.works.edit');
            Route::put('settings/works', [WorkSettingsController::class, 'update'])->name('settings.works.update');
        });
    });

    // Obras (todos los roles; los permisos los decide WorkPermissions)
    Route::get('works', [WorkController::class, 'index'])->name('works.index');
    Route::get('works/create', [WorkController::class, 'create'])->name('works.create');
    Route::post('works', [WorkController::class, 'store'])->name('works.store');
    Route::get('works/{work}', [WorkController::class, 'show'])->name('works.show');
    Route::put('works/{work}', [WorkController::class, 'update'])->name('works.update');
    Route::post('works/{work}/decision', [WorkController::class, 'decide'])->name('works.decide');
    Route::post('works/{work}/workers', [WorkController::class, 'addWorker'])->name('works.workers.store');
    Route::delete('works/{work}/workers/{worker}', [WorkController::class, 'removeWorker'])->name('works.workers.destroy');
    Route::get('works/{work}/acta', [WorkController::class, 'acta'])->name('works.acta');
    Route::post('works/{work}/material-exits', [MaterialExitController::class, 'store'])->name('works.material-exits.store');
    Route::post('works/{work}/material-exits/{materialExit}/decision', [MaterialExitController::class, 'decide'])->name('works.material-exits.decide');

    // Vigilante
    Route::middleware('role:Vigilante|Superusuario')->prefix('vigilante')->name('vigilante.')->group(function () {
        Route::get('dashboard', [VigDashboardController::class, 'index'])->name('dashboard');
        Route::get('entries', [EntryController::class, 'index'])->name('entries.index');
        Route::get('entries/create', [EntryController::class, 'create'])->name('entries.create');
        Route::post('entries', [EntryController::class, 'store'])->name('entries.store');
        Route::get('entries/lookup', [EntryController::class, 'lookup'])->name('entries.lookup');
        Route::get('entries/lookup-plate', [EntryController::class, 'lookupByPlate'])->name('entries.lookup-plate');
        Route::get('exits', [ExitController::class, 'index'])->name('exits.index');
        Route::post('exits', [ExitController::class, 'store'])->name('exits.store');
        Route::get('exits/{entry}/tools', [ExitController::class, 'tools'])->name('exits.tools');
        Route::get('authorizations', [VigilanteAuthorizationController::class, 'index'])->name('authorizations.index');
        Route::get('reports', [ReportController::class, 'index'])->name('reports.index');
        Route::post('reports/export', [ReportController::class, 'export'])->name('reports.export');
    });

    // Propietario
    Route::middleware('role:Propietario')->prefix('propietario')->name('propietario.')->group(function () {
        Route::get('dashboard', [PropietarioDashboardController::class, 'index'])->name('dashboard');
        Route::get('authorizations', [PropietarioAuthorizationController::class, 'index'])->name('authorizations.index');
        Route::get('authorizations/create', [PropietarioAuthorizationController::class, 'create'])->name('authorizations.create');
        Route::post('authorizations', [PropietarioAuthorizationController::class, 'store'])->name('authorizations.store');
        Route::delete('authorizations/{authorization}', [PropietarioAuthorizationController::class, 'destroy'])->name('authorizations.destroy');
        Route::get('history', [PropietarioHistoryController::class, 'index'])->name('history.index');
    });

    // Residente
    Route::middleware('role:Residente')->prefix('residente')->name('residente.')->group(function () {
        Route::get('dashboard', [ResidenteDashboardController::class, 'index'])->name('dashboard');
        Route::get('authorizations', [ResidenteAuthorizationController::class, 'index'])->name('authorizations.index');
        Route::get('authorizations/create', [ResidenteAuthorizationController::class, 'create'])->name('authorizations.create');
        Route::post('authorizations', [ResidenteAuthorizationController::class, 'store'])->name('authorizations.store');
        Route::delete('authorizations/{authorization}', [ResidenteAuthorizationController::class, 'destroy'])->name('authorizations.destroy');
        Route::get('history', [ResidenteHistoryController::class, 'index'])->name('history.index');
    });
});

require __DIR__.'/settings.php';
