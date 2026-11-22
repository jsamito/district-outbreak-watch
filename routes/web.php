<?php

use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\PasswordController;
use App\Http\Controllers\Admin\AuditController;
use App\Http\Controllers\Admin\SourceController;
use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\AlertController;
use App\Http\Controllers\AnnotationController;
use App\Http\Controllers\SavedViewController;
use App\Http\Controllers\Admin\ApiClientController;
use App\Http\Controllers\AlertRuleController;
use App\Http\Controllers\AttentionController;
use App\Http\Controllers\CaseAnalyticsController;
use App\Http\Controllers\CaseController;
use App\Http\Controllers\DataQualityController;
use App\Http\Controllers\FhirController;
use App\Http\Controllers\ForecastController;
use App\Http\Controllers\LabController;
use App\Http\Controllers\LiveController;
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
use App\Http\Controllers\ShareCardController;
use Illuminate\Support\Facades\Route;

/* Public */
Route::get('/', [PublicController::class, 'landing'])->name('home');
Route::get('/situation', [PublicController::class, 'situation'])->name('situation');
Route::get('/about-the-data', [PublicController::class, 'about'])->name('about');
Route::get('/accessibility', [PublicController::class, 'accessibility'])->name('accessibility');
Route::get('/privacy', [PublicController::class, 'privacy'])->name('privacy');
Route::get('/share', [ShareCardController::class, 'page'])->name('share');
Route::get('/share/summary.png', [ShareCardController::class, 'png'])->name('share.png');
Route::get('/sitemap.xml', [PublicController::class, 'sitemap'])->name('sitemap');
Route::get('/robots.txt', [PublicController::class, 'robots'])->name('robots');

/* Guest */
Route::middleware('guest')->group(function (): void {
    Route::get('/login', [LoginController::class, 'show'])->name('login');
    Route::post('/login', [LoginController::class, 'store'])->middleware('throttle:login')->name('login.store');
});

Route::post('/logout', [LoginController::class, 'destroy'])->middleware('auth')->name('logout');

/* Authenticated */
Route::middleware(['auth', 'password.changed'])->group(function (): void {
    Route::get('/account/password', [PasswordController::class, 'edit'])->name('password.edit');
    Route::put('/account/password', [PasswordController::class, 'update'])->middleware('throttle:6,1')->name('password.update');
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');
    Route::get('/areas/{orgUnit}', [OrgUnitController::class, 'show'])->name('areas.show');

    Route::middleware('can:view aggregates')->group(function (): void {
        Route::get('/dashboard/data', [DashboardController::class, 'data'])->name('dashboard.data');
        Route::get('/trends', [TrendController::class, 'index'])->name('trends');
        Route::get('/map', [MapController::class, 'index'])->name('map');
        Route::get('/quality', [QualityController::class, 'index'])->name('quality');
        Route::get('/vaccination', [VaccinationController::class, 'index'])->name('vaccination');
        Route::get('/reporting', [ReportingController::class, 'index'])->name('reporting');
        Route::get('/data-quality', [DataQualityController::class, 'index'])->name('quality-flags.index');
        Route::get('/attention', [AttentionController::class, 'index'])->name('attention');
        Route::get('/live/summary', [LiveController::class, 'summary'])->name('live.summary');
        Route::get('/fhir/MeasureReport', [FhirController::class, 'measureReport'])->name('fhir.measure-report');
        Route::get('/data/districts.geojson', [DataController::class, 'districts'])->name('data.districts');
    });

    Route::middleware('can:view forecasts')->get('/forecasts', [ForecastController::class, 'index'])->name('forecasts');

    Route::middleware('can:view alerts')->group(function (): void {
        Route::get('/alerts', [AlertController::class, 'index'])->name('alerts.index');
        Route::post('/alerts/preferences', [AlertController::class, 'preferences'])->name('alerts.preferences');
        Route::get('/alerts/{alert}', [AlertController::class, 'show'])->whereNumber('alert')->name('alerts.show');
        Route::post('/alerts/{alert}/acknowledge', [AlertController::class, 'acknowledge'])->whereNumber('alert')->name('alerts.acknowledge');
    });

    Route::middleware('can:annotate charts')->group(function (): void {
        Route::post('/annotations', [AnnotationController::class, 'store'])->name('annotations.store');
        Route::delete('/annotations/{annotation}', [AnnotationController::class, 'destroy'])->whereNumber('annotation')->name('annotations.destroy');
    });
    Route::middleware('can:view aggregates')->group(function (): void {
        Route::post('/saved-views', [SavedViewController::class, 'store'])->name('saved-views.store');
        Route::delete('/saved-views/{savedView}', [SavedViewController::class, 'destroy'])->whereNumber('savedView')->name('saved-views.destroy');
    });

    Route::prefix('admin')->group(function (): void {
        Route::middleware('can:manage api clients')->group(function (): void {
            Route::get('/api-clients', [ApiClientController::class, 'index'])->name('admin.api-clients');
            Route::post('/api-clients', [ApiClientController::class, 'store'])->name('admin.api-clients.store');
            Route::post('/api-clients/{client}/toggle', [ApiClientController::class, 'toggle'])->whereNumber('client')->name('admin.api-clients.toggle');
        });
        Route::middleware('can:manage users')->group(function (): void {
            Route::get('/users', [UserController::class, 'index'])->name('admin.users');
            Route::get('/users/create', [UserController::class, 'create'])->name('admin.users.create');
            Route::post('/users', [UserController::class, 'store'])->name('admin.users.store');
            Route::get('/users/{user}/edit', [UserController::class, 'edit'])->name('admin.users.edit');
            Route::put('/users/{user}', [UserController::class, 'update'])->name('admin.users.update');
        });
        Route::middleware('can:manage data sources')->get('/sources', [SourceController::class, 'index'])->name('admin.sources');
        Route::middleware('permission:view audit log|view audit summary')->get('/audit', [AuditController::class, 'index'])->name('admin.audit');
    });

    Route::middleware('can:manage alert rules')->group(function (): void {
        Route::get('/alert-rules', [AlertRuleController::class, 'index'])->name('alert-rules.index');
        Route::get('/alert-rules/create', [AlertRuleController::class, 'create'])->name('alert-rules.create');
        Route::post('/alert-rules', [AlertRuleController::class, 'store'])->name('alert-rules.store');
        Route::get('/alert-rules/{alertRule}/edit', [AlertRuleController::class, 'edit'])->name('alert-rules.edit');
        Route::put('/alert-rules/{alertRule}', [AlertRuleController::class, 'update'])->name('alert-rules.update');
    });

    Route::middleware('can:edit cases')->group(function (): void {
        Route::get('/cases/create', [CaseController::class, 'create'])->name('cases.create');
        Route::post('/cases', [CaseController::class, 'store'])->name('cases.store');
        Route::put('/cases/{case}', [CaseController::class, 'update'])->whereNumber('case')->name('cases.update');
        Route::post('/cases/{case}/notes', [CaseController::class, 'note'])->whereNumber('case')->name('cases.note');
    });
    Route::middleware('can:enter lab results')->group(function (): void {
        Route::post('/cases/{case}/lab', [CaseController::class, 'requestLab'])->whereNumber('case')->name('cases.lab');
        Route::post('/lab/{lab}/result', [LabController::class, 'result'])->whereNumber('lab')->name('lab.result');
    });
    Route::middleware('can:review data quality')->post('/data-quality/{flag}/review', [DataQualityController::class, 'review'])->whereNumber('flag')->name('quality-flags.review');
    Route::middleware('can:view lab worklist')->get('/lab', [LabController::class, 'index'])->name('lab.index');
    Route::middleware('permission:view cases|view cases pseudonymised')->group(function (): void {
        Route::get('/cases', [CaseController::class, 'index'])->name('cases.index');
        Route::get('/cases/analytics', [CaseAnalyticsController::class, 'index'])->name('cases.analytics');
        Route::get('/cases/{case}', [CaseController::class, 'show'])->whereNumber('case')->name('cases.show');
    });

    Route::middleware('can:export aggregates')->group(function (): void {
        Route::get('/exports', [ExportController::class, 'index'])->name('exports.index');
        Route::get('/exports/download', [ExportController::class, 'download'])->name('exports.download');
    });
});
