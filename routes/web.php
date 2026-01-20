<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\RoleController;
use App\Http\Controllers\DriverController;
use App\Http\Controllers\CompanyController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\TrailerController;
use App\Http\Controllers\VehicleController;
use App\Http\Controllers\FuelTypeController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\AssetGroupController;
use App\Http\Controllers\VehicleTypeController;
use App\Http\Controllers\VehicleGroupController;
use App\Http\Controllers\EquipmentTypeController;
use App\Http\Controllers\ApplicationFormController;

Route::get('/', function () {
    return view('welcome');
});

Route::get('/dashboard', function () {
    return view('dashboard');
})->middleware(['auth', 'verified'])->name('dashboard');



// =====================================================================
// PUBLIC APPLICATION ROUTES (NO AUTH REQUIRED)
// =====================================================================

// Dynamic route for application form landing page
Route::get('/{slug}/apply', [ApplicationFormController::class, 'show'])->name('application.form');

// Public application routes with multi-step process
Route::prefix('{slug}/application')->name('public.application.')->group(function () {
    // Step 0: Start & OTP Verification
    Route::get('/start', [ApplicationFormController::class, 'start'])->name('start');
    Route::post('/send-otp', [ApplicationFormController::class, 'sendOtp'])->name('send.otp');
    Route::get('/verify-otp', [ApplicationFormController::class, 'showVerifyOtp'])->name('verify.otp');
    Route::post('/verify-otp', [ApplicationFormController::class, 'verifyOtp'])->name('submit.otp');
    Route::post('/resend-otp', [ApplicationFormController::class, 'resendOtp'])->name('resend.otp');

    // Step 1: Basic Information (Personal Details)
    Route::get('/step-1', [ApplicationFormController::class, 'step1'])
        ->name('step1')
        ->middleware('application.session');
    Route::post('/step-1', [ApplicationFormController::class, 'storeStep1'])
        ->name('store.step1')
        ->middleware('application.session');

    // Step 2: Driver License Upload
    Route::get('/step-2/{driver_id}', [ApplicationFormController::class, 'step2'])
        ->name('step2')
        ->middleware('application.session');
    Route::post('/step-2', [ApplicationFormController::class, 'storeStep2'])
        ->name('store.step2')
        ->middleware('application.session');

    // Step 3: Medical Card Upload
    Route::get('/step-3/{driver_id}', [ApplicationFormController::class, 'step3'])
        ->name('step3')
        ->middleware('application.session');
    Route::post('/step-3', [ApplicationFormController::class, 'storeStep3'])
        ->name('store.step3')
        ->middleware('application.session');

    // Step 4: Forfeiture Document Upload
    Route::get('/step-4/{driver_id}', [ApplicationFormController::class, 'step4'])
        ->name('step4')
        ->middleware('application.session');
    Route::post('/step-4', [ApplicationFormController::class, 'storeStep4'])
        ->name('store.step4')
        ->middleware('application.session');

    // Step 5: Violation Record
    Route::get('/step-5/{driver_id}', [ApplicationFormController::class, 'step5'])
        ->name('step5')
        ->middleware('application.session');
    Route::post('/step-5', [ApplicationFormController::class, 'storeStep5'])
        ->name('store.step5')
        ->middleware('application.session');

    // Step 6: Alcohol & Drug Test Statement
    Route::get('/step-6/{driver_id}', [ApplicationFormController::class, 'step6'])
        ->name('step6')
        ->middleware('application.session');
    Route::post('/step-6', [ApplicationFormController::class, 'storeStep6'])
        ->name('store.step6')
        ->middleware('application.session');

    // Step 7: FMCSA Clearinghouse Consent
    Route::get('/step-7/{driver_id}', [ApplicationFormController::class, 'step7'])
        ->name('step7')
        ->middleware('application.session');
    Route::post('/step-7', [ApplicationFormController::class, 'storeStep7'])
        ->name('store.step7')
        ->middleware('application.session');

    // Step 8: PSP Driver Disclosure & Authorization
    Route::get('/step-8/{driver_id}', [ApplicationFormController::class, 'step8'])
        ->name('step8')
        ->middleware('application.session');
    Route::post('/step-8', [ApplicationFormController::class, 'storeStep8'])
        ->name('store.step8')
        ->middleware('application.session');

    // Step 9: Alcohol & Drug Testing Policy
    Route::get('/step-9/{driver_id}', [ApplicationFormController::class, 'step9'])
        ->name('step9')
        ->middleware('application.session');
    Route::post('/step-9', [ApplicationFormController::class, 'storeStep9'])
        ->name('store.step9')
        ->middleware('application.session');

    // Step 10: General Work Policy & Final Review
    Route::get('/step-10/{driver_id}', [ApplicationFormController::class, 'step10'])
        ->name('step10')
        ->middleware('application.session');
    Route::post('/step-10', [ApplicationFormController::class, 'storeStep10'])
        ->name('store.step10')
        ->middleware('application.session');

    // Application Complete/Thank You Page
    Route::get('/complete', [ApplicationFormController::class, 'complete'])->name('complete');

    // Resume Application (if user leaves and comes back)
    Route::get('/resume', [ApplicationFormController::class, 'resume'])->name('resume');
    Route::post('/resume', [ApplicationFormController::class, 'verifyResume'])->name('verify.resume');

    // Application Status Check
    Route::get('/status', [ApplicationFormController::class, 'status'])->name('status');
    Route::post('/check-status', [ApplicationFormController::class, 'checkStatus'])->name('check.status');

    // Save Progress (AJAX)
    Route::post('/save-progress', [ApplicationFormController::class, 'saveProgress'])->name('save.progress');

    // Delete/Withdraw Application
    Route::post('/withdraw/{driver_id}', [ApplicationFormController::class, 'withdraw'])
        ->name('withdraw')
        ->middleware('application.session');
});


// Dynamic route for application form
// Route::get('/{slug}/apply', [ApplicationFormController::class, 'show'])->name('application.form');
// Route::get('/{slug}/start', [ApplicationFormController::class, 'start'])->name('public.application.start');
// Route::post('/{slug}/send-otp', [ApplicationFormController::class, 'sendOtp'])->name('public.application.send.otp');

// Route::get('/{slug}/verify-otp', [ApplicationFormController::class, 'showVerifyOtp'])
//     ->name('public.application.verify.otp');

// Route::post('/{slug}/verify-otp', [ApplicationFormController::class, 'verifyOtp'])
//     ->name('public.application.submit.otp');

// Route::post('/{slug}/resend-otp', [ApplicationFormController::class, 'resendOtp'])
//     ->name('public.application.resend.otp');

// Route::get('/{slug}/main-form', [ApplicationFormController::class, 'mainForm'])
//     ->name('application.main.form');

// Optional: Add middleware to validate slug exists
// Route::bind('slug', function ($slug) {
//     return \App\Models\Company::where('slug', $slug)->firstOrFail();
// });



Route::get('/profit', [DashboardController::class, 'profit'])->name('admin.profit');

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


        Route::post('/fmcsa_consent', [DriverController::class, 'consentStore'])->name('admin.driver.fmcsa.consent.store');
        Route::get('/fmcsa_consent/{driver_id}', [DriverController::class, 'consent'])->name('admin.driver.fmcsa.consent');

        Route::post('/psp', [DriverController::class, 'pspStore'])->name('admin.driver.psp.store');
        Route::get('/psp/{driver_id}', [DriverController::class, 'psp'])->name('admin.driver.psp');

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
        Route::put('/{id}', [DriverController::class, 'update'])->name('admin.driver.update')->middleware('permission:drivers.edit');
        Route::post('/{id}/status', [DriverController::class, 'updateStatus'])->name('admin.driver.update.status')->middleware('permission:drivers.edit');
        Route::delete('/{id}', [DriverController::class, 'destroy'])->name('admin.driver.destroy')->middleware('permission:drivers.delete');
        Route::get('/{id}/details', [DriverController::class, 'getDriverDetails'])->name('admin.drivers.get-driver-details');
    });



    //Vehicle types
    Route::prefix('vehicle-type')->group(function () {
        Route::get('/', [VehicleTypeController::class, 'index'])->name('admin.vehicle.type.index');
        Route::get('/create', [VehicleTypeController::class, 'create'])->name('admin.vehicle.type.create');
        Route::post('/', [VehicleTypeController::class, 'store'])->name('admin.vehicle.type.store');
        Route::get('/{id}/edit', [VehicleTypeController::class, 'edit'])->name('admin.vehicle.type.edit');
        Route::put('/{id}', [VehicleTypeController::class, 'update'])->name('admin.vehicle.type.update');
        Route::delete('/{id}', [VehicleTypeController::class, 'destroy'])->name('admin.vehicle.type.destroy');
    });

    // Vehicle groups
    Route::prefix('vehicle-group')->group(function () {
        Route::get('/', [VehicleGroupController::class, 'index'])->name('admin.vehicle.group.index');
        Route::post('/', [VehicleGroupController::class, 'store'])->name('admin.vehicle.group.store');
        Route::get('/{id}/edit', [VehicleGroupController::class, 'edit'])->name('admin.vehicle.group.edit');
        Route::put('/{id}', [VehicleGroupController::class, 'update'])->name('admin.vehicle.group.update');
        Route::delete('/{id}', [VehicleGroupController::class, 'destroy'])->name('admin.vehicle.group.destroy');
    });

    // Fuel types
    Route::prefix('fuel-type')->group(function () {
        Route::get('/', [FuelTypeController::class, 'index'])->name('admin.fuel.type.index');
        Route::post('/', [FuelTypeController::class, 'store'])->name('admin.fuel.type.store');
        Route::get('/{id}/edit', [FuelTypeController::class, 'edit'])->name('admin.fuel.type.edit');
        Route::put('/{id}', [FuelTypeController::class, 'update'])->name('admin.fuel.type.update');
        Route::delete('/{id}', [FuelTypeController::class, 'destroy'])->name('admin.fuel.type.destroy');
    });

    // Equipment types
    Route::prefix('equipment-type')->group(function () {
        Route::get('/', [EquipmentTypeController::class, 'index'])->name('admin.equipment.type.index');
        Route::post('/', [EquipmentTypeController::class, 'store'])->name('admin.equipment.type.store');
        Route::get('/{id}/edit', [EquipmentTypeController::class, 'edit'])->name('admin.equipment.type.edit');
        Route::put('/{id}', [EquipmentTypeController::class, 'update'])->name('admin.equipment.type.update');
        Route::delete('/{id}', [EquipmentTypeController::class, 'destroy'])->name('admin.equipment.type.destroy');
    });

    // Vehicles
    Route::prefix('vehicle')->group(function () {
        Route::get('/', [VehicleController::class, 'index'])->name('admin.vehicle.index');
        Route::post('/', [VehicleController::class, 'store'])->name('admin.vehicle.store');
        Route::get('/{id}/edit', [VehicleController::class, 'edit'])->name('admin.vehicle.edit');
        Route::put('/{id}', [VehicleController::class, 'update'])->name('admin.vehicle.update');
        Route::delete('/{id}', [VehicleController::class, 'destroy'])->name('admin.vehicle.destroy');
        Route::post('/{id}/restore', [VehicleController::class, 'restore'])->name('admin.vehicle.restore');
        Route::get('/dropdown-data', [VehicleController::class, 'getDropdownData'])->name('admin.vehicle.dropdown.data');
        Route::get('/{id}/details', [VehicleController::class, 'getVehicleDetails'])->name('admin.vehicles.get-vehicle-details');
    });

    // Trailers
    Route::prefix('trailer')->group(function () {
        Route::get('/', [TrailerController::class, 'index'])->name('admin.trailer.index');
        Route::post('/', [TrailerController::class, 'store'])->name('admin.trailer.store');
        Route::get('/{id}/edit', [TrailerController::class, 'edit'])->name('admin.trailer.edit');
        Route::put('/{id}', [TrailerController::class, 'update'])->name('admin.trailer.update');
        Route::delete('/{id}', [TrailerController::class, 'destroy'])->name('admin.trailer.destroy');
        Route::post('/{id}/restore', [TrailerController::class, 'restore'])->name('admin.trailer.restore');
        Route::get('/dropdown-data', [TrailerController::class, 'getDropdownData'])->name('admin.trailer.dropdown.data');
        Route::get('/{id}/details', [TrailerController::class, 'getTrailerDetails'])->name('admin.trailers.get-trailer-details');
    });

    // Asset Groups
    Route::prefix('asset-group')->group(function () {
        Route::get('/', [AssetGroupController::class, 'index'])->name('admin.asset-group.index');
        Route::post('/', [AssetGroupController::class, 'store'])->name('admin.asset-group.store');
        Route::get('asset-group/get-dropdown-data', [AssetGroupController::class, 'getDropdownData'])->name('admin.asset-group.get-dropdown-data');
        Route::get('/{id}/edit', [AssetGroupController::class, 'edit'])->name('admin.asset-group.edit');
        Route::put('/{id}', [AssetGroupController::class, 'update'])->name('admin.asset-group.update');
        Route::delete('/{id}', [AssetGroupController::class, 'destroy'])->name('admin.asset-group.destroy');
        Route::post('/{id}/restore', [AssetGroupController::class, 'restore'])->name('admin.asset-group.restore');
        Route::get('/dropdown-data', [AssetGroupController::class, 'getDropdownData'])->name('admin.asset-group.dropdown.data');
    });

    //Fleet
    Route::prefix('fleet')->group(function () {
        Route::get('/vehicle', [DriverController::class, 'fleets'])->name('admin.fleet.vehicle');
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
