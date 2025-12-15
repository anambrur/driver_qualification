<?php

use App\Http\Controllers\CompanyController;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DriverController;

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
        Route::get('/', [DriverController::class, 'index'])->name('admin.driver.index');
        Route::post('/', [DriverController::class, 'store'])->name('admin.driver.store');
        Route::get('/create', [DriverController::class, 'create'])->name('admin.driver.create');
        Route::get('/license/{driver_id}', [DriverController::class, 'license'])->name('admin.driver.license');
        Route::post('/license', [DriverController::class, 'licenseStore'])->name('admin.driver.license.store');

        Route::get('/medical-card/{driver_id}', [DriverController::class, 'medicalCard'])->name('admin.driver.medical.card');
        Route::post('/medical-card', [DriverController::class, 'medicalCardStore'])->name('admin.driver.medical.card.store');

        Route::get('/forfeiture-document/{driver_id}', [DriverController::class, 'forfeiture'])->name('admin.driver.forfeiture');
        Route::post('/forfeiture-document', [DriverController::class, 'forfeitureStore'])->name('admin.driver.forfeiture.store');

        Route::get('/violation/{driver_id}', [DriverController::class, 'violation'])->name('admin.driver.violation');
        Route::post('/violation', [DriverController::class, 'violationStore'])->name('admin.driver.violation.store');

        Route::get('/drug-test/{driver_id}', [DriverController::class, 'alcoholAndDrugTest'])->name('admin.driver.alcohol.and.drug.test');
        Route::post('/drug-test', [DriverController::class, 'alcoholAndDrugTestStore'])->name('admin.driver.alcohol.and.drug.store');

        Route::get('/psp/{driver_id}', [DriverController::class, 'psp'])->name('admin.driver.psp');
        Route::post('/psp', [DriverController::class, 'pspStore'])->name('admin.driver.psp.store');

        Route::get('/fmcsa_consent/{driver_id}', [DriverController::class, 'consent'])->name('admin.driver.fmcsa.consent');
        Route::post('/fmcsa_consent', [DriverController::class, 'consentStore'])->name('admin.driver.fmcsa.consent.store');

        Route::get('/alcohol-drug-test-policy/{driver_id}', [DriverController::class, 'alcoholAndDrugTestPolicy'])->name('admin.driver.alcohol.and.drug.test.policy');
        Route::post('/alcohol-drug-test-policy', [DriverController::class, 'alcoholAndDrugTestPolicyStore'])->name('admin.driver.alcohol.and.drug.test.policy.store');

        Route::get('/general-work-policy/{driver_id}', [DriverController::class, 'generalWorkPolicy'])->name('admin.driver.general.work.policy');
        Route::post('/general-work-policy', [DriverController::class, 'generalWorkPolicyStore'])->name('admin.driver.general.work.policy.store');

        Route::get('/{id}/edit', [DriverController::class, 'edit'])->name('admin.driver.edit');
        Route::patch('/{id}', [DriverController::class, 'update'])->name('admin.driver.update');
        Route::delete('/{id}', [DriverController::class, 'destroy'])->name('admin.driver.destroy');
    });


    //Settings
    Route::prefix('settings')->group(function () {
        // Company Route
        Route::get('/company', [CompanyController::class, 'index'])->name('admin.settings.company');
        Route::get('/company/create', [CompanyController::class, 'create'])->name('admin.settings.company.create');
        Route::post('/company', [CompanyController::class, 'store'])->name('admin.settings.company.store');
        Route::get('/company/{id}/edit', [CompanyController::class, 'edit'])->name('admin.settings.company.edit');
        Route::patch('/company/{id}', [CompanyController::class, 'update'])->name('admin.settings.company.update');
        Route::delete('/company/{id}', [CompanyController::class, 'destroy'])->name('admin.settings.company.destroy');


        Route::get('/policy-pdf', [CompanyController::class, 'policyPDF'])->name('admin.settings.policy.pdf');
        Route::post('/policy-pdf', [CompanyController::class, 'policyPDFStore'])->name('admin.settings.policy.pdf.store');
    });
});

require __DIR__ . '/auth.php';
