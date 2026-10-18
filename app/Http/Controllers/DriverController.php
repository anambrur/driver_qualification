<?php

namespace App\Http\Controllers;

use App\DataTables\Driver\DriverTable;
use App\Exceptions\LimitReached;
use App\Http\Requests\Driver\StoreDriverRequest;
use App\Http\Requests\Driver\UpdateDriverRequest;
use App\Models\AssetGroup;
use App\Models\Driver;
use App\Models\DriverDocument;
use App\Models\PolicyPdf;
use App\Services\Compliance\DriverComplianceService;
use App\Services\Driver\DriverCrudService;
use App\Services\Driver\DriverDocumentWizardService;
use App\Services\Driver\DriverHireService;
use App\Support\Driver\DriverForm;
use App\Tenancy\TenantRule;
use App\Traits\CompanyFilterTrait;
use Exception;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use InvalidArgumentException;

class DriverController extends Controller
{
    use CompanyFilterTrait;

    public function index(Request $request)
    {
        if ($request->ajax()) {
            return (new DriverTable($request))->json();
        }

        $baseQuery = Driver::query();

        // Draft applications are never listed
        $baseQuery->listed();

        // Calculate status counts in one grouped query
        $byStatus = $baseQuery->toBase()
            ->selectRaw(Driver::STATUS_SQL.' AS status, COUNT(*) as aggregate')
            ->groupByRaw(Driver::STATUS_SQL)
            ->pluck('aggregate', 'status');

        $statusCounts = ['all' => (int) $byStatus->sum()];
        foreach (['draft', 'pending', 'active', 'inactive', 'rejected'] as $status) {
            $statusCounts[$status] = (int) ($byStatus[$status] ?? 0);
        }

        return view('admin.driver.index', compact('statusCounts'));
    }

    public function create()
    {
        if ($this->getCompaniesForUser()->isEmpty()) {
            toastr()->error('You do not have a company assigned.');

            return redirect()->route('admin.driver.index');
        }

        return view('admin.driver.create', ['form' => DriverForm::for()]);
    }

    public function store(StoreDriverRequest $request, DriverCrudService $crud)
    {
        try {
            $driver = $crud->create($request->validated(), Auth::id(), $request->file('photo'));

            toastr()->success('Driver created successfully!');

            return redirect()->route('admin.driver.license', ['driver_id' => $driver->id]);
        } catch (LimitReached $e) {
            throw $e;
        } catch (Exception $e) {
            Log::error('Driver creation failed: '.$e->getMessage(), [
                'exception' => $e,
                'request_data' => $request->except(['ssn', 'license_number']),
            ]);

            toastr()->error('Failed to create driver. Please try again.');

            return back()->withInput()->withErrors([
                'system_error' => 'An error occurred while creating the driver. Please try again.',
            ]);
        }
    }

    public function show($id, DriverComplianceService $complianceService)
    {
        $driver = Driver::with([
            'company',
            'licenses' => function ($query) {
                $query->orderBy('expires', 'desc');
            },
            'driver_documents',
            'documents.documentType', 'documents.file',
            'residence_addresses' => function ($query) {
                $query->orderBy('is_current', 'desc')->orderBy('created_at', 'desc');
            },
        ])->findOrFail($id);

        Gate::authorize('view', $driver);

        $compliance = $complianceService->forDriver($driver);

        $uploadedDocuments = $driver->documents
            ->filter(fn ($doc) => filled($doc->file?->path))
            ->sortByDesc('updated_at')
            ->values();

        $activityLogs = collect();

        foreach ($uploadedDocuments as $doc) {
            $typeName = $doc->documentType?->name ?? 'Document';
            $isReplace = $doc->created_at && $doc->updated_at
                && $doc->created_at->timestamp !== $doc->updated_at->timestamp;

            $activityLogs->push([
                'at' => $doc->updated_at,
                'label' => $isReplace
                    ? "Updated {$typeName}"
                    : "Uploaded {$typeName}",
                'detail' => $doc->description,
                'icon' => $isReplace ? 'fa-sync-alt' : 'fa-upload',
                'tone' => 'brand',
            ]);
        }

        if ($driver->hired_at) {
            $activityLogs->push([
                'at' => $driver->hired_at,
                'label' => 'Driver hired',
                'detail' => null,
                'icon' => 'fa-user-check',
                'tone' => 'green',
            ]);
        }

        if ($driver->rejected_at) {
            $activityLogs->push([
                'at' => $driver->rejected_at,
                'label' => 'Driver not hired',
                'detail' => $driver->rejection_reason
                    ? ucfirst(str_replace('_', ' ', $driver->rejection_reason))
                    : null,
                'icon' => 'fa-user-times',
                'tone' => 'red',
            ]);
        }

        $activityLogs = $activityLogs
            ->filter(fn ($log) => filled($log['at']))
            ->sortByDesc('at')
            ->values();

        return view('admin.driver.show', compact('driver', 'compliance', 'uploadedDocuments', 'activityLogs'));
    }

    public function edit($id, Request $request)
    {
        $driver = Driver::with([
            'company',
            'currentLicense',
            'driver_documents',
            'residence_addresses' => fn ($query) => $query->orderByDesc('is_current')->orderBy('id'),
            'experiences',
            'accidents' => fn ($query) => $query->orderByDesc('accident_date'),
            'violations' => fn ($query) => $query->orderByDesc('violation_date'),
            'forfeitures',
            'employment_records' => fn ($query) => $query->orderByDesc('employer_record_date_to'),
        ])->findOrFail($id);

        Gate::authorize('update', $driver);

        return view('admin.driver.edit', [
            'form' => DriverForm::for($driver),
            'isEditMode' => $request->query('edit') === '1',
        ]);
    }

    public function update(UpdateDriverRequest $request, $id, DriverCrudService $crud)
    {
        $driver = Driver::with([
            'licenses',
            'experiences',
            'accidents',
            'violations',
            'forfeitures',
            'employment_records',
            'residence_addresses',
        ])->findOrFail($id);

        Gate::authorize('update', $driver);

        try {
            $crud->update($driver, $request->validated(), $request->file('photo'));

            toastr()->success('Driver updated successfully!');

            return redirect()->route('admin.driver.license', [
                'driver_id' => $id,
                'edit' => '1',
            ]);
        } catch (LimitReached $e) {
            throw $e;
        } catch (Exception $e) {
            Log::error('Driver update failed: '.$e->getMessage(), [
                'exception' => $e,
                'driver_id' => $id,
                'request_data' => $request->except(['ssn', 'license_number']),
            ]);

            toastr()->error('Failed to update driver. Please try again.');

            return back()->withInput()->withErrors([
                'system_error' => 'An error occurred while updating the driver. Please try again.',
            ]);
        }
    }

    public function destroy($id)
    {
        $driver = Driver::findOrFail($id);
        Gate::authorize('delete', $driver);

        try {
            DB::transaction(function () use ($driver) {
                // A hard delete used to cascade to the driver's asset groups; hide them the same way.
                AssetGroup::where('driver_id', $driver->id)->delete();
                $driver->delete();
            });

            return response()->json(['success' => true, 'message' => 'Driver deleted successfully']);
        } catch (Exception $e) {
            Log::error('Driver delete failed: '.$e->getMessage(), [
                'exception' => $e,
                'driver_id' => $driver->id,
            ]);

            return response()->json(['success' => false, 'message' => 'Failed to delete driver. Please try again.'], 500);
        }
    }

    public function getDriverDetails($id)
    {
        $driver = Driver::findOrFail($id);
        Gate::authorize('view', $driver);

        return response()->json([
            'success' => true,
            'data' => [
                'main_phone' => $driver->main_phone,
                'alt_phone' => $driver->alt_phone,
                'email' => $driver->email,
            ],
        ]);
    }

    public function file($id, string $field, DriverDocumentWizardService $documents)
    {
        $driver = Driver::findOrFail($id);
        Gate::authorize('view', $driver);

        return $documents->fileResponse($driver, $field);
    }

    public function license($driver_id, Request $request)
    {
        $driver = $this->loadWizardDriver($driver_id);
        $currentStep = 2;
        $driver_document = DriverDocument::where('driver_id', $driver_id)->first();
        $isEditMode = $request->has('edit') && $request->edit == '1';

        return view('admin.driver.license', compact('currentStep', 'driver_id', 'driver', 'driver_document', 'isEditMode'));
    }

    public function licenseStore(Request $request, DriverDocumentWizardService $documents)
    {
        $existing = DriverDocument::where('driver_id', $request->driver_id)->first();
        $frontRule = ($existing && $existing->license_front) ? 'nullable' : 'required';
        $backRule = ($existing && $existing->license_back) ? 'nullable' : 'required';

        $validator = Validator::make($request->all(), [
            'license_front' => $frontRule.'|image|mimes:jpg,jpeg,png,webp|max:5120',
            'license_back' => $backRule.'|image|mimes:jpg,jpeg,png,webp|max:5120',
            'driver_id' => ['required', TenantRule::exists('drivers')],
        ]);

        if ($validator->fails()) {
            foreach ($validator->errors()->all() as $error) {
                toastr()->error($error);
            }

            return back()->withInput();
        }

        try {
            $driver = $this->loadWizardDriver((int) $request->driver_id);
            $documents->saveLicense($driver, $request->file('license_front'), $request->file('license_back'));

            toastr()->success('License documents saved successfully!');

            return $this->wizardRedirect('admin.driver.medical.card', (int) $request->driver_id, $this->isEditFlow($request));
        } catch (LimitReached $e) {
            throw $e;
        } catch (Exception $e) {
            Log::error('License Upload Error: '.$e->getMessage(), [
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);

            toastr()->error('Something went wrong while uploading the license.');

            return back()->withInput();
        }
    }

    public function medicalCard($driver_id, Request $request)
    {
        $driver = $this->loadWizardDriver($driver_id);
        $currentStep = 3;
        $driver_document = DriverDocument::where('driver_id', $driver_id)->first();
        $isEditMode = $request->has('edit') && $request->edit == '1';

        return view('admin.driver.medical-card', compact('currentStep', 'driver_id', 'driver', 'driver_document', 'isEditMode'));
    }

    public function medicalCardStore(Request $request, DriverDocumentWizardService $documents)
    {
        $request->validate(['driver_id' => ['required', TenantRule::exists('drivers')]]);
        $driver = $this->loadWizardDriver((int) $request->driver_id);
        // The card's expiry files the medical examiner's certificate (F17)
        $validated = $request->validate(DriverDocumentWizardService::medicalCardRules($driver));

        try {
            $documents->saveMedicalCard($driver, $request->file('medical_card'), $validated['medical_certificate_expiration_date'] ?? null);

            toastr()->success('Medical card saved successfully!');

            return $this->wizardRedirect('admin.driver.forfeiture', (int) $request->driver_id, $this->isEditFlow($request));
        } catch (LimitReached $e) {
            throw $e;
        } catch (Exception $e) {
            Log::error('Medical Card Upload Error: '.$e->getMessage(), [
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);

            return redirect()
                ->back()
                ->with('error', 'Something went wrong while uploading the medical card.');
        }
    }

    public function forfeiture($driver_id, Request $request)
    {
        $driver = $this->loadWizardDriver($driver_id);
        $currentStep = 4;
        $driver_document = DriverDocument::where('driver_id', $driver_id)->first();
        $isEditMode = $request->has('edit') && $request->edit == '1';

        return view('admin.driver.forfeiture', compact('currentStep', 'driver_id', 'driver', 'driver_document', 'isEditMode'));
    }

    public function forfeitureStore(Request $request, DriverDocumentWizardService $documents)
    {
        $request->validate([
            'forfeiture_document' => 'nullable|image|mimes:jpg,jpeg,png,webp|max:2048',
            'driver_id' => ['required', TenantRule::exists('drivers')],
        ]);

        try {
            $driver = $this->loadWizardDriver((int) $request->driver_id);
            $documents->saveForfeiture($driver, $request->file('forfeiture_document'));

            toastr()->success('Forfeiture document saved successfully!');

            return $this->wizardRedirect('admin.driver.violation', (int) $request->driver_id, $this->isEditFlow($request));
        } catch (LimitReached $e) {
            throw $e;
        } catch (Exception $e) {
            Log::error('Forfeiture Upload Error: '.$e->getMessage(), [
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);

            return redirect()
                ->back()
                ->with('error', 'Something went wrong while uploading the forfeiture document.');
        }
    }

    public function violation($driver_id, Request $request)
    {
        $driver = $this->loadWizardDriver($driver_id);
        $currentStep = 5;
        $driver_document = DriverDocument::where('driver_id', $driver_id)->first();
        $isEditMode = $request->has('edit') && $request->edit == '1';

        return view('admin.driver.violation-record', compact('currentStep', 'driver_id', 'driver', 'driver_document', 'isEditMode'));
    }

    public function violationStore(Request $request, DriverDocumentWizardService $documents)
    {
        $validator = Validator::make($request->all(), [
            'driver_id' => ['required', TenantRule::exists('drivers')],
            'violation' => 'required|in:no,yes',
            'violation_date' => 'sometimes|array',
            'violation_date.*' => 'nullable|date',
            'violation_location' => 'sometimes|array',
            'violation_location.*' => 'nullable|string|max:255',
            'offense' => 'sometimes|array',
            'offense.*' => 'nullable|string|max:255',
            'vehicle_type' => 'sometimes|array',
            'vehicle_type.*' => 'nullable|string|max:255',
            'applicant_signature' => 'required|string|max:255',
            'date_signed' => 'required|date',
        ]);

        if ($validator->fails()) {
            foreach ($validator->errors()->all() as $error) {
                toastr()->error($error);
            }

            return back()->withInput();
        }

        try {
            $driver = $this->loadWizardDriver((int) $request->driver_id);
            $documents->saveViolationRecord($driver, $request->all());

            toastr()->success('Violation saved successfully!');

            return $this->wizardRedirect('admin.driver.alcohol.and.drug.test', (int) $request->driver_id, $this->isEditFlow($request));
        } catch (Exception $e) {
            Log::error('Violation Store Error: '.$e->getMessage(), [
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'request' => $request->except(['applicant_signature']),
            ]);

            return back()
                ->with('error', 'Something went wrong while saving the violation record.')
                ->withInput();
        }
    }

    public function alcoholAndDrugTest($driver_id, Request $request)
    {
        $driver = $this->loadWizardDriver($driver_id);
        $currentStep = 6;
        $driver_document = DriverDocument::where('driver_id', $driver_id)->first() ?? new DriverDocument;
        $isEditMode = $request->has('edit') && $request->edit == '1';

        return view('admin.driver.alcohol-and-drug-test', compact('currentStep', 'driver_id', 'driver', 'driver_document', 'isEditMode'));
    }

    public function alcoholAndDrugTestStore(Request $request, DriverDocumentWizardService $documents)
    {
        $validator = Validator::make($request->all(), [
            'driver_id' => ['required', TenantRule::exists('drivers')],
            'drug_test_question_1' => 'required|in:yes,no',
            'drug_test_question_2' => 'required|in:yes,no,n/a',
            'applicant_signature' => 'required|string|max:255',
            'date_signed' => 'required|date',
        ]);

        if ($validator->fails()) {
            foreach ($validator->errors()->all() as $error) {
                toastr()->error($error);
            }

            return back()->withInput();
        }

        try {
            $driver = $this->loadWizardDriver((int) $request->driver_id);
            $documents->saveAlcoholAndDrugTest($driver, $request->all());

            toastr()->success('Alcohol and drug test statement saved successfully.');

            return $this->wizardRedirect('admin.driver.fmcsa.consent', (int) $request->driver_id, $this->isEditFlow($request));
        } catch (Exception $e) {
            Log::error('Error saving alcohol and drug test statement: '.$e->getMessage(), [
                'driver_id' => $request->driver_id ?? 'N/A',
                'exception' => $e,
            ]);

            toastr()->error('An error occurred while saving the alcohol and drug test statement. Please try again.');

            return back()->withInput()->withErrors([
                'error' => 'An unexpected error occurred. Please try again.',
            ]);
        }
    }

    public function consent($driver_id, Request $request)
    {
        $driver = $this->loadWizardDriver($driver_id);
        $currentStep = 7;
        $driver_name = $driver->first_name.' '.$driver->last_name;
        $isEditMode = $request->has('edit') && $request->edit == '1';
        $driver_document = DriverDocument::where('driver_id', $driver_id)->first() ?? new DriverDocument;

        $authUser = Auth::user();
        if (! $authUser) {
            toastr()->error('Authenticated user not found.');

            return redirect()->back();
        }
        $authUser->loadMissing('company');

        return view('admin.driver.general-consent', compact('currentStep', 'driver_id', 'driver', 'driver_document', 'driver_name', 'authUser', 'isEditMode'));
    }

    public function consentStore(Request $request, DriverDocumentWizardService $documents)
    {
        $validator = Validator::make($request->all(), [
            'driver_id' => ['required', TenantRule::exists('drivers')],
            'employee_signature' => 'required|string|max:255',
            'consent_agreement' => 'required|in:1',
            'date_signed' => 'required|date',
        ], [
            'consent_agreement.required' => 'You must agree to the consent terms by checking the box.',
            'consent_agreement.in' => 'You must agree to the consent terms.',
        ]);

        if ($validator->fails()) {
            foreach ($validator->errors()->all() as $error) {
                toastr()->error($error);
            }

            return back()->withInput();
        }

        try {
            $driver = $this->loadWizardDriver((int) $request->driver_id);
            $documents->saveFmcsaConsent($driver, $request->all());

            toastr()->success('FMCSA Clearinghouse consent saved successfully.');

            return $this->wizardRedirect('admin.driver.psp', (int) $request->driver_id, $this->isEditFlow($request));
        } catch (Exception $e) {
            Log::error('Error saving FMCSA consent: '.$e->getMessage());
            toastr()->error('An error occurred while saving the FMCSA consent. Please try again.');

            return back()->withInput()->withErrors([
                'error' => 'An unexpected error occurred. Please try again.',
            ]);
        }
    }

    public function psp($driver_id, Request $request)
    {
        $driver = $this->loadWizardDriver($driver_id);
        $currentStep = 8;
        $driver_document = DriverDocument::where('driver_id', $driver_id)->first() ?? new DriverDocument;
        $isEditMode = $request->has('edit') && $request->edit == '1';

        $authUser = Auth::user();
        if (! $authUser) {
            toastr()->error('Authenticated user not found.');

            return redirect()->back();
        }
        $authUser->loadMissing('company');

        return view('admin.driver.psp-driver-disclosure', compact('currentStep', 'driver_id', 'driver', 'driver_document', 'authUser', 'isEditMode'));
    }

    public function pspStore(Request $request, DriverDocumentWizardService $documents)
    {
        $validator = Validator::make($request->all(), [
            'driver_id' => ['required', TenantRule::exists('drivers')],
            'applicant_signature' => 'required|string|max:255',
            'authorization_agreement' => 'required|in:1',
            'date_signed' => 'required|date',
        ], [
            'authorization_agreement.required' => 'You must agree to the authorization terms by checking the box.',
            'authorization_agreement.in' => 'You must agree to the authorization terms.',
        ]);

        if ($validator->fails()) {
            foreach ($validator->errors()->all() as $error) {
                toastr()->error($error);
            }

            return back()->withInput();
        }

        try {
            $driver = $this->loadWizardDriver((int) $request->driver_id);
            $documents->savePspAuthorization($driver, $request->all());

            toastr()->success('PSP Driver Disclosure & Authorization saved successfully.');

            return $this->wizardRedirect('admin.driver.alcohol.and.drug.test.policy', (int) $request->driver_id, $this->isEditFlow($request));
        } catch (Exception $e) {
            Log::error('Error saving PSP authorization: '.$e->getMessage(), [
                'driver_id' => $request->driver_id ?? 'N/A',
                'exception' => $e,
            ]);

            toastr()->error('An error occurred while saving the PSP authorization. Please try again.');

            return back()->withInput()->withErrors([
                'error' => 'An unexpected error occurred. Please try again.',
            ]);
        }
    }

    public function alcoholAndDrugTestPolicy($driver_id, Request $request)
    {
        $driver = $this->loadWizardDriver($driver_id);
        $currentStep = 9;
        $driver_name = $driver->first_name.' '.$driver->last_name;
        $driver_document = DriverDocument::where('driver_id', $driver_id)->first() ?? new DriverDocument;
        $isEditMode = $request->has('edit') && $request->edit == '1';

        $authUser = Auth::user();
        if (! $authUser) {
            toastr()->error('Authenticated user not found.');

            return redirect()->back();
        }
        $authUser->loadMissing('company');

        $policyPdf = PolicyPdf::resolveFor($driver->company_id);

        return view('admin.driver.alcohol-and-drug-test-policy', compact('currentStep', 'driver_id', 'driver', 'driver_document', 'driver_name', 'authUser', 'policyPdf', 'isEditMode'));
    }

    public function alcoholAndDrugTestPolicyStore(Request $request, DriverDocumentWizardService $documents)
    {
        $validator = Validator::make($request->all(), [
            'driver_id' => ['required', TenantRule::exists('drivers')],
            'employee_signature' => 'required|string|max:255',
            'date_signed' => 'required|date',
        ]);

        if ($validator->fails()) {
            foreach ($validator->errors()->all() as $error) {
                toastr()->error($error);
            }

            return back()->withInput();
        }

        try {
            $driver = $this->loadWizardDriver((int) $request->driver_id);
            $documents->saveAlcoholAndDrugTestPolicy($driver, $request->all());

            toastr()->success('Alcohol & Drug Testing Policy saved successfully.');

            return $this->wizardRedirect('admin.driver.general.work.policy', (int) $request->driver_id, $this->isEditFlow($request));
        } catch (Exception $e) {
            Log::error('Error saving Alcohol & Drug Testing Policy: '.$e->getMessage());
            toastr()->error('An error occurred while saving the Alcohol & Drug Testing Policy. Please try again.');

            return back()->withInput()->withErrors([
                'error' => 'An unexpected error occurred. Please try again.',
            ]);
        }
    }

    public function generalWorkPolicy($driver_id, Request $request)
    {
        $driver = $this->loadWizardDriver($driver_id);
        $currentStep = 10;
        $driver_name = $driver->first_name.' '.$driver->last_name;
        $driver_document = DriverDocument::where('driver_id', $driver_id)->first() ?? new DriverDocument;
        $isEditMode = $request->has('edit') && $request->edit == '1';

        $authUser = Auth::user();
        if (! $authUser) {
            toastr()->error('Authenticated user not found.');

            return redirect()->back();
        }
        $authUser->loadMissing('company');

        $policyPdf = PolicyPdf::resolveFor($driver->company_id);

        return view('admin.driver.general-work-policy', compact('currentStep', 'driver_id', 'driver', 'driver_document', 'driver_name', 'authUser', 'policyPdf', 'isEditMode'));
    }

    public function generalWorkPolicyStore(Request $request, DriverDocumentWizardService $documents)
    {
        $validator = Validator::make($request->all(), [
            'driver_id' => ['required', TenantRule::exists('drivers')],
            'employee_signature' => 'required|string|max:255',
            'date_signed' => 'required|date',
        ]);

        if ($validator->fails()) {
            foreach ($validator->errors()->all() as $error) {
                toastr()->error($error);
            }

            return back()->withInput();
        }

        try {
            $driver = $this->loadWizardDriver((int) $request->driver_id);
            $fromEdit = $this->isEditFlow($request);
            $documents->saveGeneralWorkPolicy($driver, $request->all(), finalizeToPending: ! $fromEdit);

            toastr()->success($fromEdit
                ? 'General Work Policy updated successfully.'
                : 'General Work Policy saved successfully.');

            return redirect()->route('admin.driver.index');
        } catch (Exception $e) {
            Log::error('Error saving General Work Policy: '.$e->getMessage());
            toastr()->error('An error occurred while saving the General Work Policy. Please try again.');

            return back()->withInput()->withErrors([
                'error' => 'An unexpected error occurred. Please try again.',
            ]);
        }
    }

    public function updateHireStatus(Request $request, Driver $driver, DriverHireService $hireService)
    {
        Gate::authorize('update', $driver);

        if (! Auth::user()->can('drivers.hire')) {
            return response()->json([
                'success' => false,
                'message' => 'You are not authorized to perform this action.',
            ], 403);
        }

        $validator = Validator::make($request->all(), [
            'action' => 'required|in:hire,reject',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid action.',
            ], 422);
        }

        $action = $request->action;

        if ($action === 'hire') {
            $validator = Validator::make($request->all(), [
                'hire_date' => 'required|date',
                'hazmat' => 'required|in:yes,no',
                'lcv_certificate' => 'required|in:yes,no',
            ]);
        } else {
            $validator = Validator::make($request->all(), [
                'rejection_reason' => 'required|in:not_good_fit,failed_drug_test,background_check_issues,cdl_issues,mvr_issues,psp_issues,other',
                'additional_info' => 'nullable|string|max:1000',
                'record_date' => 'required|date',
            ]);
        }

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => $validator->errors()->first(),
            ], 422);
        }

        try {
            if ($action === 'hire') {
                $driver = $hireService->hire($driver, $request->all(), Auth::id());
                $message = 'Driver has been hired successfully!';
            } else {
                $driver = $hireService->reject($driver, $request->all(), Auth::id());
                $message = 'Driver has been marked as not hired.';
            }

            $status = $driver->application_status === 'rejected' ? 'rejected' : $driver->employment_status;

            return response()->json([
                'success' => true,
                'message' => $message,
                'data' => [
                    'status' => $status,
                    'hire_date' => $driver->hire_date,
                    'rejection_reason' => $hireService->getRejectionReasonLabel($driver->rejection_reason),
                    'status_label' => $hireService->getStatusLabel($status),
                ],
            ]);
        } catch (InvalidArgumentException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        } catch (LimitReached $e) {
            throw $e;
        } catch (Exception $e) {
            Log::error('Failed to update driver hire status: '.$e->getMessage(), [
                'driver_id' => $driver->id,
                'action' => $action,
                'error' => $e,
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to update driver status. Please try again.',
            ], 500);
        }
    }

    private function isEditFlow(Request $request): bool
    {
        return $request->has('from_edit') && $request->from_edit == '1';
    }

    private function wizardRedirect(string $routeName, int $driverId, bool $edit): RedirectResponse
    {
        $params = ['driver_id' => $driverId];
        if ($edit) {
            $params['edit'] = '1';
        }

        return redirect()->route($routeName, $params);
    }

    /**
     * @param  int|string  $driverId  the {driver_id} route segment or the posted driver_id
     */
    private function loadWizardDriver($driverId): Driver
    {
        // Route segments are strings; a non-numeric id is a 404, not a TypeError (DRV-09).
        abort_unless(ctype_digit((string) $driverId), 404);

        $driver = Driver::findOrFail((int) $driverId);
        Gate::authorize('update', $driver);

        return $driver;
    }
}
