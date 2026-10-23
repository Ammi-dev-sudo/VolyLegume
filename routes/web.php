<?php

use App\Http\Controllers\ActivityController;
use App\Http\Controllers\Admin\AuditLogController as AdminAuditLogController;
use App\Http\Controllers\Admin\DashboardController as AdminDashboardController;
use App\Http\Controllers\Admin\FarmController as AdminFarmController;
use App\Http\Controllers\Admin\UserController as AdminUserController;
use App\Http\Controllers\AuditLogController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\LogoutController;
use App\Http\Controllers\Auth\RegisterController;
use App\Http\Controllers\CalendarController;
use App\Http\Controllers\CalendarGenerationController;
use App\Http\Controllers\CalendarStepController;
use App\Http\Controllers\CampaignController;
use App\Http\Controllers\CropController;
use App\Http\Controllers\CustomerController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\ExpenseController;
use App\Http\Controllers\FarmController;
use App\Http\Controllers\HarvestController;
use App\Http\Controllers\InputController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\ProduceLotController;
use App\Http\Controllers\ProductionUnitController;
use App\Http\Controllers\ResourceController;
use App\Http\Controllers\RevenueController;
use App\Http\Controllers\SaleController;
use App\Http\Controllers\StockMovementController;
use App\Http\Controllers\TeamController;
use App\Http\Controllers\TreatmentController;
use Illuminate\Support\Facades\Route;

Route::redirect('/', '/dashboard');

Route::middleware('guest')->group(function () {
    Route::get('login', [LoginController::class, 'create'])->name('login');
    Route::post('login', [LoginController::class, 'store'])->name('login.attempt');
    Route::get('register', [RegisterController::class, 'create'])->name('register');
    Route::post('register', [RegisterController::class, 'store'])->name('register.attempt');
});

Route::middleware('auth')->group(function () {
    Route::post('logout', [LogoutController::class, 'destroy'])->name('logout');

    Route::middleware('platform-admin')->prefix('admin')->name('admin.')->group(function () {
        Route::get('/', [AdminDashboardController::class, 'index'])->name('dashboard');
        Route::get('utilisateurs', [AdminUserController::class, 'index'])->name('users.index');
        Route::get('utilisateurs/creer', [AdminUserController::class, 'create'])->name('users.create');
        Route::post('utilisateurs', [AdminUserController::class, 'store'])->name('users.store');
        Route::get('utilisateurs/{user}', [AdminUserController::class, 'show'])->name('users.show');
        Route::patch('utilisateurs/{user}/statut', [AdminUserController::class, 'toggleActive'])->name('users.status');
        Route::patch('utilisateurs/{user}/administrateur', [AdminUserController::class, 'togglePlatformAdmin'])->name('users.platform-admin');
        Route::post('utilisateurs/{user}/rattachements', [AdminUserController::class, 'attachMembership'])->name('users.memberships.store');
        Route::put('utilisateurs/{user}/rattachements/{membership}', [AdminUserController::class, 'updateMembership'])->name('users.memberships.update');
        Route::get('exploitations', [AdminFarmController::class, 'index'])->name('farms.index');
        Route::get('exploitations/{farmId}', [AdminFarmController::class, 'show'])->name('farms.show');
        Route::patch('exploitations/{farmId}/statut', [AdminFarmController::class, 'toggleStatus'])->name('farms.status');
        Route::get('journal', [AdminAuditLogController::class, 'index'])->name('audit.index');
    });

    Route::get('dashboard', [DashboardController::class, 'index'])->name('dashboard');

    Route::get('ressources', [ResourceController::class, 'index'])->name('resources.index');

    Route::get('notifications', [NotificationController::class, 'index'])->name('notifications.index');
    Route::post('notifications/{id}/read', [NotificationController::class, 'markAsRead'])->name('notifications.read');
    Route::post('notifications/read-all', [NotificationController::class, 'markAllAsRead'])->name('notifications.readAll');

    Route::get('exploitations', [FarmController::class, 'index'])->name('farms.index');
    Route::get('exploitations/creer', [FarmController::class, 'create'])->name('farms.create');
    Route::post('exploitations', [FarmController::class, 'store'])->name('farms.store');
    Route::get('exploitations/{farm}/modifier', [FarmController::class, 'edit'])->name('farms.edit');
    Route::put('exploitations/{farm}', [FarmController::class, 'update'])->name('farms.update');
    Route::post('exploitations/{farm}/activer', [FarmController::class, 'switch'])->name('farms.switch');
});

Route::middleware(['auth', 'farm'])->group(function () {
    Route::get('agenda', [CalendarController::class, 'index'])->name('calendar.index');

    Route::get('journal', [AuditLogController::class, 'index'])->name('audit.index');

    Route::get('equipe', [TeamController::class, 'index'])->name('team.index');
    Route::post('equipe', [TeamController::class, 'store'])->name('team.store');
    Route::put('equipe/{farmUser}', [TeamController::class, 'update'])->name('team.update');
    Route::delete('equipe/{farmUser}', [TeamController::class, 'destroy'])->name('team.destroy');

    Route::get('parcelles', [ProductionUnitController::class, 'index'])->name('production-units.index');
    Route::get('parcelles/creer', [ProductionUnitController::class, 'create'])->name('production-units.create');
    Route::post('parcelles', [ProductionUnitController::class, 'store'])->name('production-units.store');
    Route::get('parcelles/{productionUnit}/modifier', [ProductionUnitController::class, 'edit'])->name('production-units.edit');
    Route::put('parcelles/{productionUnit}', [ProductionUnitController::class, 'update'])->name('production-units.update');
    Route::delete('parcelles/{productionUnit}', [ProductionUnitController::class, 'destroy'])->name('production-units.destroy');

    Route::get('cultures', [CropController::class, 'index'])->name('crops.index');
    Route::get('cultures/creer', [CropController::class, 'create'])->name('crops.create');
    Route::post('cultures', [CropController::class, 'store'])->name('crops.store');
    Route::get('cultures/{crop}/fiche', [CropController::class, 'guide'])->name('crops.guide');
    Route::get('cultures/{crop}/modifier', [CropController::class, 'edit'])->name('crops.edit');
    Route::put('cultures/{crop}', [CropController::class, 'update'])->name('crops.update');
    Route::delete('cultures/{crop}', [CropController::class, 'destroy'])->name('crops.destroy');

    Route::get('calendrier', [CalendarStepController::class, 'index'])->name('calendar-steps.index');
    Route::get('calendrier/creer', [CalendarStepController::class, 'create'])->name('calendar-steps.create');
    Route::post('calendrier', [CalendarStepController::class, 'store'])->name('calendar-steps.store');
    Route::get('calendrier/{calendarStep}/modifier', [CalendarStepController::class, 'edit'])->name('calendar-steps.edit');
    Route::put('calendrier/{calendarStep}', [CalendarStepController::class, 'update'])->name('calendar-steps.update');
    Route::delete('calendrier/{calendarStep}', [CalendarStepController::class, 'destroy'])->name('calendar-steps.destroy');

    Route::get('campagnes', [CampaignController::class, 'index'])->name('campaigns.index');
    Route::get('campagnes/creer', [CampaignController::class, 'create'])->name('campaigns.create');
    Route::post('campagnes', [CampaignController::class, 'store'])->name('campaigns.store');
    Route::get('campagnes/{campaign}', [CampaignController::class, 'show'])->name('campaigns.show');
    Route::get('campagnes/{campaign}/modifier', [CampaignController::class, 'edit'])->name('campaigns.edit');
    Route::put('campagnes/{campaign}', [CampaignController::class, 'update'])->name('campaigns.update');
    Route::delete('campagnes/{campaign}', [CampaignController::class, 'destroy'])->name('campaigns.destroy');

    Route::get('campagnes/{campaign}/calendrier', [CalendarGenerationController::class, 'preview'])->name('campaigns.calendar');
    Route::post('campagnes/{campaign}/calendrier/generer', [CalendarGenerationController::class, 'generate'])->name('campaigns.calendar.generate');
    Route::post('campagnes/{campaign}/calendrier/replanifier', [CalendarGenerationController::class, 'replan'])->name('campaigns.calendar.replan');

    Route::get('activites', [ActivityController::class, 'index'])->name('activities.index');
    Route::get('activites/creer', [ActivityController::class, 'create'])->name('activities.create');
    Route::post('activites', [ActivityController::class, 'store'])->name('activities.store');
    Route::get('activites/{activity}', [ActivityController::class, 'show'])->name('activities.show');
    Route::get('activites/{activity}/modifier', [ActivityController::class, 'edit'])->name('activities.edit');
    Route::put('activites/{activity}', [ActivityController::class, 'update'])->name('activities.update');
    Route::delete('activites/{activity}', [ActivityController::class, 'destroy'])->name('activities.destroy');
    Route::post('activites/{activity}/realiser', [ActivityController::class, 'complete'])->name('activities.complete');

    Route::get('intrants', [InputController::class, 'index'])->name('inputs.index');
    Route::get('intrants/creer', [InputController::class, 'create'])->name('inputs.create');
    Route::post('intrants', [InputController::class, 'store'])->name('inputs.store');
    Route::get('intrants/{input}', [InputController::class, 'show'])->name('inputs.show');
    Route::get('intrants/{input}/modifier', [InputController::class, 'edit'])->name('inputs.edit');
    Route::put('intrants/{input}', [InputController::class, 'update'])->name('inputs.update');
    Route::delete('intrants/{input}', [InputController::class, 'destroy'])->name('inputs.destroy');

    Route::get('stocks/mouvements', [StockMovementController::class, 'index'])->name('stock-movements.index');
    Route::get('stocks/mouvements/creer', [StockMovementController::class, 'create'])->name('stock-movements.create');
    Route::post('stocks/mouvements', [StockMovementController::class, 'store'])->name('stock-movements.store');

    Route::get('traitements', [TreatmentController::class, 'index'])->name('treatments.index');
    Route::get('traitements/creer', [TreatmentController::class, 'create'])->name('treatments.create');
    Route::post('traitements', [TreatmentController::class, 'store'])->name('treatments.store');
    Route::get('traitements/{treatment}', [TreatmentController::class, 'show'])->name('treatments.show');

    Route::get('stock-recolte', [ProduceLotController::class, 'index'])->name('produce-lots.index');
    Route::get('stock-recolte/{produceLot}', [ProduceLotController::class, 'show'])->name('produce-lots.show');
    Route::post('stock-recolte/{produceLot}/mouvement', [ProduceLotController::class, 'storeMovement'])->name('produce-lots.movements.store');

    Route::get('clients', [CustomerController::class, 'index'])->name('customers.index');
    Route::get('clients/creer', [CustomerController::class, 'create'])->name('customers.create');
    Route::post('clients', [CustomerController::class, 'store'])->name('customers.store');
    Route::get('clients/{customer}/modifier', [CustomerController::class, 'edit'])->name('customers.edit');
    Route::put('clients/{customer}', [CustomerController::class, 'update'])->name('customers.update');
    Route::delete('clients/{customer}', [CustomerController::class, 'destroy'])->name('customers.destroy');

    Route::get('ventes', [SaleController::class, 'index'])->name('sales.index');
    Route::get('ventes/creer', [SaleController::class, 'create'])->name('sales.create');
    Route::post('ventes', [SaleController::class, 'store'])->name('sales.store');
    Route::get('ventes/{sale}', [SaleController::class, 'show'])->name('sales.show');
    Route::post('ventes/{sale}/annuler', [SaleController::class, 'cancel'])->name('sales.cancel');
    Route::post('ventes/{sale}/paiements', [SaleController::class, 'storePayment'])->name('sales.payments.store');

    Route::get('recoltes', [HarvestController::class, 'index'])->name('harvests.index');
    Route::get('recoltes/creer', [HarvestController::class, 'create'])->name('harvests.create');
    Route::post('recoltes', [HarvestController::class, 'store'])->name('harvests.store');
    Route::get('recoltes/{harvest}', [HarvestController::class, 'show'])->name('harvests.show');
    Route::delete('recoltes/{harvest}', [HarvestController::class, 'destroy'])->name('harvests.destroy');

    Route::get('finances/depenses', [ExpenseController::class, 'index'])->name('expenses.index');
    Route::get('finances/depenses/creer', [ExpenseController::class, 'create'])->name('expenses.create');
    Route::post('finances/depenses', [ExpenseController::class, 'store'])->name('expenses.store');
    Route::get('finances/depenses/{expense}/modifier', [ExpenseController::class, 'edit'])->name('expenses.edit');
    Route::put('finances/depenses/{expense}', [ExpenseController::class, 'update'])->name('expenses.update');
    Route::delete('finances/depenses/{expense}', [ExpenseController::class, 'destroy'])->name('expenses.destroy');

    Route::get('finances/recettes', [RevenueController::class, 'index'])->name('revenues.index');
    Route::get('finances/recettes/creer', [RevenueController::class, 'create'])->name('revenues.create');
    Route::post('finances/recettes', [RevenueController::class, 'store'])->name('revenues.store');
    Route::get('finances/recettes/{revenue}/modifier', [RevenueController::class, 'edit'])->name('revenues.edit');
    Route::put('finances/recettes/{revenue}', [RevenueController::class, 'update'])->name('revenues.update');
    Route::delete('finances/recettes/{revenue}', [RevenueController::class, 'destroy'])->name('revenues.destroy');
});
