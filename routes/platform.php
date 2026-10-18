<?php

use App\Http\Controllers\DocumentTypeController;
use App\Http\Controllers\EquipmentTypeController;
use App\Http\Controllers\FuelTypeController;
use App\Http\Controllers\MaintenanceCategoryController;
use App\Http\Controllers\Platform\AssetTransferController;
use App\Http\Controllers\Platform\AuditLogController;
use App\Http\Controllers\Platform\Auth\AuthenticatedSessionController;
use App\Http\Controllers\Platform\Auth\PasswordResetController;
use App\Http\Controllers\Platform\CompanyController;
use App\Http\Controllers\Platform\CompanyOverrideController;
use App\Http\Controllers\Platform\CompanySubscriptionController;
use App\Http\Controllers\Platform\DashboardController;
use App\Http\Controllers\Platform\FeatureController;
use App\Http\Controllers\Platform\PlanController;
use App\Http\Controllers\Platform\PlanPriceController;
use App\Http\Controllers\Platform\StaffController;
use App\Http\Controllers\Platform\SubscriptionController;
use App\Http\Controllers\Platform\SupportSessionController;
use App\Http\Controllers\SiteSettingController;
use App\Http\Controllers\TawkToSettingController;
use App\Http\Controllers\VehicleGroupController;
use App\Http\Controllers\VehicleTypeController;
use Illuminate\Support\Facades\Route;

/*
| The platform area (04 §2-3). Platform staff only, on the `platform` guard; tenant users never
| reach it and platform staff never reach tenant data from here. Every page is private (noindex).
*/

$can = fn (string $permission) => "permission:platform.{$permission},platform";

Route::prefix('platform')->name('platform.')->group(function () use ($can) {
    Route::middleware('guest:platform')->group(function () {
        Route::get('login', [AuthenticatedSessionController::class, 'create'])->name('login');
        Route::post('login', [AuthenticatedSessionController::class, 'store'])->middleware('throttle:5,1')->name('login.store');
        Route::get('forgot-password', [PasswordResetController::class, 'request'])->name('password.request');
        Route::post('forgot-password', [PasswordResetController::class, 'email'])->middleware('throttle:6,1')->name('password.email');
        Route::get('reset-password/{token}', [PasswordResetController::class, 'reset'])->name('password.reset');
        Route::post('reset-password', [PasswordResetController::class, 'update'])->middleware('throttle:6,1')->name('password.store');
    });

    Route::middleware(['auth:platform', 'platform'])->group(function () use ($can) {
        Route::post('logout', [AuthenticatedSessionController::class, 'destroy'])->name('logout');
        Route::get('/', DashboardController::class)->name('dashboard');

        // Companies: metadata only (04 §5)
        Route::prefix('companies')->name('companies.')->whereNumber('company')->group(function () use ($can) {
            Route::get('/', [CompanyController::class, 'index'])->name('index')->middleware($can('companies.view'));
            Route::get('/create', [CompanyController::class, 'create'])->name('create')->middleware($can('companies.manage'));
            Route::post('/', [CompanyController::class, 'store'])->name('store')->middleware($can('companies.manage'));
            Route::get('/{company}', [CompanyController::class, 'show'])->name('show')->middleware($can('companies.view'));
            Route::get('/{company}/edit', [CompanyController::class, 'edit'])->name('edit')->middleware($can('companies.manage'));
            Route::put('/{company}', [CompanyController::class, 'update'])->name('update')->middleware($can('companies.manage'));
            Route::post('/{company}/status', [CompanyController::class, 'status'])->name('status')->middleware($can('companies.lifecycle'));
            Route::post('/{company}/owner', [CompanyController::class, 'transferOwnership'])->name('owner')->middleware($can('companies.manage'));

            Route::post('/{company}/overrides', [CompanyOverrideController::class, 'store'])->name('overrides.store')->middleware($can('overrides.manage'));
            Route::post('/{company}/overrides/revoke', [CompanyOverrideController::class, 'destroy'])->name('overrides.revoke')->middleware($can('overrides.manage'));

            // Billing actions on a company (07 §7)
            Route::prefix('/{company}/subscription')->name('subscription.')->middleware($can('subscriptions.manage'))->group(function () {
                Route::post('/grant', [CompanySubscriptionController::class, 'grant'])->name('grant');
                Route::post('/expire', [CompanySubscriptionController::class, 'expire'])->name('expire');
                Route::post('/suspend', [CompanySubscriptionController::class, 'suspend'])->name('suspend');
                Route::post('/reactivate', [CompanySubscriptionController::class, 'reactivate'])->name('reactivate');
                Route::post('/move-price', [CompanySubscriptionController::class, 'movePrice'])->name('move-price');
            });
        });

        // Catalogue (05)
        Route::prefix('plans')->name('plans.')->whereNumber(['plan', 'price'])->middleware($can('plans.manage'))->group(function () {
            Route::get('/', [PlanController::class, 'index'])->name('index');
            Route::get('/create', [PlanController::class, 'create'])->name('create');
            Route::post('/', [PlanController::class, 'store'])->name('store');
            Route::get('/{plan}/edit', [PlanController::class, 'edit'])->name('edit');
            Route::put('/{plan}', [PlanController::class, 'update'])->name('update');
            Route::delete('/{plan}', [PlanController::class, 'destroy'])->name('destroy');
            Route::put('/{plan}/features', [PlanController::class, 'features'])->name('features');
            Route::post('/{plan}/prices', [PlanPriceController::class, 'store'])->name('prices.store');
            Route::post('/{plan}/prices/{price}/archive', [PlanPriceController::class, 'archive'])->name('prices.archive');
        });
        Route::get('features', [FeatureController::class, 'index'])->name('features.index')->middleware($can('features.manage'));
        Route::put('features/{feature}', [FeatureController::class, 'update'])->name('features.update')->middleware($can('features.manage'));

        Route::get('subscriptions', [SubscriptionController::class, 'dashboard'])->name('subscriptions.dashboard')->middleware($can('subscriptions.view'));
        Route::get('subscriptions/all', [SubscriptionController::class, 'index'])->name('subscriptions.index')->middleware($can('subscriptions.view'));
        Route::get('subscriptions/payments', [SubscriptionController::class, 'payments'])->name('subscriptions.payments')->middleware($can('payments.view'));

        // Global lookups (LKP-01) and platform settings
        Route::prefix('lookups')->name('lookups.')->middleware($can('settings.manage'))->group(function () {
            $lookup = function (string $prefix, string $controller) {
                Route::prefix($prefix)->name("{$prefix}.")->group(function () use ($controller) {
                    Route::get('/', [$controller, 'index'])->name('index');
                    Route::post('/', [$controller, 'store'])->name('store');
                    Route::get('/{id}/edit', [$controller, 'edit'])->name('edit');
                    Route::put('/{id}', [$controller, 'update'])->name('update');
                    Route::delete('/{id}', [$controller, 'destroy'])->name('destroy');
                });
            };
            $lookup('vehicle-types', VehicleTypeController::class);
            $lookup('vehicle-groups', VehicleGroupController::class);
            $lookup('fuel-types', FuelTypeController::class);
            $lookup('equipment-types', EquipmentTypeController::class);
            $lookup('maintenance-categories', MaintenanceCategoryController::class);

            Route::prefix('document-types')->name('document-types.')->group(function () {
                Route::get('/', [DocumentTypeController::class, 'index'])->name('index');
                Route::post('/', [DocumentTypeController::class, 'store'])->name('store');
                Route::get('/{id}', [DocumentTypeController::class, 'show'])->name('show');
                Route::put('/{id}', [DocumentTypeController::class, 'update'])->name('update');
                Route::delete('/{id}', [DocumentTypeController::class, 'destroy'])->name('destroy');
                Route::post('/{id}/toggle-status', [DocumentTypeController::class, 'toggleStatus'])->name('toggle-status');
            });
        });
        Route::prefix('settings')->name('settings.')->middleware($can('settings.manage'))->group(function () {
            Route::get('/site', [SiteSettingController::class, 'index'])->name('site.index');
            Route::put('/site', [SiteSettingController::class, 'update'])->name('site.update');
            Route::get('/tawk', [TawkToSettingController::class, 'index'])->name('tawk.index');
            Route::put('/tawk', [TawkToSettingController::class, 'update'])->name('tawk.update');
        });

        // Platform staff (04 §3): at least two active Platform Owners at all times
        Route::prefix('staff')->name('staff.')->whereNumber('staff')->middleware($can('staff.manage'))->group(function () {
            Route::get('/', [StaffController::class, 'index'])->name('index');
            Route::get('/create', [StaffController::class, 'create'])->name('create');
            Route::post('/', [StaffController::class, 'store'])->name('store');
            Route::get('/{staff}/edit', [StaffController::class, 'edit'])->name('edit');
            Route::put('/{staff}', [StaffController::class, 'update'])->name('update');
            Route::post('/{staff}/status', [StaffController::class, 'status'])->name('status');
        });

        Route::get('audit', [AuditLogController::class, 'index'])->name('audit.index')->middleware($can('audit.view'));

        // Support sessions (Q1, 04 §4): the company Owner and a second Platform Owner approve;
        // start and end are for the requester only (checked in SupportSessionService)
        Route::prefix('support-sessions')->name('support-sessions.')->whereNumber('supportSession')->group(function () use ($can) {
            Route::get('/', [SupportSessionController::class, 'index'])->name('index');
            Route::get('/create', [SupportSessionController::class, 'create'])->name('create')->middleware($can('support.request'));
            Route::post('/', [SupportSessionController::class, 'store'])->name('store')->middleware([$can('support.request'), 'throttle:20,1']);
            Route::post('/{supportSession}/approve', [SupportSessionController::class, 'approve'])->name('approve')->middleware($can('support.approve'));
            Route::post('/{supportSession}/reject', [SupportSessionController::class, 'reject'])->name('reject')->middleware($can('support.approve'));
            Route::post('/{supportSession}/start', [SupportSessionController::class, 'start'])->name('start')->middleware($can('support.request'));
            Route::post('/{supportSession}/end', [SupportSessionController::class, 'end'])->name('end');
        });

        // Asset Transfer (PO-08, 04 §7)
        Route::prefix('asset-transfers')->name('asset-transfers.')->middleware($can('assets.transfer'))->group(function () {
            Route::get('/', [AssetTransferController::class, 'index'])->name('index');
            Route::get('/create', [AssetTransferController::class, 'create'])->name('create');
            Route::get('/assets', [AssetTransferController::class, 'assets'])->name('assets');
            Route::post('/', [AssetTransferController::class, 'store'])->name('store')->middleware('throttle:10,1');
        });
    });
});
