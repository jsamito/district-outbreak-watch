<?php

use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\DashboardController;
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
});
