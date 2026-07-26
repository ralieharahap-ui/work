<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\PalmOilSourceController;
use App\Http\Controllers\UnloadingPointController;
use App\Http\Controllers\JettyPointController;
use App\Http\Controllers\ProjectCalculatorController;
use Illuminate\Support\Facades\Route;

// ── Auth ─────────────────────────────────────────────────
Route::middleware('guest')->group(function () {
    Route::get('/login',     [AuthController::class, 'showLogin'])->name('login');
    Route::post('/login',    [AuthController::class, 'login']);
    Route::get('/register',  [AuthController::class, 'showRegister'])->name('register');
    Route::post('/register', [AuthController::class, 'register']);
});
Route::post('/logout', [AuthController::class, 'logout'])->middleware('auth')->name('logout');

// ── Protected ─────────────────────────────────────────────
Route::middleware(['auth', 'active'])->group(function () {

    Route::get('/', [DashboardController::class, '__invoke'])->name('dashboard');

    // Sumber Cangkang Sawit
    Route::middleware('permission:inventory.view')->prefix('palm-oil-sources')->group(function () {
        Route::get('/',                    [PalmOilSourceController::class, 'index'])->name('palm-oil-sources.index');
        Route::get('/create',              [PalmOilSourceController::class, 'create'])->middleware('permission:inventory.create')->name('palm-oil-sources.create');
        Route::post('/',                   [PalmOilSourceController::class, 'store'])->middleware('permission:inventory.create')->name('palm-oil-sources.store');
        Route::get('/{palmOilSource}',     [PalmOilSourceController::class, 'show'])->name('palm-oil-sources.show');
        Route::get('/{palmOilSource}/edit',[PalmOilSourceController::class, 'edit'])->middleware('permission:inventory.edit')->name('palm-oil-sources.edit');
        Route::put('/{palmOilSource}',     [PalmOilSourceController::class, 'update'])->middleware('permission:inventory.edit')->name('palm-oil-sources.update');
        Route::delete('/{palmOilSource}',  [PalmOilSourceController::class, 'destroy'])->middleware('permission:inventory.delete')->name('palm-oil-sources.destroy');
        Route::get('/api/data',            [PalmOilSourceController::class, 'api'])->name('palm-oil-sources.api');
    });

    // Titik Bongkar (Customer)
    Route::middleware('permission:inventory.view')->prefix('unloading-points')->group(function () {
        Route::get('/',                     [UnloadingPointController::class, 'index'])->name('unloading-points.index');
        Route::get('/create',               [UnloadingPointController::class, 'create'])->middleware('permission:inventory.create')->name('unloading-points.create');
        Route::post('/',                    [UnloadingPointController::class, 'store'])->middleware('permission:inventory.create')->name('unloading-points.store');
        Route::get('/{unloadingPoint}',     [UnloadingPointController::class, 'show'])->name('unloading-points.show');
        Route::get('/{unloadingPoint}/edit',[UnloadingPointController::class, 'edit'])->middleware('permission:inventory.edit')->name('unloading-points.edit');
        Route::put('/{unloadingPoint}',     [UnloadingPointController::class, 'update'])->middleware('permission:inventory.edit')->name('unloading-points.update');
        Route::delete('/{unloadingPoint}',  [UnloadingPointController::class, 'destroy'])->middleware('permission:inventory.delete')->name('unloading-points.destroy');
    });

    // Titik Dermaga (Jetty)
    Route::middleware('permission:inventory.view')->prefix('jetty-points')->group(function () {
        Route::get('/',                  [JettyPointController::class, 'index'])->name('jetty-points.index');
        Route::get('/create',            [JettyPointController::class, 'create'])->middleware('permission:inventory.create')->name('jetty-points.create');
        Route::post('/',                 [JettyPointController::class, 'store'])->middleware('permission:inventory.create')->name('jetty-points.store');
        Route::get('/{jettyPoint}',      [JettyPointController::class, 'show'])->name('jetty-points.show');
        Route::get('/{jettyPoint}/edit', [JettyPointController::class, 'edit'])->middleware('permission:inventory.edit')->name('jetty-points.edit');
        Route::put('/{jettyPoint}',      [JettyPointController::class, 'update'])->middleware('permission:inventory.edit')->name('jetty-points.update');
        Route::delete('/{jettyPoint}',   [JettyPointController::class, 'destroy'])->middleware('permission:inventory.delete')->name('jetty-points.destroy');
    });

    // Kalkulasi Proyek Pengadaan
    Route::middleware('permission:inventory.view')->group(function () {
        Route::get('/project-calculator',  [ProjectCalculatorController::class, 'index'])->name('project-calculator.index');
        Route::post('/project-calculator', [ProjectCalculatorController::class, 'store'])->middleware('permission:inventory.create')->name('project-calculator.store');
        Route::delete('/project-calculator/{scenario}', [ProjectCalculatorController::class, 'destroy'])->middleware('permission:inventory.delete')->name('project-calculator.destroy');
    });
});
