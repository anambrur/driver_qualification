<?php

namespace App\Http\Controllers;

use App\Http\Requests\Driver\StoreDriverRequest;
use App\Http\Requests\Driver\UpdateDriverRequest;
use App\Models\Country;
use App\Models\Driver;
use App\Models\DriverDocument;
use App\Models\PolicyPdf;
use App\Models\State;
use App\Services\Driver\DriverCrudService;
use App\Services\Driver\DriverDocumentWizardService;
use App\Services\Driver\DriverHireService;
use App\Traits\CompanyFilterTrait;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use InvalidArgumentException;
use Yajra\DataTables\Facades\DataTables;

class DriverController extends Controller
{
    use CompanyFilterTrait;

    public function index(Request $request)
    {
        // Get base query with company filtering
        $baseQuery = Driver::query();
        $baseQuery = $this->applyCompanyFilter($baseQuery);

        // Exclude draft drivers for non-super-admin users
        if (!Auth::user()->hasRole('super-admin')) {
            $baseQuery->where('status', '!=', 'draft');
        }

        // Calculate status counts
        $statusCounts = [
            'all' => (clone $baseQuery)->count(),
            'draft' => (clone $baseQuery)->where('status', 'draft')->count(),
            'pending' => (clone $baseQuery)->where('status', 'pending')->count(),
            'active' => (clone $baseQuery)->where('status', 'active')->count(),
            'inactive' => (clone $baseQuery)->where('status', 'inactive')->count(),
            'rejected' => (clone $baseQuery)->where('status', 'rejected')->count(),
        ];

        // Check if it's an AJAX request for DataTables
        if ($request->ajax()) {
            $drivers = Driver::with(['company', 'licenses' => function ($query) {
                $query->latest('expires');
            }])->select('drivers.*');

            // Apply company filter
            $drivers = $this->applyCompanyFilter($drivers);

            // Exclude draft drivers for non-super-admin users
            if (!Auth::user()->hasRole('super-admin')) {
                $drivers->where('status', '!=', 'draft');
            }

            // Apply status filter if provided
            if ($request->has('status') && $request->status && $request->status !== 'all') {
                $drivers->where('status', $request->status);
            }

            return DataTables::of($drivers)
                ->addIndexColumn()
                ->addColumn('full_name', function ($driver) {
                    return $driver->first_name . ' ' .
                        ($driver->middle_name ? $driver->middle_name . ' ' : '') .
                        $driver->last_name .
                        ($driver->suffix ? ' ' . $driver->suffix : '');
                })
                ->addColumn('company_name', function ($driver) {
                    return $driver->company ? $driver->company->company_name : 'N/A';
                })
                ->addColumn('state', function ($driver) {
                    return $driver->state ?? 'N/A';
                })
                ->addColumn('hired_at', function ($driver) {
                    return $driver->hired_at ? $driver->hired_at : 'Not Hired';
                })
                ->addColumn('license_expiration_date', function ($driver) {
                    if (isset($driver->licenses) && $driver->licenses->isNotEmpty()) {
                        $latestLicense = $driver->licenses->sortByDesc('expires')->first();
                        return $latestLicense->expires ?
                            $latestLicense->expires : 'N/A';
                    }
                    return 'N/A';
                })
                ->addColumn('medical_certificate_expiration_date', function ($driver) {
                    return $driver->medical_certificate_expiration_date ?
                        $driver->medical_certificate_expiration_date : 'N/A';
                })
                ->addColumn('status', function ($driver) {
                    $statusConfigs = [
                        'draft' => [
                            'label' => 'Draft',
                            'classes' => 'bg-gray-100 text-gray-800 dark:bg-gray-700 dark:text-gray-300'
                        ],
                        'pending' => [
                            'label' => 'Pending',
                            'classes' => 'bg-yellow-100 text-yellow-800 dark:bg-yellow-900 dark:text-yellow-200'
                        ],
                        'active' => [
                            'label' => 'Active',
                            'classes' => 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200'
                        ],
                        'inactive' => [
                            'label' => 'Inactive',
                            'classes' => 'bg-gray-100 text-gray-800 dark:bg-gray-700 dark:text-gray-300'
                        ],
                        'submitted' => [
                            'label' => 'Submitted',
                            'classes' => 'bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-200'
                        ],
                        'under_review' => [
                            'label' => 'Under Review',
                            'classes' => 'bg-yellow-100 text-yellow-800 dark:bg-yellow-900 dark:text-yellow-200'
                        ],
                        'approved' => [
                            'label' => 'Approved',
                            'classes' => 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200'
                        ],
                        'rejected' => [
                            'label' => 'Rejected',
                            'classes' => 'bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-200'
                        ],
                    ];

                    $config = $statusConfigs[$driver->status] ?? [
                        'label' => ucfirst($driver->status),
                        'classes' => 'bg-gray-100 text-gray-800 dark:bg-gray-700 dark:text-gray-300'
                    ];

                    return '<span class="inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium ' . $config['classes'] . '">' . $config['label'] . '</span>';
                })
                ->addColumn('action', function ($driver) {
                    return '<div class="flex items-center space-x-2">
                <a href="' . route('admin.driver.show', $driver->id) . '" 
                   class="inline-flex h-8 w-8 items-center justify-center rounded-lg border border-gray-300 bg-white text-gray-700 shadow-theme-xs hover:bg-gray-50 focus:outline-hidden focus:ring-2 focus:ring-gray-500/20 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-400 dark:hover:bg-gray-700" 
                   title="View">
                    <i class="fas fa-eye text-xs"></i>
                </a>
                <a href="' . route('admin.driver.edit', $driver->id) . '" 
                   class="inline-flex h-8 w-8 items-center justify-center rounded-lg border border-gray-300 bg-white text-gray-700 shadow-theme-xs hover:bg-gray-50 focus:outline-hidden focus:ring-2 focus:ring-gray-500/20 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-400 dark:hover:bg-gray-700" 
                   title="Edit">
                    <i class="fas fa-edit text-xs"></i>
                </a>
                <button type="button" 
                        onclick="deleteDriver(' . $driver->id . ', \'' . addslashes($driver->first_name . ' ' . $driver->last_name) . '\')" 
                        class="inline-flex h-8 w-8 items-center justify-center rounded-lg border border-gray-300 bg-white text-gray-700 shadow-theme-xs hover:bg-red-50 hover:text-red-600 focus:outline-hidden focus:ring-2 focus:ring-gray-500/20 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-400 dark:hover:bg-red-900/30 dark:hover:text-red-400" 
                        title="Delete">
                    <i class="fas fa-trash text-xs"></i>
                </button>
            </div>';
                })
                ->rawColumns(['status', 'action'])
                ->filter(function ($query) use ($request) {
                    if ($request->has('search') && !empty($request->search['value'])) {
                        $search = $request->search['value'];
                        $query->where(function ($q) use ($search) {
                            $q->where('first_name', 'like', "%{$search}%")
                                ->orWhere('last_name', 'like', "%{$search}%")
                                ->orWhere('middle_name', 'like', "%{$search}%")
                                ->orWhere('email', 'like', "%{$search}%")
                                ->orWhere('main_phone', 'like', "%{$search}%")
                                ->orWhere('state', 'like', "%{$search}%")
                                ->orWhereHas('company', function ($companyQuery) use ($search) {
                                    $companyQuery->where('company_name', 'like', "%{$search}%")
                                        ->orWhere('company_name', 'like', "%{$search}%")
                                        ->orWhere('business_name', 'like', "%{$search}%");
                                });
                        });
                    }
                })
                ->order(function ($query) use ($request) {
                    if ($request->has('order') && isset($request->order[0])) {
                        $columnIndex = $request->order[0]['column'];
                        $direction = $request->order[0]['dir'];

                        // Map DataTables columns to database columns
                        switch ($columnIndex) {
                            case 0: // #
                                $query->orderBy('id', $direction);
                                break;
                            case 1: // Full Name
                                $query->orderBy('first_name', $direction)
                                    ->orderBy('last_name', $direction);
                                break;
                            case 2: // Company
                                $query->orderBy('company_id', $direction);
                                break;
                            case 3: // Status
                                $query->orderBy('status', $direction);
                                break;
                            case 4: // State
                                $query->orderBy('state', $direction);
                                break;
                            case 5: // License Exp.
                                $query->orderBy('id', $direction);
                                break;
                            case 6: // Medical Exp.
                                $query->orderBy('medical_certificate_expiration_date', $direction);
                                break;
                            case 7: // Hire Date
                                $query->orderBy('hired_at', $direction);
                                break;
                            default:
                                $query->orderBy('id', $direction);
                        }
                    } else {
                        $query->orderBy('id', 'desc');
                    }
                })
                ->make(true);
        }

        // Get companies for dropdown (filtered by user role)
        $companies = $this->getCompaniesForUser();

        // For the initial page load, pass status counts to the view
        return view('admin.driver.index', compact('statusCounts', 'companies'));
    }

    public function create()
    {
        $companies = $this->getCompaniesForUser();

        if ($companies->isEmpty() && !Auth::user()->hasRole('super-admin')) {
            toastr()->error('You do not have a company assigned.');
            return redirect()->route('admin.driver.index');
        }

        $countries = Country::orderBy('name')->get();
        $defaultCountry = Country::where('iso_code', 'US')->first();
        $states = $defaultCountry ? $defaultCountry->states()->orderBy('name')->get() : collect();
        $currentStep = 1;

        return view('admin.driver.create', compact('companies', 'countries', 'states', 'defaultCountry', 'currentStep'));
    }

    public function store(StoreDriverRequest $request, DriverCrudService $crud)
    {
        try {
            $driver = $crud->create($request->validated(), Auth::id(), $request->file('photo'));

            toastr()->success('Driver created successfully!');
            return redirect()->route('admin.driver.license', ['driver_id' => $driver->id]);
        } catch (Exception $e) {
            Log::error('Driver creation failed: ' . $e->getMessage(), [
                'exception' => $e,
                'request_data' => $request->except(['ssn', 'license_number']),
            ]);

            toastr()->error('Failed to create driver. Please try again.');
            return back()->withInput()->withErrors([
                'system_error' => 'An error occurred while creating the driver. Please try again.',
            ]);
        }
    }

    public function show($id)
    {
        $driver = Driver::with([
            'company',
            'licenses' => function ($query) {
                $query->orderBy('expires', 'desc');
            },
            'driver_documents',
            'residence_addresses' => function ($query) {
                $query->orderBy('is_current', 'desc')->orderBy('created_at', 'desc');
            },
        ])->findOrFail($id);

        $this->authorizeCompanyAccess($driver, 'You do not have permission to view this driver.');

        return view('admin.driver.show', compact('driver'));
    }

    public function edit($id, Request $request)
    {
        $driver = Driver::with([
            'company',
            'licenses' => function ($query) {
                $query->orderBy('expires', 'desc')->limit(1);
            },
            'residence_addresses' => function ($query) {
                $query->orderBy('is_current', 'desc');
            },
            'experiences',
            'accidents' => function ($query) {
                $query->orderBy('accident_date', 'desc');
            },
            'violations' => function ($query) {
                $query->orderBy('violation_date', 'desc');
            },
            'forfeitures',
            'employment_records' => function ($query) {
                $query->orderBy('employer_record_date_to', 'desc');
            },
        ])->findOrFail($id);

        $this->authorizeCompanyAccess($driver, 'You do not have permission to edit this driver.');

        $currentStep = 1;
        $isEditMode = $request->has('edit') && $request->edit == '1';
        $companies = $this->getCompaniesForUser();
        $countries = Country::orderBy('name')->get();
        $states = State::where('country_id', Country::where('iso_code', 'US')->first()->id ?? 1)
            ->orderBy('name')
            ->get();

        return view('admin.driver.edit', compact('currentStep', 'driver', 'companies', 'countries', 'states', 'isEditMode'));
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

        $this->authorizeCompanyAccess($driver, 'You do not have permission to update this driver.');

        try {
            $crud->update($driver, $request->validated(), $request->file('photo'));

            toastr()->success('Driver updated successfully!');

            return redirect()->route('admin.driver.license', [
                'driver_id' => $id,
                'edit' => '1',
            ]);
        } catch (Exception $e) {
            Log::error('Driver update failed: ' . $e->getMessage(), [
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
        try {
            $driver = Driver::findOrFail($id);
            $this->authorizeCompanyAccess($driver, 'You do not have permission to delete this driver.');
            $driver->delete();

            return response()->json(['success' => true, 'message' => 'Driver deleted successfully']);
        } catch (Exception $e) {
            return response()->json(['success' => false, 'message' => 'Error deleting driver: ' . $e->getMessage()], 500);
        }
    }

    public function updateStatus(Request $request, $id)
    {
        $driver = Driver::findOrFail($id);
        $this->authorizeCompanyAccess($driver);

        $validator = Validator::make($request->all(), [
            'status' => 'required|in:draft,submitted,under_review,approved,rejected',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => $validator->errors()->first(),
            ], 422);
        }

        try {
            $driver->update(['status' => $request->status]);

            return response()->json([
                'success' => true,
                'message' => 'Driver status updated successfully!',
            ]);
        } catch (Exception $e) {
            Log::error('Driver status update failed: ' . $e->getMessage(), [
                'exception' => $e,
                'driver_id' => $id,
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to update driver status. Please try again.',
            ], 500);
        }
    }

    public function getDriverDetails($id)
    {
        $driver = Driver::findOrFail($id);
        $this->authorizeCompanyAccess($driver);

        return response()->json([
            'success' => true,
            'data' => [
                'main_phone' => $driver->main_phone,
                'alt_phone' => $driver->alt_phone,
                'email' => $driver->email,
            ],
        ]);
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
            'license_front' => $frontRule . '|image|mimes:jpg,jpeg,png,webp|max:5120',
            'license_back' => $backRule . '|image|mimes:jpg,jpeg,png,webp|max:5120',
            'driver_id' => 'required|exists:drivers,id',
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
        } catch (Exception $e) {
            Log::error('License Upload Error: ' . $e->getMessage(), [
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
        $request->validate([
            'medical_card' => 'nullable|image|mimes:jpg,jpeg,png,webp|max:2048',
            'driver_id' => 'required|exists:drivers,id',
        ]);

        try {
            $driver = $this->loadWizardDriver((int) $request->driver_id);
            $documents->saveMedicalCard($driver, $request->file('medical_card'));

            toastr()->success('Medical card saved successfully!');

            return $this->wizardRedirect('admin.driver.forfeiture', (int) $request->driver_id, $this->isEditFlow($request));
        } catch (Exception $e) {
            Log::error('Medical Card Upload Error: ' . $e->getMessage(), [
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
            'driver_id' => 'required|exists:drivers,id',
        ]);

        try {
            $driver = $this->loadWizardDriver((int) $request->driver_id);
            $documents->saveForfeiture($driver, $request->file('forfeiture_document'));

            toastr()->success('Forfeiture document saved successfully!');

            return $this->wizardRedirect('admin.driver.violation', (int) $request->driver_id, $this->isEditFlow($request));
        } catch (Exception $e) {
            Log::error('Forfeiture Upload Error: ' . $e->getMessage(), [
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
            'driver_id' => 'required|exists:drivers,id',
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
            Log::error('Violation Store Error: ' . $e->getMessage(), [
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
        $driver_document = DriverDocument::where('driver_id', $driver_id)->first() ?? new DriverDocument();
        $isEditMode = $request->has('edit') && $request->edit == '1';

        return view('admin.driver.alcohol-and-drug-test', compact('currentStep', 'driver_id', 'driver', 'driver_document', 'isEditMode'));
    }

    public function alcoholAndDrugTestStore(Request $request, DriverDocumentWizardService $documents)
    {
        $validator = Validator::make($request->all(), [
            'driver_id' => 'required|exists:drivers,id',
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
            Log::error('Error saving alcohol and drug test statement: ' . $e->getMessage(), [
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
        $driver_name = $driver->first_name . ' ' . $driver->last_name;
        $isEditMode = $request->has('edit') && $request->edit == '1';
        $driver_document = DriverDocument::where('driver_id', $driver_id)->first() ?? new DriverDocument();

        $authUser = Auth::user();
        if (!$authUser) {
            toastr()->error('Authenticated user not found.');
            return redirect()->back();
        }
        $authUser->loadMissing('company');

        return view('admin.driver.general-consent', compact('currentStep', 'driver_id', 'driver', 'driver_document', 'driver_name', 'authUser', 'isEditMode'));
    }

    public function consentStore(Request $request, DriverDocumentWizardService $documents)
    {
        $validator = Validator::make($request->all(), [
            'driver_id' => 'required|exists:drivers,id',
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
            Log::error('Error saving FMCSA consent: ' . $e->getMessage());
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
        $driver_document = DriverDocument::where('driver_id', $driver_id)->first() ?? new DriverDocument();
        $isEditMode = $request->has('edit') && $request->edit == '1';

        $authUser = Auth::user();
        if (!$authUser) {
            toastr()->error('Authenticated user not found.');
            return redirect()->back();
        }
        $authUser->loadMissing('company');

        return view('admin.driver.psp-driver-disclosure', compact('currentStep', 'driver_id', 'driver', 'driver_document', 'authUser', 'isEditMode'));
    }

    public function pspStore(Request $request, DriverDocumentWizardService $documents)
    {
        $validator = Validator::make($request->all(), [
            'driver_id' => 'required|exists:drivers,id',
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
            Log::error('Error saving PSP authorization: ' . $e->getMessage(), [
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
        $driver_name = $driver->first_name . ' ' . $driver->last_name;
        $driver_document = DriverDocument::where('driver_id', $driver_id)->first() ?? new DriverDocument();
        $isEditMode = $request->has('edit') && $request->edit == '1';

        $authUser = Auth::user();
        if (!$authUser) {
            toastr()->error('Authenticated user not found.');
            return redirect()->back();
        }
        $authUser->loadMissing('company');

        $policyPdf = PolicyPdf::first() ?? new PolicyPdf();

        return view('admin.driver.alcohol-and-drug-test-policy', compact('currentStep', 'driver_id', 'driver', 'driver_document', 'driver_name', 'authUser', 'policyPdf', 'isEditMode'));
    }

    public function alcoholAndDrugTestPolicyStore(Request $request, DriverDocumentWizardService $documents)
    {
        $validator = Validator::make($request->all(), [
            'driver_id' => 'required|exists:drivers,id',
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
            Log::error('Error saving Alcohol & Drug Testing Policy: ' . $e->getMessage());
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
        $driver_name = $driver->first_name . ' ' . $driver->last_name;
        $driver_document = DriverDocument::where('driver_id', $driver_id)->first() ?? new DriverDocument();
        $isEditMode = $request->has('edit') && $request->edit == '1';

        $authUser = Auth::user();
        if (!$authUser) {
            toastr()->error('Authenticated user not found.');
            return redirect()->back();
        }
        $authUser->loadMissing('company');

        $policyPdf = PolicyPdf::first() ?? new PolicyPdf();

        return view('admin.driver.general-work-policy', compact('currentStep', 'driver_id', 'driver', 'driver_document', 'driver_name', 'authUser', 'policyPdf', 'isEditMode'));
    }

    public function generalWorkPolicyStore(Request $request, DriverDocumentWizardService $documents)
    {
        $validator = Validator::make($request->all(), [
            'driver_id' => 'required|exists:drivers,id',
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
            $documents->saveGeneralWorkPolicy($driver, $request->all(), finalizeToPending: !$fromEdit);

            toastr()->success($fromEdit
                ? 'General Work Policy updated successfully.'
                : 'General Work Policy saved successfully.');

            return redirect()->route('admin.driver.index');
        } catch (Exception $e) {
            Log::error('Error saving General Work Policy: ' . $e->getMessage());
            toastr()->error('An error occurred while saving the General Work Policy. Please try again.');
            return back()->withInput()->withErrors([
                'error' => 'An unexpected error occurred. Please try again.',
            ]);
        }
    }

    public function updateHireStatus(Request $request, Driver $driver, DriverHireService $hireService)
    {
        $this->authorizeCompanyAccess($driver);

        if (!Auth::user()->can('drivers.hire')) {
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

            return response()->json([
                'success' => true,
                'message' => $message,
                'data' => [
                    'status' => $driver->status,
                    'hire_date' => $driver->hire_date,
                    'rejection_reason' => $hireService->getRejectionReasonLabel($driver->rejection_reason),
                    'status_label' => $hireService->getStatusLabel($driver->status),
                ],
            ]);
        } catch (InvalidArgumentException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        } catch (Exception $e) {
            Log::error('Failed to update driver hire status: ' . $e->getMessage(), [
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

    private function wizardRedirect(string $routeName, int $driverId, bool $edit): \Illuminate\Http\RedirectResponse
    {
        $params = ['driver_id' => $driverId];
        if ($edit) {
            $params['edit'] = '1';
        }

        return redirect()->route($routeName, $params);
    }

    private function loadWizardDriver(int $driverId): Driver
    {
        $driver = Driver::findOrFail($driverId);
        $this->authorizeCompanyAccess($driver, 'Unauthorized action.');

        return $driver;
    }
}
