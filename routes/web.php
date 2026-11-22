<?php

use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\AttentionController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DataController;
use App\Http\Controllers\ExportController;
use App\Http\Controllers\MapController;
use App\Http\Controllers\QualityController;
use App\Http\Controllers\ReportingController;
use App\Http\Controllers\TrendController;
use App\Http\Controllers\VaccinationController;
use App\Http\Controllers\OrgUnitController;
use App\Http\Controllers\PublicController;
use Illuminate\Support\Facades\Route;

/* Public */
Route::get('/', [PublicController::class, 'landing'])->name('home');

/* Guest */
Route::middleware('guest')->group(function (): void {
    Route::get('/login', [LoginController::class, 'show'])->name('login');
    Route::post('/login', [LoginController::class, 'store'])->middleware('throttle:login')->name('login.store');
});

Route::post('/logout', [LoginController::class, 'destroy'])->middleware('auth')->name('logout');

/* Authenticated */
Route::middleware('auth')->group(function (): void {
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');
    Route::get('/areas/{orgUnit}', [OrgUnitController::class, 'show'])->name('areas.show');

    Route::middleware('can:view aggregates')->group(function (): void {
        Route::get('/trends', [TrendController::class, 'index'])->name('trends');
        Route::get('/map', [MapController::class, 'index'])->name('map');
        Route::get('/quality', [QualityController::class, 'index'])->name('quality');
        Route::get('/vaccination', [VaccinationController::class, 'index'])->name('vaccination');
        Route::get('/reporting', [ReportingController::class, 'index'])->name('reporting');
        Route::get('/attention', [AttentionController::class, 'index'])->name('attention');
        Route::get('/data/districts.geojson', [DataController::class, 'districts'])->name('data.districts');
    });

    Route::middleware('can:export aggregates')->group(function (): void {
        Route::get('/exports', [ExportController::class, 'index'])->name('exports.index');
        Route::get('/exports/download', [ExportController::class, 'download'])->name('exports.download');
    });
});
