<?php

use App\Http\Controllers\ApplicationFormController;
use App\Http\Controllers\AssetGroupController;
use App\Http\Controllers\BillingController;
use App\Http\Controllers\BlogController;
use App\Http\Controllers\CheckoutController;
use App\Http\Controllers\CompanyController;
use App\Http\Controllers\CompanyInvitationController;
use App\Http\Controllers\ComplianceDashboardController;
use App\Http\Controllers\ComplianceReminderController;
use App\Http\Controllers\ContactMessageController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DemoRequestController;
use App\Http\Controllers\DocumentTypeController;
use App\Http\Controllers\DocumentUploadController;
use App\Http\Controllers\DriverComplianceDashboardController;
use App\Http\Controllers\DriverController;
use App\Http\Controllers\DriverDocumentUploadController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\MaintenanceScheduleController;
use App\Http\Controllers\PageController;
use App\Http\Controllers\PlanController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\ReplyEmailController;
use App\Http\Controllers\RoleController;
use App\Http\Controllers\SeoController;
use App\Http\Controllers\ServiceLogController;
use App\Http\Controllers\StripeWebhookController;
use App\Http\Controllers\SupportAccessController;
use App\Http\Controllers\TrailerController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\UserPermissionController;
use App\Http\Controllers\VehicleController;
use App\Http\Middleware\ResolvePublicCompany;
use Illuminate\Support\Facades\Route;

Route::post('stripe/webhook', StripeWebhookController::class)->name('stripe.webhook');

Route::get('robots.txt', [SeoController::class, 'robots'])->name('seo.robots');
Route::get('sitemap.xml', [SeoController::class, 'sitemap'])->name('seo.sitemap');

Route::get('/', HomeController::class)->name('home');
Route::post('demo-request', [DemoRequestController::class, 'store'])->middleware('throttle:5,10,demo-request')->name('demo-requests.store');

// New accounts are sent to pricing (PO-16); a lapsed company stays read-only (07 §4.3)
Route::get('/dashboard', [DashboardController::class, 'index'])
    ->middleware(['auth', 'verified', 'subscribed'])
    ->name('dashboard');

// =====================================================================
// PUBLIC APPLICATION ROUTES (NO AUTH REQUIRED)
// =====================================================================

// Dynamic route for application form landing page
Route::get('/{slug}/apply', [ApplicationFormController::class, 'show'])->name('application.form')->middleware(ResolvePublicCompany::class);

// Public application routes with multi-step process
Route::prefix('{slug}/application')->name('public.application.')->middleware(ResolvePublicCompany::class)->group(function () {
    // Step 0: Start & OTP Verification
    Route::get('/start', [ApplicationFormController::class, 'start'])->name('start');
    Route::post('/send-otp', [ApplicationFormController::class, 'sendOtp'])->name('send.otp')
        ->middleware('throttle:5,10,application-sms');
    Route::get('/verify-otp', [ApplicationFormController::class, 'showVerifyOtp'])->name('verify.otp');
    Route::post('/verify-otp', [ApplicationFormController::class, 'verifyOtp'])->name('submit.otp')
        ->middleware('throttle:10,1,application-verify');
    Route::post('/resend-otp', [ApplicationFormController::class, 'resendOtp'])->name('resend.otp')
        ->middleware('throttle:5,10,application-sms');

    // Step 1: Basic Information (Personal Details)
    Route::get('/step-1', [ApplicationFormController::class, 'step1'])
        ->name('step1');
    Route::post('/step-1', [ApplicationFormController::class, 'storeStep1'])
        ->name('store.step1');

    // Step 2: Driver License Upload
    Route::get('/step-2/{driver_id}', [ApplicationFormController::class, 'step2'])
        ->name('step2');
    Route::post('/step-2', [ApplicationFormController::class, 'storeStep2'])
        ->name('store.step2');

    // Step 3: Medical Card Upload
    Route::get('/step-3/{driver_id}', [ApplicationFormController::class, 'step3'])
        ->name('step3');
    Route::post('/step-3', [ApplicationFormController::class, 'storeStep3'])
        ->name('store.step3');

    // Step 4: Forfeiture Document Upload
    Route::get('/step-4/{driver_id}', [ApplicationFormController::class, 'step4'])
        ->name('step4');
    Route::post('/step-4', [ApplicationFormController::class, 'storeStep4'])
        ->name('store.step4');

    // Step 5: Violation Record
    Route::get('/step-5/{driver_id}', [ApplicationFormController::class, 'step5'])
        ->name('step5');
    Route::post('/step-5', [ApplicationFormController::class, 'storeStep5'])
        ->name('store.step5');

    // Step 6: Alcohol & Drug Test Statement
    Route::get('/step-6/{driver_id}', [ApplicationFormController::class, 'step6'])
        ->name('step6');
    Route::post('/step-6', [ApplicationFormController::class, 'storeStep6'])
        ->name('store.step6');

    // Step 7: FMCSA Clearinghouse Consent
    Route::get('/step-7/{driver_id}', [ApplicationFormController::class, 'step7'])
        ->name('step7');
    Route::post('/step-7', [ApplicationFormController::class, 'storeStep7'])
        ->name('store.step7');

    // Step 8: PSP Driver Disclosure & Authorization
    Route::get('/step-8/{driver_id}', [ApplicationFormController::class, 'step8'])
        ->name('step8');
    Route::post('/step-8', [ApplicationFormController::class, 'storeStep8'])
        ->name('store.step8');

    // Step 9: Alcohol & Drug Testing Policy
    Route::get('/step-9/{driver_id}', [ApplicationFormController::class, 'step9'])
        ->name('step9');
    Route::post('/step-9', [ApplicationFormController::class, 'storeStep9'])
        ->name('store.step9');

    // Step 10: General Work Policy & Final Review
    Route::get('/step-10/{driver_id}', [ApplicationFormController::class, 'step10'])
        ->name('step10');
    Route::post('/step-10', [ApplicationFormController::class, 'storeStep10'])
        ->name('store.step10');

    // Application Complete/Thank You Page
    Route::get('/complete', [ApplicationFormController::class, 'complete'])->name('complete');

    // Resume Application (if user leaves and comes back)
    Route::get('/resume', [ApplicationFormController::class, 'resume'])->name('resume');
    Route::post('/resume', [ApplicationFormController::class, 'verifyResume'])->name('verify.resume')
        ->middleware('throttle:10,10,application-lookup');
    Route::post('/check-resume', [ApplicationFormController::class, 'checkResumePhone'])->name('check.resume')
        ->middleware('throttle:5,10,application-sms');
    Route::post('/check-resume-otp', [ApplicationFormController::class, 'verifyResumeOtpPhone'])->name('verify.resume.otp')
        ->middleware('throttle:10,1,application-verify');

    // Application Status Check
    Route::get('/status', [ApplicationFormController::class, 'status'])->name('status');
    Route::post('/check-status', [ApplicationFormController::class, 'checkStatus'])->name('check.status')
        ->middleware('throttle:5,10,application-sms');
    Route::get('/status/verify', [ApplicationFormController::class, 'showStatusVerify'])->name('status.verify');
    Route::post('/status/verify', [ApplicationFormController::class, 'verifyStatus'])->name('status.verify.submit')
        ->middleware('throttle:10,1,application-verify');

    // The applicant's own uploaded files (stored on the private disk)
    Route::get('/file/{driver_id}/{field}', [ApplicationFormController::class, 'file'])->name('file');
    Route::get('/policy/{policy}', [ApplicationFormController::class, 'policy'])->name('policy');

    // Delete/Withdraw Application
    Route::post('/withdraw/{driver_id}', [ApplicationFormController::class, 'withdraw'])
        ->name('withdraw');
});

// Signed links that confirm an email for replies; the signature is the authorization (opened from any device)
Route::middleware(['signed', 'throttle:20,1'])->prefix('reply-email/verify')->name('reply-email.verify.')->group(function () {
    Route::get('company/{company}/{hash}', [ReplyEmailController::class, 'verifyCompany'])->whereNumber('company')->name('company');
    Route::get('platform/{hash}', [ReplyEmailController::class, 'verifyPlatform'])->name('platform');
});

// Public site pages and blog (Platform > Content). Registered after /{slug}/apply so a company
// slug always wins there; post slugs "apply" and "application" are refused.
Route::get('blog', [BlogController::class, 'index'])->name('blog.index');
Route::get('blog/{post}', [BlogController::class, 'show'])->name('blog.show');
Route::get('{page}', [PageController::class, 'show'])
    ->where('page', 'about|careers|contact|security|privacy-policy|terms-of-service')
    ->name('pages.show');
Route::post('contact', [ContactMessageController::class, 'store'])->middleware('throttle:5,10,contact')->name('contact.store');

// Optional: Add middleware to validate slug exists
// Route::bind('slug', function ($slug) {
//     return \App\Models\Company::where('slug', $slug)->firstOrFail();
// });

// ─── Subscription routes (auth required, no subscription check) ───────────────
Route::middleware(['auth'])->group(function () {
    // Company users (06 §8, 10 §5): the services resolve every id inside the user's own company.
    Route::prefix('users')->name('users.')->whereNumber(['user', 'invitation'])->group(function () {
        Route::get('/', [UserController::class, 'index'])->name('index')->middleware('permission:users.view');
        Route::get('/invite', [CompanyInvitationController::class, 'create'])->name('invite')->middleware('permission:users.create');
        Route::post('/invitations', [CompanyInvitationController::class, 'store'])->name('invitations.store')->middleware(['permission:users.create', 'throttle:20,1']);
        Route::post('/invitations/{invitation}/resend', [CompanyInvitationController::class, 'resend'])->name('invitations.resend')->middleware(['permission:users.create', 'throttle:20,1']);
        Route::delete('/invitations/{invitation}', [CompanyInvitationController::class, 'destroy'])->name('invitations.destroy')->middleware('permission:users.create');
        Route::get('/{user}/edit', [UserController::class, 'edit'])->name('edit')->middleware('permission:users.edit');
        Route::put('/{user}', [UserController::class, 'update'])->name('update')->middleware('permission:users.edit');
        Route::post('/{user}/deactivate', [UserController::class, 'deactivate'])->name('deactivate')->middleware('permission:users.deactivate');
        Route::post('/{user}/reactivate', [UserController::class, 'reactivate'])->name('reactivate')->middleware('permission:users.deactivate');
        // Direct grants (06 §8 rule 2); revoking works after a downgrade (the service checks permissions.grant)
        Route::post('/{user}/permissions', [UserPermissionController::class, 'store'])->name('permissions.store')->middleware('permission:permissions.grant');
        Route::delete('/{user}/permissions/{permission}', [UserPermissionController::class, 'destroy'])->name('permissions.destroy')->middleware('permission:users.edit');
    });

    // Company roles (10 §4): per team, so another company's role id is a 404. Viewing roles and
    // editing the built-in ones is core; creating and deleting roles needs the Custom roles feature.
    Route::prefix('roles')->name('admin.roles.')->whereNumber('role')->group(function () {
        Route::get('/', [RoleController::class, 'index'])->name('index')->middleware('permission:roles.view');
        Route::get('/create', [RoleController::class, 'create'])->name('create')->middleware(['feature:custom_roles', 'permission:roles.create']);
        Route::post('/', [RoleController::class, 'store'])->name('store')->middleware(['feature:custom_roles', 'permission:roles.create']);
        Route::get('/{role}', [RoleController::class, 'show'])->name('show')->middleware('permission:roles.view');
        Route::get('/{role}/edit', [RoleController::class, 'edit'])->name('edit')->middleware('permission:roles.edit');
        Route::put('/{role}', [RoleController::class, 'update'])->name('update')->middleware('permission:roles.edit');
        Route::post('/{role}/reset', [RoleController::class, 'reset'])->name('reset')->middleware('permission:roles.edit');
        Route::delete('/{role}', [RoleController::class, 'destroy'])->name('destroy')->middleware(['feature:custom_roles', 'permission:roles.delete']);
    });

    // ─── Billing (07 §6): per company. Reachable in every access state except blocked, so a
    // company without a subscription, or a read-only one, can still pay.
    Route::get('/billing', [BillingController::class, 'index'])->name('billing.index')->middleware('permission:billing.view');
    Route::post('/billing/portal', [BillingController::class, 'portal'])->name('billing.portal')->middleware('permission:billing.manage');
    Route::post('/billing/cancel', [BillingController::class, 'cancel'])->name('billing.cancel')->middleware('permission:billing.manage');
    Route::post('/billing/resume', [BillingController::class, 'resume'])->name('billing.resume')->middleware('permission:billing.manage');

    Route::get('/pricing/plans', [PlanController::class, 'plans'])->name('pricing.plans');
    // A trial or a checkout needs a verified email: one free trial per verified email (PO-15)
    Route::post('/billing/trial', [CheckoutController::class, 'trial'])->name('billing.trial')->middleware(['verified', 'permission:billing.manage', 'throttle:10,1']);
    Route::post('/checkout', [CheckoutController::class, 'checkout'])->name('checkout')->middleware(['verified', 'permission:billing.manage', 'throttle:10,1']);
    Route::get('/checkout-success', [CheckoutController::class, 'success'])->name('checkout.success')->middleware(['verified', 'permission:billing.manage']);

    // Support access (Q1, 04 §4): only the company Owner approves, refuses or ends a platform
    // support session. Reachable in a read-only company too, so support can still help.
    Route::prefix('support-access')->name('support-access.')->whereNumber('supportSession')->group(function () {
        Route::get('/', [SupportAccessController::class, 'index'])->name('index');
        Route::post('/{supportSession}/approve', [SupportAccessController::class, 'approve'])->name('approve');
        Route::post('/{supportSession}/reject', [SupportAccessController::class, 'reject'])->name('reject');
        Route::post('/{supportSession}/revoke', [SupportAccessController::class, 'revoke'])->name('revoke');
    });

    // Legacy subscription routes (redirect to new billing flow)
    Route::get('/subscription/plans', fn () => redirect()->route('pricing.plans'));
    Route::get('/subscription/my', fn () => redirect()->route('billing.index'));
    Route::get('/subscription/renew', fn () => redirect()->route('billing.index'));
    Route::get('/subscription/expired', fn () => redirect()->route('pricing.plans'));
});

// The profile stays reachable before verification, so a mistyped email can be corrected
Route::middleware(['auth', 'subscribed'])->prefix('admin')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

Route::middleware(['auth', 'verified', 'subscribed'])->prefix('admin')->group(function () {

    // Dashboard
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('admin.dashboard');

    // Driver
    Route::prefix('driver')->middleware('feature:drivers')->group(function () {
        $wizardPermission = 'permission:drivers.create|drivers.edit';

        // Document upload routes (static paths) - MUST COME FIRST
        Route::post('/license', [DriverController::class, 'licenseStore'])->name('admin.driver.license.store')->middleware($wizardPermission);
        Route::get('/license/{driver_id}', [DriverController::class, 'license'])->name('admin.driver.license')->middleware($wizardPermission);

        Route::post('/medical-card', [DriverController::class, 'medicalCardStore'])->name('admin.driver.medical.card.store')->middleware($wizardPermission);
        Route::get('/medical-card/{driver_id}', [DriverController::class, 'medicalCard'])->name('admin.driver.medical.card')->middleware($wizardPermission);

        Route::post('/forfeiture-document', [DriverController::class, 'forfeitureStore'])->name('admin.driver.forfeiture.store')->middleware($wizardPermission);
        Route::get('/forfeiture-document/{driver_id}', [DriverController::class, 'forfeiture'])->name('admin.driver.forfeiture')->middleware($wizardPermission);

        Route::post('/violation', [DriverController::class, 'violationStore'])->name('admin.driver.violation.store')->middleware($wizardPermission);
        Route::get('/violation/{driver_id}', [DriverController::class, 'violation'])->name('admin.driver.violation')->middleware($wizardPermission);

        Route::post('/drug-test', [DriverController::class, 'alcoholAndDrugTestStore'])->name('admin.driver.alcohol.and.drug.store')->middleware($wizardPermission);
        Route::get('/drug-test/{driver_id}', [DriverController::class, 'alcoholAndDrugTest'])->name('admin.driver.alcohol.and.drug.test')->middleware($wizardPermission);

        Route::post('/fmcsa_consent', [DriverController::class, 'consentStore'])->name('admin.driver.fmcsa.consent.store')->middleware($wizardPermission);
        Route::get('/fmcsa_consent/{driver_id}', [DriverController::class, 'consent'])->name('admin.driver.fmcsa.consent')->middleware($wizardPermission);

        Route::post('/psp', [DriverController::class, 'pspStore'])->name('admin.driver.psp.store')->middleware($wizardPermission);
        Route::get('/psp/{driver_id}', [DriverController::class, 'psp'])->name('admin.driver.psp')->middleware($wizardPermission);

        Route::post('/alcohol-drug-test-policy', [DriverController::class, 'alcoholAndDrugTestPolicyStore'])->name('admin.driver.alcohol.and.drug.test.policy.store')->middleware($wizardPermission);
        Route::get('/alcohol-drug-test-policy/{driver_id}', [DriverController::class, 'alcoholAndDrugTestPolicy'])->name('admin.driver.alcohol.and.drug.test.policy')->middleware($wizardPermission);

        Route::post('/general-work-policy', [DriverController::class, 'generalWorkPolicyStore'])->name('admin.driver.general.work.policy.store')->middleware($wizardPermission);
        Route::get('/general-work-policy/{driver_id}', [DriverController::class, 'generalWorkPolicy'])->name('admin.driver.general.work.policy')->middleware($wizardPermission);

        Route::post('/{driver}/hire-status', [DriverController::class, 'updateHireStatus'])
            ->name('admin.driver.hire-status')
            ->middleware('permission:drivers.hire');

        // Basic CRUD routes
        Route::get('/', [DriverController::class, 'index'])->name('admin.driver.index')->middleware('permission:drivers.view');
        Route::get('/create', [DriverController::class, 'create'])->name('admin.driver.create')->middleware('permission:drivers.create');
        Route::post('/', [DriverController::class, 'store'])->name('admin.driver.store')->middleware('permission:drivers.create');

        // Single driver routes with {id} parameter - MUST COME LAST
        Route::get('/{id}', [DriverController::class, 'show'])->name('admin.driver.show')->middleware('permission:drivers.view');
        Route::get('/{id}/edit', [DriverController::class, 'edit'])->name('admin.driver.edit')->middleware('permission:drivers.edit');
        Route::put('/{id}', [DriverController::class, 'update'])->name('admin.driver.update')->middleware('permission:drivers.edit');
        Route::delete('/{id}', [DriverController::class, 'destroy'])->name('admin.driver.destroy')->middleware('permission:drivers.delete');
        Route::get('/{id}/details', [DriverController::class, 'getDriverDetails'])->name('admin.drivers.get-driver-details')->middleware('permission:drivers.view');
        Route::get('/{id}/file/{field}', [DriverController::class, 'file'])->name('admin.driver.file')->middleware('permission:drivers.view|drivers.create|drivers.edit');
    });

    // Vehicles
    Route::prefix('vehicle')->middleware('feature:fleet')->group(function () {
        Route::get('/', [VehicleController::class, 'index'])->name('admin.vehicle.index')->middleware('permission:vehicles.view');
        Route::get('/dropdown-data', [VehicleController::class, 'getDropdownData'])->name('admin.vehicle.dropdown.data')->middleware('permission:vehicles.view');
        Route::post('/', [VehicleController::class, 'store'])->name('admin.vehicle.store')->middleware('permission:vehicles.create');
        Route::get('/{id}', [VehicleController::class, 'show'])->name('admin.vehicle.show')->middleware('permission:vehicles.view')->whereNumber('id');
        Route::get('/{id}/photo', [VehicleController::class, 'photo'])->name('admin.vehicle.photo')->middleware('permission:vehicles.view');
        Route::get('/{id}/edit', [VehicleController::class, 'edit'])->name('admin.vehicle.edit')->middleware('permission:vehicles.edit');
        Route::put('/{id}', [VehicleController::class, 'update'])->name('admin.vehicle.update')->middleware('permission:vehicles.edit');
        Route::delete('/{id}', [VehicleController::class, 'destroy'])->name('admin.vehicle.destroy')->middleware('permission:vehicles.delete');
        Route::post('/{id}/restore', [VehicleController::class, 'restore'])->name('admin.vehicle.restore')->middleware('permission:vehicles.edit');
        Route::get('/{id}/details', [VehicleController::class, 'getVehicleDetails'])->name('admin.vehicles.get-vehicle-details')->middleware('permission:vehicles.view');
    });

    // Trailers
    Route::prefix('trailer')->middleware('feature:fleet')->group(function () {
        Route::get('/', [TrailerController::class, 'index'])->name('admin.trailer.index')->middleware('permission:trailers.view');
        Route::get('/dropdown-data', [TrailerController::class, 'getDropdownData'])->name('admin.trailer.dropdown.data')->middleware('permission:trailers.view');
        Route::post('/', [TrailerController::class, 'store'])->name('admin.trailer.store')->middleware('permission:trailers.create');
        Route::get('/{id}', [TrailerController::class, 'show'])->name('admin.trailer.show')->middleware('permission:trailers.view')->whereNumber('id');
        Route::get('/{id}/photo', [TrailerController::class, 'photo'])->name('admin.trailer.photo')->middleware('permission:trailers.view');
        Route::get('/{id}/edit', [TrailerController::class, 'edit'])->name('admin.trailer.edit')->middleware('permission:trailers.edit');
        Route::put('/{id}', [TrailerController::class, 'update'])->name('admin.trailer.update')->middleware('permission:trailers.edit');
        Route::delete('/{id}', [TrailerController::class, 'destroy'])->name('admin.trailer.destroy')->middleware('permission:trailers.delete');
        Route::post('/{id}/restore', [TrailerController::class, 'restore'])->name('admin.trailer.restore')->middleware('permission:trailers.edit');
        Route::get('/{id}/details', [TrailerController::class, 'getTrailerDetails'])->name('admin.trailers.get-trailer-details')->middleware('permission:trailers.view');
    });

    // Asset Groups
    Route::prefix('asset-group')->middleware('feature:fleet')->group(function () {
        Route::get('/', [AssetGroupController::class, 'index'])->name('admin.asset-group.index')->middleware('permission:asset-groups.view');
        Route::post('/', [AssetGroupController::class, 'store'])->name('admin.asset-group.store')->middleware('permission:asset-groups.create');
        Route::get('asset-group/get-dropdown-data', [AssetGroupController::class, 'getDropdownData'])->name('admin.asset-group.get-dropdown-data')->middleware('permission:asset-groups.view');
        Route::get('/{id}/edit', [AssetGroupController::class, 'edit'])->name('admin.asset-group.edit')->middleware('permission:asset-groups.edit');
        Route::put('/{id}', [AssetGroupController::class, 'update'])->name('admin.asset-group.update')->middleware('permission:asset-groups.edit');
        Route::delete('/{id}', [AssetGroupController::class, 'destroy'])->name('admin.asset-group.destroy')->middleware('permission:asset-groups.delete');
        Route::post('/{id}/restore', [AssetGroupController::class, 'restore'])->name('admin.asset-group.restore')->middleware('permission:asset-groups.edit');
        Route::get('/dropdown-data', [AssetGroupController::class, 'getDropdownData'])->name('admin.asset-group.dropdown.data')->middleware('permission:asset-groups.view');
    });

    Route::prefix('compliance')->group(function () {
        Route::get('/fleet-compliance', [ComplianceDashboardController::class, 'fleet'])->name('admin.compliance.fleet')->middleware('permission:fleets.dashboard');
        Route::get('vehicles/list', [DocumentUploadController::class, 'getVehiclesList'])->name('admin.compliance.vehicles.list')->middleware('permission:fleets.dashboard');
        Route::get('trailers/list', [DocumentUploadController::class, 'getTrailersList'])->name('admin.compliance.trailers.list')->middleware('permission:fleets.dashboard');
        Route::get('/vehicles/{id}/details', [ComplianceDashboardController::class, 'getVehicleDetails'])->name('admin.compliance.vehicle.details')->middleware('permission:fleets.dashboard');
        Route::get('/trailers/{id}/details', [ComplianceDashboardController::class, 'getTrailerDetails'])->name('admin.compliance.trailer.details')->middleware('permission:fleets.dashboard');
        Route::post('documents/upload', [DocumentUploadController::class, 'uploadDocument'])->name('admin.compliance.documents.upload')->middleware('permission:fleets.edit');
        Route::delete('documents/delete', [DocumentUploadController::class, 'deleteDocument'])->name('admin.compliance.documents.delete')->middleware('permission:fleets.delete');
        Route::get('documents/{documentId}/{assetType}/download', [DocumentUploadController::class, 'downloadDocument'])->name('admin.compliance.documents.download')->middleware('permission:fleets.dashboard');
        Route::get('documents/{documentId}/{assetType}/view', [DocumentUploadController::class, 'viewDocument'])->name('admin.compliance.documents.view')->middleware('permission:fleets.dashboard');
        Route::post('documents/send-reminder', [ComplianceReminderController::class, 'sendVehicleReminder'])->name('admin.compliance.documents.send-reminder')->middleware(['permission:fleets.edit', 'throttle:10,1,compliance-reminder']);

        // Driver compliance routes
        Route::get('/drivers', [DriverComplianceDashboardController::class, 'index'])->name('admin.compliance.drivers')->middleware('permission:drivers.dashboard');
        Route::get('drivers/list', [DriverDocumentUploadController::class, 'getDriversList'])->name('admin.compliance.drivers.list')->middleware('permission:drivers.dashboard');
        Route::get('/drivers/{id}/details', [DriverComplianceDashboardController::class, 'getDriverDetails'])->name('admin.compliance.driver.details')->middleware('permission:drivers.dashboard');

        // Driver document upload routes
        Route::post('driver-documents/upload', [DriverDocumentUploadController::class, 'uploadDocument'])->name('admin.compliance.driver.documents.upload')->middleware('permission:drivers.edit');
        Route::delete('driver-documents/delete', [DriverDocumentUploadController::class, 'deleteDocument'])->name('admin.compliance.driver.documents.delete')->middleware('permission:driver-documents.delete');
        Route::get('driver-documents/{documentId}/download', [DriverDocumentUploadController::class, 'downloadDocument'])->name('admin.compliance.driver.documents.download')->middleware('permission:drivers.dashboard');
        Route::get('driver-documents/{documentId}/view', [DriverDocumentUploadController::class, 'viewDocument'])->name('admin.compliance.driver.documents.view')->middleware('permission:drivers.dashboard');
        Route::post('driver-documents/send-reminder', [ComplianceReminderController::class, 'sendDriverReminder'])->name('admin.compliance.driver.documents.send-reminder')->middleware(['permission:drivers.edit', 'throttle:10,1,compliance-reminder']);
    });

    // Service Logs
    Route::prefix('service-log')->middleware('feature:maintenance')->group(function () {
        Route::get('/', [ServiceLogController::class, 'index'])->name('admin.service-log.index')->middleware('permission:maintenance.view');
        Route::post('/', [ServiceLogController::class, 'store'])->name('admin.service-log.store')->middleware('permission:maintenance.create');
        Route::get('/dropdown-data', [ServiceLogController::class, 'getDropdownData'])->name('admin.service-log.dropdown-data')->middleware('permission:maintenance.view');
        Route::get('/{id}/edit', [ServiceLogController::class, 'edit'])->name('admin.service-log.edit')->middleware('permission:maintenance.edit');
        Route::get('/{id}', [ServiceLogController::class, 'show'])->name('admin.service-log.show')->middleware('permission:maintenance.view');
        Route::put('/{id}', [ServiceLogController::class, 'update'])->name('admin.service-log.update')->middleware('permission:maintenance.edit');
        Route::delete('/{id}', [ServiceLogController::class, 'destroy'])->name('admin.service-log.destroy')->middleware('permission:maintenance.delete');

        Route::get('/vehicle/{id}/details', [ServiceLogController::class, 'getVehicleDetails'])->name('admin.service-log.get-vehicle-details')->middleware('permission:maintenance.view');
        Route::get('/document/{id}/download', [ServiceLogController::class, 'downloadDocument'])->name('admin.service-log.download-document')->middleware('permission:maintenance.view');
        Route::delete('/document/{id}', [ServiceLogController::class, 'deleteDocument'])->name('admin.service-log.delete-document')->middleware('permission:maintenance.edit');
    });

    // Maintenance Schedules
    Route::prefix('maintenance-schedule')->middleware('feature:maintenance')->group(function () {
        Route::get('/', [MaintenanceScheduleController::class, 'index'])->name('admin.maintenance-schedule.index')->middleware('permission:scheduled.view');
        Route::post('/', [MaintenanceScheduleController::class, 'store'])->name('admin.maintenance-schedule.store')->middleware('permission:scheduled.create');
        Route::get('/dropdown-data', [MaintenanceScheduleController::class, 'getDropdownData'])->name('admin.maintenance-schedule.dropdown-data')->middleware('permission:scheduled.view');
        Route::get('/{id}/edit', [MaintenanceScheduleController::class, 'edit'])->name('admin.maintenance-schedule.edit')->middleware('permission:scheduled.edit');
        Route::get('/{id}', [MaintenanceScheduleController::class, 'show'])->name('admin.maintenance-schedule.show')->middleware('permission:scheduled.view');
        Route::put('/{id}', [MaintenanceScheduleController::class, 'update'])->name('admin.maintenance-schedule.update')->middleware('permission:scheduled.edit');
        Route::delete('/{id}', [MaintenanceScheduleController::class, 'destroy'])->name('admin.maintenance-schedule.destroy')->middleware('permission:scheduled.delete');

        Route::get('/vehicle/{id}/details', [MaintenanceScheduleController::class, 'getVehicleDetails'])->name('admin.maintenance-schedule.get-vehicle-details')->middleware('permission:scheduled.view');
        Route::post('/{id}/mark-completed', [MaintenanceScheduleController::class, 'markAsCompleted'])->name('admin.maintenance-schedule.mark-completed')->middleware('permission:scheduled.edit');
    });

    // Settings: the company's own profile and policy PDFs (PO-14); platform settings live in /platform
    Route::prefix('settings')->group(function () {
        Route::get('/company/{id}/edit', [CompanyController::class, 'edit'])->name('admin.settings.company.edit')->middleware('permission:company.edit');
        Route::patch('/company/{id}', [CompanyController::class, 'update'])->name('admin.settings.company.update')->middleware('permission:company.edit');
        Route::post('/company/reply-email', [ReplyEmailController::class, 'store'])->name('admin.settings.company.reply-email.store')->middleware(['permission:company.edit', 'throttle:3,1']);
        Route::delete('/company/reply-email', [ReplyEmailController::class, 'destroy'])->name('admin.settings.company.reply-email.destroy')->middleware('permission:company.edit');

        Route::get('/policy-pdf', [CompanyController::class, 'policyPDF'])->name('admin.settings.policy.pdf')->middleware('permission:company.edit');
        Route::post('/policy-pdf', [CompanyController::class, 'policyPDFStore'])->name('admin.settings.policy.pdf.store')->middleware('permission:company.edit');
        // The driver wizard shows the policies to staff who sign drivers up
        Route::get('/policy-pdf/{policy}', [CompanyController::class, 'policyFile'])->name('admin.settings.policy.pdf.file')->middleware('permission:company.edit|drivers.create|drivers.edit');

        // Document types are managed by the platform (LKP-01); a company switches them off for itself
        Route::prefix('document-types')->group(function () {
            Route::get('/', [DocumentTypeController::class, 'index'])->name('admin.settings.document-types.index');
            Route::get('/by-module', [DocumentTypeController::class, 'getByModule'])->name('admin.settings.document-types.by-module');
            Route::get('/{id}', [DocumentTypeController::class, 'show'])->name('admin.settings.document-types.show');
            Route::post('/{id}/company-toggle', [DocumentTypeController::class, 'toggleForCompany'])->name('admin.settings.document-types.company-toggle')->middleware('permission:document-types.toggle');
        });
    });
});

require __DIR__.'/auth.php';
require __DIR__.'/platform.php';
