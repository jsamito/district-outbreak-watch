<?php

use App\Http\Controllers\Api\NotificationController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->middleware(['api.client', 'throttle:120,1'])->group(function (): void {
    Route::post('/notifications', [NotificationController::class, 'store'])->name('api.notifications.store');
});
