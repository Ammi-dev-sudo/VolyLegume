<?php

use App\Http\Controllers\Api\ActivityController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\CampaignController;
use App\Http\Middleware\RequireActiveFarm;
use App\Http\Middleware\ResolveApiFarmContext;
use Illuminate\Support\Facades\Route;

Route::post('login', [AuthController::class, 'login'])->name('api.login');
Route::post('logout', [AuthController::class, 'logout'])->middleware('auth:sanctum')->name('api.logout');

Route::middleware(['auth:sanctum', ResolveApiFarmContext::class, RequireActiveFarm::class])->group(function () {
    Route::get('campaigns', [CampaignController::class, 'index'])->name('api.campaigns.index');
    Route::get('campaigns/{campaign}', [CampaignController::class, 'show'])->name('api.campaigns.show');
    Route::post('activities', [ActivityController::class, 'store'])->name('api.activities.store');
});
