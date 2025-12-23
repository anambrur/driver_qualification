<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\RoleController;
use App\Http\Controllers\DriverController;
use App\Http\Controllers\CompanyController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\DashboardController;

Route::get('/', function () {
    return view('welcome');
});

Route::get('/dashboard', function () {
    return view('dashboard');
})->middleware(['auth', 'verified'])->name('dashboard');

Route::middleware('auth')->prefix('admin')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');

    // Dashboard
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('admin.dashboard');


    //Driver
    Route::prefix('driver')->group(function () {
        // Document upload routes (static paths) - MUST COME FIRST
        Route::post('/license', [DriverController::class, 'licenseStore'])->name('admin.driver.license.store');
        Route::get('/license/{driver_id}', [DriverController::class, 'license'])->name('admin.driver.license');

        Route::post('/medical-card', [DriverController::class, 'medicalCardStore'])->name('admin.driver.medical.card.store');
        Route::get('/medical-card/{driver_id}', [DriverController::class, 'medicalCard'])->name('admin.driver.medical.card');

        Route::post('/forfeiture-document', [DriverController::class, 'forfeitureStore'])->name('admin.driver.forfeiture.store');
        Route::get('/forfeiture-document/{driver_id}', [DriverController::class, 'forfeiture'])->name('admin.driver.forfeiture');

        Route::post('/violation', [DriverController::class, 'violationStore'])->name('admin.driver.violation.store');
        Route::get('/violation/{driver_id}', [DriverController::class, 'violation'])->name('admin.driver.violation');

        Route::post('/drug-test', [DriverController::class, 'alcoholAndDrugTestStore'])->name('admin.driver.alcohol.and.drug.store');
        Route::get('/drug-test/{driver_id}', [DriverController::class, 'alcoholAndDrugTest'])->name('admin.driver.alcohol.and.drug.test');

        Route::post('/psp', [DriverController::class, 'pspStore'])->name('admin.driver.psp.store');
        Route::get('/psp/{driver_id}', [DriverController::class, 'psp'])->name('admin.driver.psp');

        Route::post('/fmcsa_consent', [DriverController::class, 'consentStore'])->name('admin.driver.fmcsa.consent.store');
        Route::get('/fmcsa_consent/{driver_id}', [DriverController::class, 'consent'])->name('admin.driver.fmcsa.consent');

        Route::post('/alcohol-drug-test-policy', [DriverController::class, 'alcoholAndDrugTestPolicyStore'])->name('admin.driver.alcohol.and.drug.test.policy.store');
        Route::get('/alcohol-drug-test-policy/{driver_id}', [DriverController::class, 'alcoholAndDrugTestPolicy'])->name('admin.driver.alcohol.and.drug.test.policy');

        Route::post('/general-work-policy', [DriverController::class, 'generalWorkPolicyStore'])->name('admin.driver.general.work.policy.store');
        Route::get('/general-work-policy/{driver_id}', [DriverController::class, 'generalWorkPolicy'])->name('admin.driver.general.work.policy');

        Route::post('/{driver}/hire-status', [DriverController::class, 'updateHireStatus'])
            ->name('admin.driver.hire-status');

        // Basic CRUD routes
        Route::get('/', [DriverController::class, 'index'])->name('admin.driver.index')->middleware('permission:drivers.view');
        Route::get('/create', [DriverController::class, 'create'])->name('admin.driver.create')->middleware('permission:drivers.create');
        Route::post('/', [DriverController::class, 'store'])->name('admin.driver.store')->middleware('permission:drivers.create');

        

        // Single driver routes with {id} parameter - MUST COME LAST
        Route::get('/{id}', [DriverController::class, 'show'])->name('admin.driver.show')->middleware('permission:drivers.view');
        Route::get('/{id}/edit', [DriverController::class, 'edit'])->name('admin.driver.edit')->middleware('permission:drivers.edit');
        Route::post('/{id}', [DriverController::class, 'update'])->name('admin.driver.update')->middleware('permission:drivers.edit');
        Route::patch('/{id}', [DriverController::class, 'update'])->name('admin.driver.update'); // Alternative PATCH method
        Route::post('/{id}/status', [DriverController::class, 'updateStatus'])->name('admin.driver.update.status')->middleware('permission:drivers.edit');
        Route::delete('/{id}', [DriverController::class, 'destroy'])->name('admin.driver.destroy')->middleware('permission:drivers.delete');
    });


    //Settings
    Route::prefix('settings')->group(function () {
        // Company Route
        Route::get('/company', [CompanyController::class, 'index'])->name('admin.settings.company')->middleware('permission:companies.view');
        Route::get('/company/create', [CompanyController::class, 'create'])->name('admin.settings.company.create')->middleware('permission:companies.create');
        Route::post('/company', [CompanyController::class, 'store'])->name('admin.settings.company.store')->middleware('permission:companies.create');
        Route::get('/company/{id}/edit', [CompanyController::class, 'edit'])->name('admin.settings.company.edit')->middleware('permission:companies.edit');
        Route::patch('/company/{id}', [CompanyController::class, 'update'])->name('admin.settings.company.update')->middleware('permission:companies.edit');
        Route::delete('/company/{id}', [CompanyController::class, 'destroy'])->name('admin.settings.company.destroy')->middleware('permission:companies.delete');

        Route::get('/policy-pdf', [CompanyController::class, 'policyPDF'])->name('admin.settings.policy.pdf')->middleware('permission:policy-pdf.view');
        Route::post('/policy-pdf', [CompanyController::class, 'policyPDFStore'])->name('admin.settings.policy.pdf.store')->middleware('permission:policy-pdf.edit');


        //roles routes
        Route::get('/roles', [RoleController::class, 'index'])->name('admin.roles.index')
            ->middleware('permission:roles.view');
        Route::get('/roles/create', [RoleController::class, 'create'])->name('admin.roles.create')
            ->middleware('permission:roles.create');
        Route::post('/roles', [RoleController::class, 'store'])->name('admin.roles.store')
            ->middleware('permission:roles.create');
        Route::get('/roles/{id}', [RoleController::class, 'show'])->name('admin.roles.show')
            ->middleware('permission:roles.view');
        Route::get('/roles/{id}/edit', [RoleController::class, 'edit'])->name('admin.roles.edit')
            ->middleware('permission:roles.edit');
        Route::put('/roles/{role}', [RoleController::class, 'update'])->name('admin.roles.update')
            ->middleware('permission:roles.edit');
        Route::delete('/roles/{id}', [RoleController::class, 'destroy'])->name('admin.roles.destroy')
            ->middleware('permission:roles.delete');
    });
});

require __DIR__ . '/auth.php';
