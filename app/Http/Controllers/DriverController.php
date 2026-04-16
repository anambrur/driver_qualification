<?php

namespace App\Http\Controllers;

use Exception;
use App\Models\Company;
use App\Models\Country;
use App\Models\Driver;
use App\Models\DriverDocument;
use App\Models\PolicyPdf;
use App\Models\State;
use App\Models\Violation;
use App\Traits\CompanyFilterTrait;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
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
        // Get companies based on user role
        $companies = $this->getCompaniesForUser();

        // If user has no company and is not super-admin, redirect
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

    public function store(Request $request)
    {
        // Add company validation based on user role
        $companyId = $this->getUserCompanyId();

        if (!Auth::user()->hasRole('super-admin')) {
            // Ensure the user is only creating drivers for their own company
            if ($companyId === null || $request->company_id != $companyId) {
                return response()->json([
                    'success' => false,
                    'message' => 'You can only create drivers for your own company.'
                ], 403);
            }
        }

        // Main driver validation - simplified to match form structure
        $validator = Validator::make($request->all(), [
            'company_id' => 'required|exists:companies,id',
            'first_name' => 'required|string|max:255',
            'middle_name' => 'nullable|string|max:255',
            'last_name' => 'required|string|max:255',
            'suffix' => 'nullable|string|max:10',
            'date_of_birth' => 'required|date|before:-18 years',
            'ssn' => 'required|string|max:11',
            'main_phone' => 'required|string|max:20',
            'alt_phone' => 'nullable|string|max:20',
            'email' => 'required|email|max:255',
            'medical_certificate_expiration_date' => 'required|date|after_or_equal:' . now()->format('Y-m-d'),
            'photo' => 'nullable|image|mimes:jpeg,png,jpg,gif,svg|max:2048',
            'business_name' => 'nullable|string|max:255',
            'employer_identification_number' => 'nullable|string|max:20',
            'federal_tax_classification' => 'nullable|in:individual_sole_proprietor,c_corporation,s_corporation,llc',
            'address' => 'required|string|max:500',
            'city' => 'required|string|max:255',
            'state' => 'required',
            'country' => 'required',
            'postal_code' => 'required|string|max:20',
            'twic_card' => 'sometimes|boolean',
            'passport' => 'sometimes|boolean',

            // License validation
            'license_first_name' => 'required|string|max:255',
            'license_last_name' => 'required|string|max:255',
            'license_issued' => 'required|date',
            'license_expires' => 'required|date|after:license_issued',
            'license_country' => 'required',
            'license_state' => 'required',
            'license_class' => 'required|string|max:255',
            'license_number' => 'required|string|max:255',
            'repeat_license_number' => 'required|same:license_number',
            'is_h_placarded_hazmat' => 'sometimes|boolean',
            'is_n_tank_vehicle' => 'sometimes|boolean',
            'is_p_passengers' => 'sometimes|boolean',
            'is_t_double_trailer' => 'sometimes|boolean',
            'is_s_school_bus' => 'sometimes|boolean',
            'is_x_placarded_hazmat' => 'sometimes|boolean',

            // Array validations - make more flexible
            'residence_address' => 'sometimes|array',
            'residence_address.*' => 'nullable|string',
            'residence_city' => 'sometimes|array',
            'residence_city.*' => 'nullable|string',
            'residence_country' => 'sometimes|array',
            'residence_country.*' => 'nullable',
            'residence_state' => 'sometimes|array',
            'residence_state.*' => 'nullable',
            'residence_postal_code' => 'sometimes|array',
            'residence_postal_code.*' => 'nullable|string',

            'equipment_class' => 'required|array',
            'equipment_class.*' => 'required|string',
            'experience' => 'required|array',
            'experience.*' => 'required|in:no,yes',
            'experience_from_date' => 'sometimes|array',
            'experience_from_date.*' => 'nullable|date',
            'experience_to_date' => 'sometimes|array',
            'experience_to_date.*' => 'nullable|date',
            'approx_miles' => 'sometimes|array',
            'approx_miles.*' => 'nullable|string',

            'accident' => 'required|in:no,yes',
            'accident_date' => 'sometimes|array',
            'accident_date.*' => 'nullable|date',
            'accident_location' => 'sometimes|array',
            'accident_location.*' => 'nullable|string',
            'number_of_injuries' => 'sometimes|array',
            'number_of_injuries.*' => 'nullable|string',
            'number_of_fatalities' => 'sometimes|array',
            'number_of_fatalities.*' => 'nullable|string',
            'hazmat_spill' => 'sometimes|array',
            'hazmat_spill.*' => 'nullable|in:no,yes',

            'violation' => 'required|in:no,yes',
            'violation_date' => 'sometimes|array',
            'violation_date.*' => 'nullable|date',
            'violation_location' => 'sometimes|array',
            'violation_location.*' => 'nullable|string',
            'offense' => 'sometimes|array',
            'offense.*' => 'nullable|string',
            'vehicle_type' => 'sometimes|array',
            'vehicle_type.*' => 'nullable|string',

            'denied_license' => 'required|in:no,yes',
            'license_revoked' => 'required|in:no,yes',
            'forfeitures' => 'nullable|string|max:1000',

            'employer_name' => 'sometimes|array',
            'employer_name.*' => 'nullable|string',
            'employer_record_address' => 'sometimes|array',
            'employer_record_address.*' => 'nullable|string',
            'employer_record_city' => 'sometimes|array',
            'employer_record_city.*' => 'nullable|string',
            'employer_record_country' => 'sometimes|array',
            'employer_record_country.*' => 'nullable',
            'employer_record_state' => 'sometimes|array',
            'employer_record_state.*' => 'nullable',
            'employer_record_postal_code' => 'sometimes|array',
            'employer_record_postal_code.*' => 'nullable|string',
            'employer_record_phone' => 'sometimes|array',
            'employer_record_phone.*' => 'nullable|string',
            'employer_record_fax' => 'sometimes|array',
            'employer_record_fax.*' => 'nullable|string',
            'employer_record_email' => 'sometimes|array',
            'employer_record_email.*' => 'nullable|email',
            'employer_record_position' => 'sometimes|array',
            'employer_record_position.*' => 'nullable|string',
            'employer_record_date_from' => 'sometimes|array',
            'employer_record_date_from.*' => 'nullable|date',
            'employer_record_date_to' => 'sometimes|array',
            'employer_record_date_to.*' => 'nullable|date',
            'employer_record_reason_for_leaving' => 'sometimes|array',
            'employer_record_reason_for_leaving.*' => 'nullable|string',
            'employed_regulations' => 'sometimes|array',
            'employed_regulations.*' => 'nullable|in:no,yes',
            'safety_sensitive_function' => 'sometimes|array',
            'safety_sensitive_function.*' => 'nullable|in:no,yes',
        ]);


        if ($validator->fails()) {
            foreach ($validator->errors()->all() as $error) {
                toastr()->error($error);
            }
            return back()->withInput();
        }

        DB::beginTransaction();

        try {
            // Format SSN
            $ssn = preg_replace('/[^0-9]/', '', $request->ssn);

            // Get country/state names from IDs
            $countryName = Country::find($request->country)?->name ?? $request->country;
            $stateName = $request->state ? (is_numeric($request->state) ?
                State::find($request->state)?->name : $request->state) : null;

            if ($request->hasFile('photo')) {
                $file = $request->file('photo');
                $extension = $file->getClientOriginalExtension();
                $fileName = 'driver_photo_' . time() . '.' . $extension;
                $photo = $file->storeAs('images/drivers', $fileName, 'public');
            }

            // Create driver - automatically set company_id if needed
            $driverData = $request->all();
            if (!Auth::user()->hasRole('super-admin') && $companyId) {
                $driverData['company_id'] = $companyId;
            }


            // Create driver - FIXED: Use correct field name for medical certificate
            $driver = Driver::create([
                'user_id' => Auth::user()->id,
                'company_id' => $request->company_id,
                'first_name' => $request->first_name,
                'middle_name' => $request->middle_name,
                'last_name' => $request->last_name,
                'suffix' => $request->suffix,
                'date_of_birth' => $request->date_of_birth,
                'ssn' => $ssn,
                'main_phone' => $request->main_phone,
                'alt_phone' => $request->alt_phone,
                'email' => $request->email,
                'medical_certificate_expiration_date' => $request->medical_certificate_expiration_date,
                'photo' => $photo ?? null,
                'business_name' => $request->business_name,
                'employer_identification_number' => $request->employer_identification_number,
                'federal_tax_classification' => $request->federal_tax_classification,
                'address' => $request->address,
                'city' => $request->city,
                'state' => $stateName,
                'country' => $countryName,
                'postal_code' => $request->postal_code,
                'twic_card' => $request->boolean('twic_card'),
                'passport' => $request->boolean('passport'),
                'status' => 'draft',
            ]);

            // Create residence addresses
            if ($request->has('residence_address')) {
                $residences = [];
                foreach ($request->residence_address as $index => $address) {
                    // Only create if address is provided
                    if (!empty(trim($address ?? ''))) {
                        $resCountry = $request->residence_country[$index] ?? null;
                        $resState = $request->residence_state[$index] ?? null;

                        $residences[] = [
                            'driver_id' => $driver->id,
                            'address' => $address,
                            'city' => $request->residence_city[$index] ?? null,
                            'state' => is_numeric($resState) ? \App\Models\State::find($resState)?->name : $resState,
                            'country' => is_numeric($resCountry) ? Country::find($resCountry)?->name : $resCountry,
                            'zip' => $request->residence_postal_code[$index] ?? null,
                            'is_current' => $index === 0, // First address is current
                            'created_at' => now(),
                            'updated_at' => now(),
                        ];
                    }
                }
                if (!empty($residences)) {
                    DB::table('residence_addresses')->insert($residences);
                }
            }

            // Create license - Get names from IDs
            $licenseCountry = Country::find($request->license_country)?->name ?? $request->license_country;
            $licenseState = $request->license_state ? (is_numeric($request->license_state) ?
                State::find($request->license_state)?->name : $request->license_state) : null;

            DB::table('licenses')->insert([
                'driver_id' => $driver->id,
                'first_name' => $request->license_first_name,
                'last_name' => $request->license_last_name,
                'issued' => $request->license_issued,
                'expires' => $request->license_expires,
                'country' => $licenseCountry,
                'state' => $licenseState,
                'class' => $request->license_class,
                'license_number' => $request->license_number,
                'repeat_license_number' => $request->repeat_license_number,
                'is_h_placarded_hazmat' => $request->boolean('is_h_placarded_hazmat'),
                'is_n_tank_vehicle' => $request->boolean('is_n_tank_vehicle'),
                'is_p_passengers' => $request->boolean('is_p_passengers'),
                'is_t_double_trailer' => $request->boolean('is_t_double_trailer'),
                'is_s_school_bus' => $request->boolean('is_s_school_bus'),
                'is_x_placarded_hazmat' => $request->boolean('is_x_placarded_hazmat'),
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            // Create experiences
            $experiences = [];
            foreach ($request->equipment_class as $index => $equipmentClass) {
                // Only create if experience is yes and dates are provided
                if (($request->experience[$index] ?? 'no') === 'yes') {
                    $experiences[] = [
                        'driver_id' => $driver->id,
                        'equipment_class' => $equipmentClass,
                        'experience' => $request->experience[$index] ?? 'no',
                        'from_date' => $request->experience_from_date[$index] ?? null,
                        'to_date' => $request->experience_to_date[$index] ?? null,
                        'approx_miles' => $request->approx_miles[$index] ?? null,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ];
                }
            }
            if (!empty($experiences)) {
                DB::table('experiences')->insert($experiences);
            }

            // Handle accidents
            if ($request->accident === 'yes' && $request->has('accident_date')) {
                $accidents = [];
                foreach ($request->accident_date as $index => $date) {
                    if (!empty($date)) {
                        $accidents[] = [
                            'driver_id' => $driver->id,
                            'accident' => 'yes',
                            'accident_date' => $date,
                            'accident_location' => $request->accident_location[$index] ?? null,
                            'number_of_injuries' => $request->number_of_injuries[$index] ?? null,
                            'number_of_fatalities' => $request->number_of_fatalities[$index] ?? null,
                            'hazmat_spill' => $request->hazmat_spill[$index] ?? 'no',
                            'created_at' => now(),
                            'updated_at' => now(),
                        ];
                    }
                }
                if (!empty($accidents)) {
                    DB::table('accidents')->insert($accidents);
                }
            } else {
                // Create default no accident record
                DB::table('accidents')->insert([
                    'driver_id' => $driver->id,
                    'accident' => 'no',
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }

            // Handle violations
            if ($request->violation === 'yes' && $request->has('violation_date')) {
                $violations = [];
                foreach ($request->violation_date as $index => $date) {
                    if (!empty($date)) {
                        $violations[] = [
                            'driver_id' => $driver->id,
                            'violation' => 'yes',
                            'violation_date' => $date,
                            'violation_location' => $request->violation_location[$index] ?? null,
                            'offense' => $request->offense[$index] ?? null,
                            'vehicle_type' => $request->vehicle_type[$index] ?? null,
                            'created_at' => now(),
                            'updated_at' => now(),
                        ];
                    }
                }
                if (!empty($violations)) {
                    DB::table('violations')->insert($violations);
                }
            } else {
                // Create default no violation record
                DB::table('violations')->insert([
                    'driver_id' => $driver->id,
                    'violation' => 'no',
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }

            // Create forfeiture
            DB::table('forfeitures')->insert([
                'driver_id' => $driver->id,
                'denied_license' => $request->denied_license,
                'license_revoked' => $request->license_revoked,
                'forfeitures' => $request->forfeitures,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            // Create employment records
            if ($request->has('employer_name')) {
                $employmentRecords = [];
                foreach ($request->employer_name as $index => $employerName) {
                    if (!empty(trim($employerName ?? ''))) {
                        $empCountry = $request->employer_record_country[$index] ?? null;
                        $empState = $request->employer_record_state[$index] ?? null;

                        $employmentRecords[] = [
                            'driver_id' => $driver->id,
                            'employer_name' => $employerName,
                            'employer_record_address' => $request->employer_record_address[$index] ?? null,
                            'employer_record_city' => $request->employer_record_city[$index] ?? null,
                            'employer_record_country' => is_numeric($empCountry) ? Country::find($empCountry)?->name : $empCountry,
                            'employer_record_state' => is_numeric($empState) ? \App\Models\State::find($empState)?->name : $empState,
                            'employer_record_postal_code' => $request->employer_record_postal_code[$index] ?? null,
                            'employer_record_phone' => $request->employer_record_phone[$index] ?? null,
                            'employer_record_fax' => $request->employer_record_fax[$index] ?? null,
                            'employer_record_email' => $request->employer_record_email[$index] ?? null,
                            'employer_record_position' => $request->employer_record_position[$index] ?? null,
                            'employer_record_date_from' => $request->employer_record_date_from[$index] ?? null,
                            'employer_record_date_to' => $request->employer_record_date_to[$index] ?? null,
                            'employer_record_reason_for_leaving' => $request->employer_record_reason_for_leaving[$index] ?? null,
                            'employed_regulations' => $request->employed_regulations[$index] ?? 'no',
                            'safety_sensitive_function' => $request->safety_sensitive_function[$index] ?? 'no',
                            'created_at' => now(),
                            'updated_at' => now(),
                        ];
                    }
                }
                if (!empty($employmentRecords)) {
                    DB::table('employment_records')->insert($employmentRecords);
                }
            }

            DB::commit();

            toastr()->success('Driver created successfully!');
            return redirect()->route('admin.driver.license', ['driver_id' => $driver->id]);
        } catch (Exception $e) {
            DB::rollBack();

            logger()->error('Driver creation failed: ' . $e->getMessage(), [
                'exception' => $e,
                'request_data' => $request->except(['ssn', 'license_number'])
            ]);

            toastr()->error('Failed to create driver. Please try again.');
            return back()->withInput()->withErrors([
                'system_error' => 'An error occurred while creating the driver. Please try again.'
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
            }
        ])->findOrFail($id);

        // Check if user has access to this driver
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
            }
        ])->findOrFail($id);

        // Check if user has permission to edit this driver
        $this->authorizeCompanyAccess($driver, 'You do not have permission to edit this driver.');

        $currentStep = 1;
        $isEditMode = $request->has('edit') && $request->edit == '1';

        $companies = Company::where('status', 'active')->get();
        $countries = Country::orderBy('name')->get();
        $states = State::where('country_id', Country::where('iso_code', 'US')->first()->id ?? 1)
            ->orderBy('name')
            ->get();

        // dd($driver);

        return view('admin.driver.edit', compact('currentStep', 'driver', 'companies', 'countries', 'states', 'isEditMode'));
    }

    public function update(Request $request, $id)
    {
        $driver = Driver::with([
            'licenses',
            'experiences',
            'accidents',
            'violations',
            'forfeitures',
            'employment_records',
            'residence_addresses'
        ])->findOrFail($id);

        // Check if user has access to this driver
        $this->authorizeCompanyAccess($driver, 'You do not have permission to update this driver.');

        // Ensure user can't change company if not super-admin
        if (!Auth::user()->hasRole('super-admin')) {
            $request->merge(['company_id' => $driver->company_id]);
        }

        // Main validation - expanded to include all sections
        $validator = Validator::make($request->all(), [
            'company_id' => 'required|exists:companies,id',
            'first_name' => 'required|string|max:255',
            'middle_name' => 'nullable|string|max:255',
            'last_name' => 'required|string|max:255',
            'suffix' => 'nullable|string|max:10',
            'date_of_birth' => 'required|date|before:-18 years',
            'ssn' => 'required|string|max:11',
            'main_phone' => 'required|string|max:20',
            'alt_phone' => 'nullable|string|max:20',
            'email' => 'required|email|max:255',
            'business_name' => 'nullable|string|max:255',
            'employer_identification_number' => 'nullable|string|max:20',
            'federal_tax_classification' => 'nullable|in:individual_sole_proprietor,c_corporation,s_corporation,llc',
            'address' => 'required|string|max:500',
            'city' => 'required|string|max:255',
            'state' => 'required|string|max:255',
            'country' => 'required|string|max:255',
            'postal_code' => 'required|string|max:20',
            'twic_card' => 'boolean',
            'passport' => 'boolean',
            'status' => 'required|in:draft,pending,active,inactive,submitted,under_review,approved,rejected',
            'medical_certificate_expiration_date' => 'nullable|date',
            'photo' => 'nullable|image|mimes:jpeg,png,jpg,gif,svg|max:2048',

            // License fields
            'license_first_name' => 'nullable|string|max:255',
            'license_last_name' => 'nullable|string|max:255',
            'license_issued' => 'nullable|date',
            'license_expires' => 'nullable|date|after:license_issued',
            'license_number' => 'nullable|string|max:255',
            'repeat_license_number' => 'nullable|string|max:255|same:license_number',
            'license_class' => 'nullable|string|max:255',

            // Endorsements
            'is_h_placarded_hazmat' => 'boolean',
            'is_n_tank_vehicle' => 'boolean',
            'is_p_passengers' => 'boolean',
            'is_t_double_trailer' => 'boolean',
            'is_s_school_bus' => 'boolean',
            'is_x_placarded_hazmat' => 'boolean',

            // Residence addresses
            'residence_address' => 'sometimes|array',
            'residence_address.*' => 'nullable|string',
            'residence_city' => 'sometimes|array',
            'residence_city.*' => 'nullable|string',
            'residence_country' => 'sometimes|array',
            'residence_country.*' => 'nullable|string',
            'residence_state' => 'sometimes|array',
            'residence_state.*' => 'nullable|string',
            'residence_postal_code' => 'sometimes|array',
            'residence_postal_code.*' => 'nullable|string',

            // Experience fields
            'equipment_class' => 'sometimes|array',
            'equipment_class.*' => 'nullable|string',
            'experience' => 'sometimes|array',
            'experience.*' => 'nullable|in:no,yes',
            'experience_from_date' => 'sometimes|array',
            'experience_from_date.*' => 'nullable|date',
            'experience_to_date' => 'sometimes|array',
            'experience_to_date.*' => 'nullable|date',
            'approx_miles' => 'sometimes|array',
            'approx_miles.*' => 'nullable|string',

            // Accident fields
            'accident' => 'required|in:no,yes',
            'accident_date' => 'sometimes|array',
            'accident_date.*' => 'nullable|date',
            'accident_location' => 'sometimes|array',
            'accident_location.*' => 'nullable|string',
            'number_of_injuries' => 'sometimes|array',
            'number_of_injuries.*' => 'nullable|string',
            'number_of_fatalities' => 'sometimes|array',
            'number_of_fatalities.*' => 'nullable|string',
            'hazmat_spill' => 'sometimes|array',
            'hazmat_spill.*' => 'nullable|in:no,yes',

            // Violation fields
            'violation' => 'required|in:no,yes',
            'violation_date' => 'sometimes|array',
            'violation_date.*' => 'nullable|date',
            'violation_location' => 'sometimes|array',
            'violation_location.*' => 'nullable|string',
            'offense' => 'sometimes|array',
            'offense.*' => 'nullable|string',
            'vehicle_type' => 'sometimes|array',
            'vehicle_type.*' => 'nullable|string',

            // Forfeiture fields
            'denied_license' => 'required|in:no,yes',
            'license_revoked' => 'required|in:no,yes',
            'forfeitures' => 'nullable|string|max:1000',

            // Employment record fields
            'employer_name' => 'sometimes|array',
            'employer_name.*' => 'nullable|string',
            'employer_record_address' => 'sometimes|array',
            'employer_record_address.*' => 'nullable|string',
            'employer_record_city' => 'sometimes|array',
            'employer_record_city.*' => 'nullable|string',
            'employer_record_country' => 'sometimes|array',
            'employer_record_country.*' => 'nullable|string',
            'employer_record_state' => 'sometimes|array',
            'employer_record_state.*' => 'nullable|string',
            'employer_record_postal_code' => 'sometimes|array',
            'employer_record_postal_code.*' => 'nullable|string',
            'employer_record_phone' => 'sometimes|array',
            'employer_record_phone.*' => 'nullable|string',
            'employer_record_fax' => 'sometimes|array',
            'employer_record_fax.*' => 'nullable|string',
            'employer_record_email' => 'sometimes|array',
            'employer_record_email.*' => 'nullable|email',
            'employer_record_position' => 'sometimes|array',
            'employer_record_position.*' => 'nullable|string',
            'employer_record_date_from' => 'sometimes|array',
            'employer_record_date_from.*' => 'nullable|date',
            'employer_record_date_to' => 'sometimes|array',
            'employer_record_date_to.*' => 'nullable|date',
            'employer_record_reason_for_leaving' => 'sometimes|array',
            'employer_record_reason_for_leaving.*' => 'nullable|string',
            'employed_regulations' => 'sometimes|array',
            'employed_regulations.*' => 'nullable|in:no,yes',
            'safety_sensitive_function' => 'sometimes|array',
            'safety_sensitive_function.*' => 'nullable|in:no,yes',
        ]);

        if ($validator->fails()) {
            foreach ($validator->errors()->all() as $error) {
                toastr()->error($error);
            }
            return back()->withInput();
        }

        DB::beginTransaction();

        try {
            // Format SSN
            $ssn = preg_replace('/[^0-9]/', '', $request->ssn);

            // Handle photo upload
            $photo = $driver->photo;
            if ($request->hasFile('photo')) {
                // Delete old photo if exists
                if ($driver->photo && Storage::disk('public')->exists($driver->photo)) {
                    Storage::disk('public')->delete($driver->photo);
                }

                $file = $request->file('photo');
                $extension = $file->getClientOriginalExtension();
                $fileName = 'driver_photo_' . time() . '.' . $extension;
                $photo = $file->storeAs('images/drivers', $fileName, 'public');
            }

            // Update driver
            $driver->update([
                'company_id' => $request->company_id,
                'first_name' => $request->first_name,
                'middle_name' => $request->middle_name,
                'last_name' => $request->last_name,
                'suffix' => $request->suffix,
                'date_of_birth' => $request->date_of_birth,
                'ssn' => $ssn,
                'main_phone' => $request->main_phone,
                'alt_phone' => $request->alt_phone,
                'email' => $request->email,
                'business_name' => $request->business_name,
                'employer_identification_number' => $request->employer_identification_number,
                'federal_tax_classification' => $request->federal_tax_classification,
                'address' => $request->address,
                'city' => $request->city,
                'state' => $request->state,
                'country' => $request->country,
                'postal_code' => $request->postal_code,
                'twic_card' => $request->boolean('twic_card'),
                'passport' => $request->boolean('passport'),
                'status' => $request->status,
                'medical_certificate_expiration_date' => $request->medical_certificate_expiration_date,
                'photo' => $photo,
            ]);

            // Update or create license
            if ($request->filled('license_number')) {
                if ($driver->licenses->isNotEmpty()) {
                    $license = $driver->licenses->first();
                    $license->update([
                        'first_name' => $request->license_first_name,
                        'last_name' => $request->license_last_name,
                        'issued' => $request->license_issued,
                        'expires' => $request->license_expires,
                        'country' => $request->country,
                        'state' => $request->state,
                        'class' => $request->license_class,
                        'license_number' => $request->license_number,
                        'repeat_license_number' => $request->repeat_license_number,
                        'is_h_placarded_hazmat' => $request->boolean('is_h_placarded_hazmat'),
                        'is_n_tank_vehicle' => $request->boolean('is_n_tank_vehicle'),
                        'is_p_passengers' => $request->boolean('is_p_passengers'),
                        'is_t_double_trailer' => $request->boolean('is_t_double_trailer'),
                        'is_s_school_bus' => $request->boolean('is_s_school_bus'),
                        'is_x_placarded_hazmat' => $request->boolean('is_x_placarded_hazmat'),
                    ]);
                } else {
                    DB::table('licenses')->insert([
                        'driver_id' => $driver->id,
                        'first_name' => $request->license_first_name,
                        'last_name' => $request->license_last_name,
                        'issued' => $request->license_issued,
                        'expires' => $request->license_expires,
                        'country' => $request->country,
                        'state' => $request->state,
                        'class' => $request->license_class,
                        'license_number' => $request->license_number,
                        'repeat_license_number' => $request->repeat_license_number,
                        'is_h_placarded_hazmat' => $request->boolean('is_h_placarded_hazmat'),
                        'is_n_tank_vehicle' => $request->boolean('is_n_tank_vehicle'),
                        'is_p_passengers' => $request->boolean('is_p_passengers'),
                        'is_t_double_trailer' => $request->boolean('is_t_double_trailer'),
                        'is_s_school_bus' => $request->boolean('is_s_school_bus'),
                        'is_x_placarded_hazmat' => $request->boolean('is_x_placarded_hazmat'),
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }
            }

            // Update residence addresses
            $driver->residence_addresses()->delete();
            if ($request->has('residence_address')) {
                $residences = [];
                foreach ($request->residence_address as $index => $address) {
                    if (!empty(trim($address ?? ''))) {
                        $residences[] = [
                            'driver_id' => $driver->id,
                            'address' => $address,
                            'city' => $request->residence_city[$index] ?? null,
                            'state' => $request->residence_state[$index] ?? null,
                            'country' => $request->residence_country[$index] ?? null,
                            'zip' => $request->residence_postal_code[$index] ?? null,
                            'is_current' => $index === 0, // First address is current
                            'created_at' => now(),
                            'updated_at' => now(),
                        ];
                    }
                }
                if (!empty($residences)) {
                    DB::table('residence_addresses')->insert($residences);
                }
            }

            // Update experiences
            $driver->experiences()->delete();
            if ($request->has('equipment_class')) {
                $experiences = [];
                foreach ($request->equipment_class as $index => $equipmentClass) {
                    if (($request->experience[$index] ?? 'no') === 'yes') {
                        $experiences[] = [
                            'driver_id' => $driver->id,
                            'equipment_class' => $equipmentClass,
                            'experience' => $request->experience[$index] ?? 'no',
                            'from_date' => $request->experience_from_date[$index] ?? null,
                            'to_date' => $request->experience_to_date[$index] ?? null,
                            'approx_miles' => $request->approx_miles[$index] ?? null,
                            'created_at' => now(),
                            'updated_at' => now(),
                        ];
                    }
                }
                if (!empty($experiences)) {
                    DB::table('experiences')->insert($experiences);
                }
            }

            // Update accidents
            $driver->accidents()->delete();
            if ($request->accident === 'yes' && $request->has('accident_date')) {
                $accidents = [];
                foreach ($request->accident_date as $index => $date) {
                    if (!empty($date)) {
                        $accidents[] = [
                            'driver_id' => $driver->id,
                            'accident' => 'yes',
                            'accident_date' => $date,
                            'accident_location' => $request->accident_location[$index] ?? null,
                            'number_of_injuries' => $request->number_of_injuries[$index] ?? null,
                            'number_of_fatalities' => $request->number_of_fatalities[$index] ?? null,
                            'hazmat_spill' => $request->hazmat_spill[$index] ?? 'no',
                            'created_at' => now(),
                            'updated_at' => now(),
                        ];
                    }
                }
                if (!empty($accidents)) {
                    DB::table('accidents')->insert($accidents);
                }
            } else {
                // Create default no accident record
                DB::table('accidents')->insert([
                    'driver_id' => $driver->id,
                    'accident' => 'no',
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }

            // Update violations
            $driver->violations()->delete();
            if ($request->violation === 'yes' && $request->has('violation_date')) {
                $violations = [];
                foreach ($request->violation_date as $index => $date) {
                    if (!empty($date)) {
                        $violations[] = [
                            'driver_id' => $driver->id,
                            'violation' => 'yes',
                            'violation_date' => $date,
                            'violation_location' => $request->violation_location[$index] ?? null,
                            'offense' => $request->offense[$index] ?? null,
                            'vehicle_type' => $request->vehicle_type[$index] ?? null,
                            'created_at' => now(),
                            'updated_at' => now(),
                        ];
                    }
                }
                if (!empty($violations)) {
                    DB::table('violations')->insert($violations);
                }
            } else {
                // Create default no violation record
                DB::table('violations')->insert([
                    'driver_id' => $driver->id,
                    'violation' => 'no',
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }

            // Update forfeitures
            $driver->forfeitures()->delete();
            DB::table('forfeitures')->insert([
                'driver_id' => $driver->id,
                'denied_license' => $request->denied_license,
                'license_revoked' => $request->license_revoked,
                'forfeitures' => $request->forfeitures,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            // Update employment records
            $driver->employment_records()->delete();
            if ($request->has('employer_name')) {
                $employmentRecords = [];
                foreach ($request->employer_name as $index => $employerName) {
                    if (!empty(trim($employerName ?? ''))) {
                        $employmentRecords[] = [
                            'driver_id' => $driver->id,
                            'employer_name' => $employerName,
                            'employer_record_address' => $request->employer_record_address[$index] ?? null,
                            'employer_record_city' => $request->employer_record_city[$index] ?? null,
                            'employer_record_country' => $request->employer_record_country[$index] ?? null,
                            'employer_record_state' => $request->employer_record_state[$index] ?? null,
                            'employer_record_postal_code' => $request->employer_record_postal_code[$index] ?? null,
                            'employer_record_phone' => $request->employer_record_phone[$index] ?? null,
                            'employer_record_fax' => $request->employer_record_fax[$index] ?? null,
                            'employer_record_email' => $request->employer_record_email[$index] ?? null,
                            'employer_record_position' => $request->employer_record_position[$index] ?? null,
                            'employer_record_date_from' => $request->employer_record_date_from[$index] ?? null,
                            'employer_record_date_to' => $request->employer_record_date_to[$index] ?? null,
                            'employer_record_reason_for_leaving' => $request->employer_record_reason_for_leaving[$index] ?? null,
                            'employed_regulations' => $request->employed_regulations[$index] ?? 'no',
                            'safety_sensitive_function' => $request->safety_sensitive_function[$index] ?? 'no',
                            'created_at' => now(),
                            'updated_at' => now(),
                        ];
                    }
                }
                if (!empty($employmentRecords)) {
                    DB::table('employment_records')->insert($employmentRecords);
                }
            }

            DB::commit();

            toastr()->success('Driver updated successfully!');

            return redirect()->route('admin.driver.license', [
                'driver_id' => $id,
                'edit' => '1'
            ]);
        } catch (Exception $e) {
            DB::rollBack();

            logger()->error('Driver update failed: ' . $e->getMessage(), [
                'exception' => $e,
                'driver_id' => $id,
                'request_data' => $request->except(['ssn', 'license_number'])
            ]);

            toastr()->error('Failed to update driver. Please try again.');
            return back()->withInput()->withErrors([
                'system_error' => 'An error occurred while updating the driver. Please try again.'
            ]);
        }
    }

    public function destroy($id)
    {
        try {
            $driver = Driver::findOrFail($id);

            // Check if user has access to this driver
            $this->authorizeCompanyAccess($driver, 'You do not have permission to delete this driver.');

            $driver->delete();

            return response()->json(['success' => true, 'message' => 'Driver deleted successfully']);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => 'Error deleting driver: ' . $e->getMessage()], 500);
        }
    }

    public function updateStatus(Request $request, $id)
    {
        $validator = Validator::make($request->all(), [
            'status' => 'required|in:draft,submitted,under_review,approved,rejected',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => $validator->errors()->first()
            ], 422);
        }

        try {
            $driver = Driver::findOrFail($id);
            $driver->update(['status' => $request->status]);

            return response()->json([
                'success' => true,
                'message' => 'Driver status updated successfully!'
            ]);
        } catch (Exception $e) {
            logger()->error('Driver status update failed: ' . $e->getMessage(), [
                'exception' => $e,
                'driver_id' => $id
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to update driver status. Please try again.'
            ], 500);
        }
    }

    public function getDriverDetails($id)
    {
        try {
            $driver = Driver::findOrFail($id);

            return response()->json([
                'success' => true,
                'data' => [
                    'main_phone' => $driver->main_phone,
                    'alt_phone' => $driver->alt_phone,
                    'email' => $driver->email,
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Driver not found'
            ], 404);
        }
    }

    public function license($driver_id, Request $request)
    {
        $driver = Driver::findOrFail($driver_id);

        // Check authorization
        if (!Auth::user()->hasRole('super-admin')) {
            $company_id = Auth::user()->load('company')->company->id ?? null;
            if ($driver->company_id !== $company_id) {
                abort(403, 'Unauthorized action.');
            }
        }

        $currentStep = 2;
        $driver_document = DriverDocument::where('driver_id', $driver_id)->first();

        // Check if we're in edit mode
        $isEditMode = $request->has('edit') && $request->edit == '1';

        return view('admin.driver.license', compact('currentStep', 'driver_id', 'driver', 'driver_document', 'isEditMode'));
    }

    public function licenseStore(Request $request)
    {
        // Validate input
        $validator = Validator::make($request->all(), [
            'license_front' => 'required|image|mimes:jpg,jpeg,png,webp|max:5120', // 5MB
            'license_back' => 'required|image|mimes:jpg,jpeg,png,webp|max:5120',
            'driver_id' => 'required|exists:drivers,id',
        ]);

        if ($validator->fails()) {
            foreach ($validator->errors()->all() as $error) {
                toastr()->error($error);
            }
            return back()->withInput();
        }

        try {
            DB::beginTransaction();

            $driver = Driver::findOrFail($request->driver_id);

            // Check authorization
            if (!Auth::user()->hasRole('super-admin')) {
                $company_id = Auth::user()->load('company')->company->id ?? null;
                if ($driver->company_id !== $company_id) {
                    abort(403, 'Unauthorized action.');
                }
            }

            // Find existing document first to handle file deletion
            $existingDocument = DriverDocument::where('driver_id', $request->driver_id)->first();

            $license_front = $existingDocument ? $existingDocument->license_front : null;
            $license_back = $existingDocument ? $existingDocument->license_back : null;

            // Handle license front upload
            if ($request->hasFile('license_front')) {
                // Delete old file if exists
                if ($existingDocument && $existingDocument->license_front && Storage::disk('public')->exists($existingDocument->license_front)) {
                    Storage::disk('public')->delete($existingDocument->license_front);
                }

                $file = $request->file('license_front');
                $fileName = 'license_front_' . time() . '_' . uniqid() . '.' . $file->getClientOriginalExtension();
                $license_front = $file->storeAs('images/documents', $fileName, 'public');
            }

            // Handle license back upload
            if ($request->hasFile('license_back')) {
                // Delete old file if exists
                if ($existingDocument && $existingDocument->license_back && Storage::disk('public')->exists($existingDocument->license_back)) {
                    Storage::disk('public')->delete($existingDocument->license_back);
                }

                $file = $request->file('license_back');
                $fileName = 'license_back_' . time() . '_' . uniqid() . '.' . $file->getClientOriginalExtension();
                $license_back = $file->storeAs('images/documents', $fileName, 'public');
            }

            // Use updateOrCreate
            DriverDocument::updateOrCreate(
                [
                    'driver_id' => $request->driver_id,
                ],
                [
                    'license_front' => $license_front,
                    'license_back' => $license_back,
                ]
            );

            DB::commit();

            toastr()->success('License documents saved successfully!');

            // Determine next step based on mode
            if ($request->has('from_edit') && $request->from_edit == '1') {
                // In edit mode, continue to medical card step
                return redirect()->route('admin.driver.medical.card', [
                    'driver_id' => $request->driver_id,
                    'edit' => '1'
                ]);
            } else {
                // In create mode, continue to medical card step
                return redirect()->route('admin.driver.medical.card', [
                    'driver_id' => $request->driver_id
                ]);
            }
        } catch (\Exception $e) {
            DB::rollBack();

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
        $driver = Driver::findOrFail($driver_id);

        // Check authorization
        if (!Auth::user()->hasRole('super-admin')) {
            $company_id = Auth::user()->load('company')->company->id ?? null;
            if ($driver->company_id !== $company_id) {
                abort(403, 'Unauthorized action.');
            }
        }

        $currentStep = 3;
        $driver_document = DriverDocument::where('driver_id', $driver_id)->first();
        $isEditMode = $request->has('edit') && $request->edit == '1';
        return view('admin.driver.medical-card', compact('currentStep', 'driver_id', 'driver', 'driver_document', 'isEditMode'));
    }

    public function medicalCardStore(Request $request)
    {
        // Validate input
        $request->validate([
            'medical_card' => 'nullable|image|mimes:jpg,jpeg,png,webp|max:2048',
            'driver_id' => 'required|exists:drivers,id',
        ]);

        try {
            DB::beginTransaction();


            $driver = Driver::findOrFail($request->driver_id);
            // Check authorization
            if (!Auth::user()->hasRole('super-admin')) {
                $company_id = Auth::user()->load('company')->company->id ?? null;
                if ($driver->company_id !== $company_id) {
                    abort(403, 'Unauthorized action.');
                }
            }

            // Find existing document first to handle file deletion
            $existingDocument = DriverDocument::where('driver_id', $request->driver_id)->first();

            $medical_card = $existingDocument ? $existingDocument->medical_card : null;

            // Handle medical card upload
            if ($request->hasFile('medical_card')) {
                // Delete old file if exists
                if ($existingDocument && $existingDocument->medical_card && Storage::disk('public')->exists($existingDocument->medical_card)) {
                    Storage::disk('public')->delete($existingDocument->medical_card);
                }

                $file = $request->file('medical_card');
                $fileName = 'medical_card_' . time() . '_' . uniqid() . '.' . $file->getClientOriginalExtension();
                $medical_card = $file->storeAs('images/documents', $fileName, 'public');
            }


            // Use updateOrCreate 
            DriverDocument::updateOrCreate(
                [
                    'driver_id' => $request->driver_id,
                ],
                [
                    'medical_card' => $medical_card,
                    // medical_card and document_path remain unchanged
                ]
            );

            DB::commit();

            toastr()->success('Medical card saved successfully!');

            // Determine next step based on mode
            if ($request->has('from_edit') && $request->from_edit == '1') {
                return redirect()->route('admin.driver.forfeiture', [
                    'driver_id' => $request->driver_id,
                    'edit' => '1'
                ]);
            } else {
                return redirect()->route('admin.driver.forfeiture', [
                    'driver_id' => $request->driver_id
                ]);
            }
        } catch (\Exception $e) {
            DB::rollBack();

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
        $driver = Driver::findOrFail($driver_id);

        // Check authorization
        if (!Auth::user()->hasRole('super-admin')) {
            $company_id = Auth::user()->load('company')->company->id ?? null;
            if ($driver->company_id !== $company_id) {
                abort(403, 'Unauthorized action.');
            }
        }

        $currentStep = 4;
        $driver_document = DriverDocument::where('driver_id', $driver_id)->first();
        $isEditMode = $request->has('edit') && $request->edit == '1';
        return view('admin.driver.forfeiture', compact('currentStep', 'driver_id', 'driver', 'driver_document', 'isEditMode'));
    }

    public function forfeitureStore(Request $request)
    {
        // Validate input
        $request->validate([
            'forfeiture_document' => 'nullable|image|mimes:jpg,jpeg,png,webp|max:2048',
            'driver_id' => 'required|exists:drivers,id',
        ]);


        try {
            DB::beginTransaction();
            $driver = Driver::findOrFail($request->driver_id);

            // Check authorization
            if (!Auth::user()->hasRole('super-admin')) {
                $company_id = Auth::user()->load('company')->company->id ?? null;
                if ($driver->company_id !== $company_id) {
                    abort(403, 'Unauthorized action.');
                }
            }

            // Find existing document first to handle file deletion
            $existingDocument = DriverDocument::where('driver_id', $request->driver_id)->first();

            $forfeiture_document = $existingDocument ? $existingDocument->forfeiture_document : null;

            // Handle license front upload
            if ($request->hasFile('forfeiture_document')) {
                // Delete old file if exists
                if ($existingDocument && $existingDocument->forfeiture_document && Storage::disk('public')->exists($existingDocument->forfeiture_document)) {
                    Storage::disk('public')->delete($existingDocument->forfeiture_document);
                }

                $file = $request->file('forfeiture_document');
                $fileName = 'forfeiture_document_' . time() . '_' . uniqid() . '.' . $file->getClientOriginalExtension();
                $forfeiture_document = $file->storeAs('images/documents', $fileName, 'public');
            }


            // Use updateOrCreate - only updates license_front and license_back
            DriverDocument::updateOrCreate(
                [
                    'driver_id' => $request->driver_id,
                ],
                [
                    'forfeiture_document' => $forfeiture_document,
                ]
            );

            DB::commit();

            toastr()->success('Forfeiture document saved successfully!');

            // Determine next step based on mode
            if ($request->has('from_edit') && $request->from_edit == '1') {
                return redirect()->route('admin.driver.violation', [
                    'driver_id' => $request->driver_id,
                    'edit' => '1'
                ]);
            } else {
                return redirect()->route('admin.driver.violation', [
                    'driver_id' => $request->driver_id
                ]);
            }
        } catch (\Exception $e) {
            DB::rollBack();

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
        $driver = Driver::findOrFail($driver_id);

        // Check authorization
        if (!Auth::user()->hasRole('super-admin')) {
            $company_id = Auth::user()->load('company')->company->id ?? null;
            if ($driver->company_id !== $company_id) {
                abort(403, 'Unauthorized action.');
            }
        }
        $currentStep = 5;
        $driver_document = DriverDocument::where('driver_id', $driver_id)->first();
        $isEditMode = $request->has('edit') && $request->edit == '1';

        return view('admin.driver.violation-record', compact('currentStep', 'driver_id', 'driver', 'driver_document', 'isEditMode'));
    }

    public function violationStore(Request $request)
    {
        // Validate input
        $validator = Validator::make($request->all(), [
            'driver_id' => 'required|exists:drivers,id',
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

        DB::beginTransaction();

        try {

            $driver = Driver::findOrFail($request->driver_id);

            // Check authorization
            if (!Auth::user()->hasRole('super-admin')) {
                $company_id = Auth::user()->load('company')->company->id ?? null;
                if ($driver->company_id !== $company_id) {
                    abort(403, 'Unauthorized action.');
                }
            }

            $driverId = $request->driver_id;

            // First, delete all existing violations for this driver
            // (since we're replacing all records)
            Violation::where('driver_id', $driverId)->delete();

            // Check if "I have had no violations" radio is selected
            // Note: In your blade file, the radio name is "violation" with value "yes" for "I have had no violations"
            if ($request->violation === 'no') {
                // Create a "no violation" record with signature
                Violation::create([
                    'driver_id' => $driverId,
                    'violation' => 'no',
                    'violation_record_signature' => $request->applicant_signature,
                    'violation_record_date_signed' => $request->date_signed,
                ]);
            } else {
                // Check if there are any violations
                $hasViolations = false;

                if ($request->has('violation_date')) {
                    foreach ($request->violation_date as $index => $date) {
                        if (!empty(trim($date ?? ''))) {
                            $hasViolations = true;

                            // Create violation record for each violation
                            Violation::create([
                                'driver_id' => $driverId,
                                'violation' => 'yes',
                                'violation_date' => $date,
                                'violation_location' => $request->violation_location[$index] ?? null,
                                'offense' => $request->offense[$index] ?? null,
                                'vehicle_type' => $request->vehicle_type[$index] ?? null,
                                'violation_record_signature' => $request->applicant_signature,
                                'violation_record_date_signed' => $request->date_signed,
                            ]);
                        }
                    }
                }

                // If no violations were added but "no violations" wasn't selected
                // (this handles the case where user doesn't check "no violations" but also doesn't add any violations)
                if (!$hasViolations) {
                    Violation::create([
                        'driver_id' => $driverId,
                        'violation' => 'no',
                        'violation_record_signature' => $request->applicant_signature,
                        'violation_record_date_signed' => $request->date_signed,
                    ]);
                }
            }

            // Update driver document
            DriverDocument::updateOrCreate(
                [
                    'driver_id' => $driverId,
                ],
                [
                    'violation_record_signature' => $request->applicant_signature,
                    'violation_record_date_signed' => $request->date_signed,
                ]
            );


            DB::commit();

            toastr()->success('Vo]iolation  saved successfully!');

            // Determine next step based on mode
            if ($request->has('from_edit') && $request->from_edit == '1') {
                return redirect()->route('admin.driver.alcohol.and.drug.test', [
                    'driver_id' => $request->driver_id,
                    'edit' => '1'
                ]);
            } else {
                return redirect()->route('admin.driver.alcohol.and.drug.test', [
                    'driver_id' => $request->driver_id
                ]);
            }
        } catch (\Exception $e) {
            DB::rollBack();

            Log::error('Violation Store Error: ' . $e->getMessage(), [
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'request' => $request->except(['applicant_signature'])
            ]);

            return back()
                ->with('error', 'Something went wrong while saving the violation record.')
                ->withInput();
        }
    }

    public function alcoholAndDrugTest($driver_id, Request $request)
    {
        $driver = Driver::findOrFail($driver_id);

        // Check authorization
        if (!Auth::user()->hasRole('super-admin')) {
            $company_id = Auth::user()->load('company')->company->id ?? null;
            if ($driver->company_id !== $company_id) {
                abort(403, 'Unauthorized action.');
            }
        }
        $currentStep = 6;
        $driver_document = DriverDocument::where('driver_id', $driver_id)->first();
        $isEditMode = $request->has('edit') && $request->edit == '1';

        if (!$driver_document) {
            $driver_document = new DriverDocument();
        }

        return view('admin.driver.alcohol-and-drug-test', compact('currentStep', 'driver_id', 'driver', 'driver_document', 'isEditMode'));
    }

    public function alcoholAndDrugTestStore(Request $request)
    {
        DB::beginTransaction();

        try {
            // Validate input
            $validator = Validator::make($request->all(), [
                'driver_id' => 'required|exists:drivers,id',
                'drug_test_question_1' => 'required|in:yes,no',
                'drug_test_question_2' => 'required|in:yes,no,n/a',
                'applicant_signature' => 'required|string|max:255',
                'date_signed' => 'required|date',
            ]);

            $driver = Driver::findOrFail($request->driver_id);

            // Check authorization
            if (!Auth::user()->hasRole('super-admin')) {
                $company_id = Auth::user()->load('company')->company->id ?? null;
                if ($driver->company_id !== $company_id) {
                    abort(403, 'Unauthorized action.');
                }
            }

            if ($validator->fails()) {
                DB::rollBack();

                foreach ($validator->errors()->all() as $error) {
                    toastr()->error($error);
                }
                return back()->withInput();
            }

            // Update or create driver document
            DriverDocument::updateOrCreate(
                [
                    'driver_id' => $request->driver_id,
                ],
                [
                    'drug_test_question_1' => $request->drug_test_question_1,
                    'drug_test_question_2' => $request->drug_test_question_2,
                    'drug_test_signature' => $request->applicant_signature,
                    'drug_test_date_signed' => $request->date_signed,
                ]
            );

            // Commit the transaction
            DB::commit();

            toastr()->success('Alcohol and drug test statement saved successfully.');

            // Determine next step based on mode
            if ($request->has('from_edit') && $request->from_edit == '1') {
                return redirect()->route('admin.driver.fmcsa.consent', [
                    'driver_id' => $request->driver_id,
                    'edit' => '1'
                ]);
            } else {
                return redirect()->route('admin.driver.fmcsa.consent', [
                    'driver_id' => $request->driver_id
                ]);
            }
        } catch (\Exception $e) {
            // Rollback transaction on error
            DB::rollBack();

            // Log the error for debugging
            Log::error('Error saving alcohol and drug test statement: ' . $e->getMessage(), [
                'driver_id' => $request->driver_id ?? 'N/A',
                'exception' => $e
            ]);

            toastr()->error('An error occurred while saving the alcohol and drug test statement. Please try again.');

            return back()->withInput()->withErrors([
                'error' => 'An unexpected error occurred. Please try again.'
            ]);
        }
    }

    public function consent($driver_id, Request $request)
    {
        $driver = Driver::findOrFail($driver_id);

        // Check authorization
        if (!Auth::user()->hasRole('super-admin')) {
            $company_id = Auth::user()->load('company')->company->id ?? null;
            if ($driver->company_id !== $company_id) {
                abort(403, 'Unauthorized action.');
            }
        }
        $currentStep = 7;

        $driver = Driver::find($driver_id);
        $driver_name = $driver ? $driver->first_name . ' ' . $driver->last_name : 'Applicant';
        $isEditMode = $request->has('edit') && $request->edit == '1';

        $driver_document = DriverDocument::where('driver_id', $driver_id)->first();
        if (!$driver_document) {
            $driver_document = new DriverDocument();
        }

        $authUser = Auth::user()->load('company');
        if (!$authUser) {
            toastr()->error('Authenticated user not found.');
            return redirect()->back();
        }

        return view('admin.driver.general-consent', compact('currentStep', 'driver_id', 'driver', 'driver_document', 'driver_name', 'authUser', 'isEditMode'));
    }

    public function consentStore(Request $request)
    {
        DB::beginTransaction();

        try {
            // Validate input
            $validator = Validator::make($request->all(), [
                'driver_id' => 'required|exists:drivers,id',
                'employee_signature' => 'required|string|max:255',
                'consent_agreement' => 'required|in:1',
                'date_signed' => 'required|date',
            ], [
                'consent_agreement.required' => 'You must agree to the consent terms by checking the box.',
                'consent_agreement.in' => 'You must agree to the consent terms.',
            ]);

            $driver = Driver::findOrFail($request->driver_id);

            // Check authorization
            if (!Auth::user()->hasRole('super-admin')) {
                $company_id = Auth::user()->load('company')->company->id ?? null;
                if ($driver->company_id !== $company_id) {
                    abort(403, 'Unauthorized action.');
                }
            }

            if ($validator->fails()) {
                DB::rollBack();
                foreach ($validator->errors()->all() as $error) {
                    toastr()->error($error);
                }
                return back()->withInput();
            }

            // Update or create driver document with FMCSA consent
            DriverDocument::updateOrCreate(
                [
                    'driver_id' => $request->driver_id,
                ],
                [
                    'fmcsa_consent' => true,
                    'fmcsa_consent_date' => now(),
                    'fmcsa_consent_signature' => $request->employee_signature,
                    'fmcsa_consent_agreement' => $request->consent_agreement,
                    'fmcsa_date_signed' => $request->date_signed,
                ]
            );


            DB::commit();
            toastr()->success('FMCSA Clearinghouse consent saved successfully.');

            // Determine next step based on mode
            if ($request->has('from_edit') && $request->from_edit == '1') {
                return redirect()->route('admin.driver.psp', [
                    'driver_id' => $request->driver_id,
                    'edit' => '1'
                ]);
            } else {
                return redirect()->route('admin.driver.psp', [
                    'driver_id' => $request->driver_id
                ]);
            }
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error saving FMCSA consent: ' . $e->getMessage());
            toastr()->error('An error occurred while saving the FMCSA consent. Please try again.');
            return back()->withInput()->withErrors([
                'error' => 'An unexpected error occurred. Please try again.'
            ]);
        }
    }

    public function psp($driver_id, Request $request)
    {
        $driver = Driver::findOrFail($driver_id);

        // Check authorization
        if (!Auth::user()->hasRole('super-admin')) {
            $company_id = Auth::user()->load('company')->company->id ?? null;
            if ($driver->company_id !== $company_id) {
                abort(403, 'Unauthorized action.');
            }
        }
        $currentStep = 8;
        $driver_document = DriverDocument::where('driver_id', $driver_id)->first();
        $isEditMode = $request->has('edit') && $request->edit == '1';

        if (!$driver_document) {
            $driver_document = new DriverDocument();
        }

        $authUser = Auth::user()->load('company');
        if (!$authUser) {
            toastr()->error('Authenticated user not found.');
            return redirect()->back();
        }

        return view('admin.driver.psp-driver-disclosure', compact('currentStep', 'driver_id', 'driver', 'driver_document', 'authUser', 'isEditMode'));
    }

    public function pspStore(Request $request)
    {
        DB::beginTransaction();

        try {
            // Validate input
            $validator = Validator::make($request->all(), [
                'driver_id' => 'required|exists:drivers,id',
                'applicant_signature' => 'required|string|max:255',
                'authorization_agreement' => 'required|in:1',
                'date_signed' => 'required|date',
            ], [
                'authorization_agreement.required' => 'You must agree to the authorization terms by checking the box.',
                'authorization_agreement.in' => 'You must agree to the authorization terms.',
            ]);

            $driver = Driver::findOrFail($request->driver_id);

            // Check authorization
            if (!Auth::user()->hasRole('super-admin')) {
                $company_id = Auth::user()->load('company')->company->id ?? null;
                if ($driver->company_id !== $company_id) {
                    abort(403, 'Unauthorized action.');
                }
            }


            if ($validator->fails()) {
                DB::rollBack();

                foreach ($validator->errors()->all() as $error) {
                    toastr()->error($error);
                }
                return back()->withInput();
            }

            // Update or create driver document with PSP authorization
            DriverDocument::updateOrCreate(
                [
                    'driver_id' => $request->driver_id,
                ],
                [
                    'psp_authorization' => true,
                    'psp_authorization_date' => now(),
                    'psp_authorization_signature' => $request->applicant_signature,
                    'psp_authorization_agreement' => $request->authorization_agreement,
                ]
            );

            // Commit the transaction
            DB::commit();

            toastr()->success('PSP Driver Disclosure & Authorization saved successfully.');

            // Determine next step based on mode
            if ($request->has('from_edit') && $request->from_edit == '1') {
                return redirect()->route('admin.driver.alcohol.and.drug.test.policy', [
                    'driver_id' => $request->driver_id,
                    'edit' => '1'
                ]);
            } else {
                return redirect()->route('admin.driver.alcohol.and.drug.test.policy', [
                    'driver_id' => $request->driver_id
                ]);
            }
        } catch (\Exception $e) {
            // Rollback transaction on error
            DB::rollBack();

            // Log the error for debugging
            Log::error('Error saving PSP authorization: ' . $e->getMessage(), [
                'driver_id' => $request->driver_id ?? 'N/A',
                'exception' => $e,
                'trace' => $e->getTraceAsString()
            ]);

            toastr()->error('An error occurred while saving the PSP authorization. Please try again.');

            return back()->withInput()->withErrors([
                'error' => 'An unexpected error occurred. Please try again.'
            ]);
        }
    }

    public function alcoholAndDrugTestPolicy($driver_id, Request $request)
    {
        $driver = Driver::findOrFail($driver_id);

        // Check authorization
        if (!Auth::user()->hasRole('super-admin')) {
            $company_id = Auth::user()->load('company')->company->id ?? null;
            if ($driver->company_id !== $company_id) {
                abort(403, 'Unauthorized action.');
            }
        }
        $currentStep = 9;

        $driver = Driver::find($driver_id);
        $driver_name = $driver ? $driver->first_name . ' ' . $driver->last_name : 'Applicant';

        $driver_document = DriverDocument::where('driver_id', $driver_id)->first();
        $isEditMode = $request->has('edit') && $request->edit == '1';

        if (!$driver_document) {
            $driver_document = new DriverDocument();
        }

        $authUser = Auth::user()->load('company');
        if (!$authUser) {
            toastr()->error('Authenticated user not found.');
            return redirect()->back();
        }

        $policyPdf = PolicyPdf::first();
        if (!$policyPdf) {
            $policyPdf = new PolicyPdf();
        }

        return view('admin.driver.alcohol-and-drug-test-policy', compact('currentStep', 'driver_id', 'driver', 'driver_document', 'driver_name', 'authUser', 'policyPdf', 'isEditMode'));
    }

    public function alcoholAndDrugTestPolicyStore(Request $request)
    {
        DB::beginTransaction();

        try {
            // Validate input
            $validator = Validator::make($request->all(), [
                'driver_id' => 'required|exists:drivers,id',
                'employee_signature' => 'required|string|max:255',
                'date_signed' => 'required|date',
            ]);

            $driver = Driver::findOrFail($request->driver_id);

            // Check authorization
            if (!Auth::user()->hasRole('super-admin')) {
                $company_id = Auth::user()->load('company')->company->id ?? null;
                if ($driver->company_id !== $company_id) {
                    abort(403, 'Unauthorized action.');
                }
            }

            if ($validator->fails()) {
                DB::rollBack();
                foreach ($validator->errors()->all() as $error) {
                    toastr()->error($error);
                }
                return back()->withInput();
            }

            // Update or create driver document with FMCSA consent
            DriverDocument::updateOrCreate(
                [
                    'driver_id' => $request->driver_id,
                ],
                [
                    'alcohol_drug_test_policy_signature' => $request->employee_signature,
                    'alcohol_drug_test_policy_date' => $request->date_signed,
                ]
            );


            DB::commit();

            toastr()->success('Alcohol & Drug Testing Policy saved successfully.');

            // Determine next step based on mode
            if ($request->has('from_edit') && $request->from_edit == '1') {
                return redirect()->route('admin.driver.general.work.policy', [
                    'driver_id' => $request->driver_id,
                    'edit' => '1'
                ]);
            } else {
                return redirect()->route('admin.driver.general.work.policy', [
                    'driver_id' => $request->driver_id
                ]);
            }
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error saving Alcohol & Drug Testing Policy: ' . $e->getMessage());
            toastr()->error('An error occurred while saving the Alcohol & Drug Testing Policy. Please try again.');
            return back()->withInput()->withErrors([
                'error' => 'An unexpected error occurred. Please try again.'
            ]);
        }
    }


    public function generalWorkPolicy($driver_id, Request $request)
    {
        $driver = Driver::findOrFail($driver_id);

        // Check authorization
        if (!Auth::user()->hasRole('super-admin')) {
            $company_id = Auth::user()->load('company')->company->id ?? null;
            if ($driver->company_id !== $company_id) {
                abort(403, 'Unauthorized action.');
            }
        }
        $currentStep = 10;

        $driver = Driver::find($driver_id);
        $driver_name = $driver ? $driver->first_name . ' ' . $driver->last_name : 'Applicant';
        $driver_document = DriverDocument::where('driver_id', $driver_id)->first();
        $isEditMode = $request->has('edit') && $request->edit == '1';
        if (!$driver_document) {
            $driver_document = new DriverDocument();
        }

        $authUser = Auth::user()->load('company');
        if (!$authUser) {
            toastr()->error('Authenticated user not found.');
            return redirect()->back();
        }

        $policyPdf = PolicyPdf::first();
        if (!$policyPdf) {
            $policyPdf = new PolicyPdf();
        }

        return view('admin.driver.general-work-policy', compact('currentStep', 'driver_id', 'driver', 'driver_document', 'driver_name', 'authUser', 'policyPdf', 'isEditMode'));
    }

    public function generalWorkPolicyStore(Request $request)
    {
        DB::beginTransaction();

        try {
            // Validate input
            $validator = Validator::make($request->all(), [
                'driver_id' => 'required|exists:drivers,id',
                'employee_signature' => 'required|string|max:255',
                'date_signed' => 'required|date',
            ]);


            if ($validator->fails()) {
                DB::rollBack();
                foreach ($validator->errors()->all() as $error) {
                    toastr()->error($error);
                }
                return back()->withInput();
            }

            // Update or create driver document with FMCSA consent
            DriverDocument::updateOrCreate(
                [
                    'driver_id' => $request->driver_id,
                ],
                [
                    'general_work_policy_signature' => $request->employee_signature,
                    'general_work_policy_date' => $request->date_signed,
                ]
            );

            // Only update status if not in edit mode
            if (!$request->has('from_edit') || $request->from_edit != '1') {
                Driver::where('id', $request->driver_id)->update(['status' => 'pending']);
            }

            DB::commit();



            // Redirect based on mode
            if ($request->has('from_edit') && $request->from_edit == '1') {
                toastr()->success('General Work Policy updatednb successfully.');
                return redirect()->route('admin.driver.index');
            } else {
                toastr()->success('General Work Policy saved successfully.');
                return redirect()->route('admin.driver.index');
            }
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error saving General Work Policy: ' . $e->getMessage());
            toastr()->error('An error occurred while saving the General Work Policy. Please try again.');
            return back()->withInput()->withErrors([
                'error' => 'An unexpected error occurred. Please try again.'
            ]);
        }
    }

    public function updateHireStatus(Request $request, Driver $driver)
    {
        // Check authorization
        if (!Auth::user()->can('drivers.hire')) {
            return response()->json([
                'success' => false,
                'message' => 'You are not authorized to perform this action.'
            ], 403);
        }

        // Validate request based on action
        $validator = Validator::make($request->all(), [
            'action' => 'required|in:hire,reject'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid action.'
            ], 422);
        }

        $action = $request->action;

        // Additional validation based on action
        if ($action === 'hire') {
            $validator = Validator::make($request->all(), [
                'hire_date' => 'required|date',
                'hazmat' => 'required|in:yes,no',
                'lcv_certificate' => 'required|in:yes,no'
            ]);
        } else {
            $validator = Validator::make($request->all(), [
                'rejection_reason' => 'required|in:not_good_fit,failed_drug_test,background_check_issues,cdl_issues,mvr_issues,psp_issues,other',
                'additional_info' => 'nullable|string|max:1000',
                'record_date' => 'required|date'
            ]);
        }

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => $validator->errors()->first()
            ], 422);
        }

        // Check if driver is in pending status
        if ($driver->status !== 'pending') {
            return response()->json([
                'success' => false,
                'message' => 'This driver is not in pending status.'
            ], 422);
        }

        DB::beginTransaction();

        try {
            $previousStatus = $driver->status;
            $userId = Auth::id();

            if ($action === 'hire') {
                // Hire the driver with additional details
                $driver->update([
                    'status' => 'active',
                    'hire_date' => $request->hire_date,
                    'hazmat' => $request->hazmat,
                    'lcv_certificate' => $request->lcv_certificate,
                    'hired_at' => now(),
                    'action_by' => $userId,
                    // Clear rejection fields if they exist
                    'rejection_reason' => null,
                    'rejection_notes' => null,
                    'rejection_date' => null,
                    'rejected_at' => null
                ]);

                $message = 'Driver has been hired successfully!';
            } else {
                // Do not hire (reject) with details
                $driver->update([
                    'status' => 'rejected',
                    'rejection_reason' => $request->rejection_reason,
                    'rejection_notes' => $request->additional_info,
                    'rejection_date' => $request->record_date,
                    'rejected_at' => now(),
                    'action_by' => $userId,
                    // Clear hire fields if they exist
                    'hazmat' => null,
                    'lcv_certificate' => null,
                    'hire_date' => null,
                    'hired_at' => null
                ]);

                $message = 'Driver has been marked as not hired.';
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => $message,
                'data' => [
                    'status' => $driver->status,
                    'hire_date' => $driver->hire_date,
                    'rejection_reason' => $this->getRejectionReasonLabel($driver->rejection_reason),
                    'status_label' => $this->getStatusLabel($driver->status)
                ]
            ]);
        } catch (\Exception $e) {
            DB::rollBack();

            Log::error('Failed to update driver hire status: ' . $e->getMessage(), [
                'driver_id' => $driver->id,
                'action' => $action,
                'error' => $e
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to update driver status. Please try again.'
            ], 500);
        }
    }

    /**
     * Get rejection reason label
     */
    private function getRejectionReasonLabel($reason)
    {
        $labels = [
            'not_good_fit' => 'Applicant is not a good fit for our company',
            'failed_drug_test' => 'Applicant failed a pre-employment drug test',
            'background_check_issues' => 'Items found on the background check',
            'cdl_issues' => 'Items found on the Commercial Driver\'s License',
            'mvr_issues' => 'Items found on the Motor Vehicle Report (MVR)',
            'psp_issues' => 'Items found on the Pre-Employment Screening Program (PSP) report',
            'other' => 'Other reason not listed above'
        ];

        return $labels[$reason] ?? ucfirst($reason);
    }

    /**
     * Get status label for response
     */
    private function getStatusLabel($status)
    {
        $labels = [
            'draft' => 'Draft',
            'pending' => 'Pending',
            'active' => 'Active (Hired)',
            'inactive' => 'Inactive',
            'submitted' => 'Submitted',
            'under_review' => 'Under Review',
            'approved' => 'Approved',
            'rejected' => 'Rejected (Not Hired)',
        ];

        return $labels[$status] ?? ucfirst($status);
    }
}
