<?php

namespace App\Http\Controllers;

use Exception;
use App\Models\Driver;
use App\Models\Company;
use App\Models\Country;
use App\Models\Violation;
use Illuminate\Http\Request;
use App\Models\DriverDocument;
use App\Models\PolicyPdf;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Yajra\DataTables\Facades\DataTables;
use Illuminate\Support\Facades\Validator;

class DriverController extends Controller
{
    public function index(Request $request)
    {
        if ($request->ajax()) {
            $drivers = Driver::with('company')->select('drivers.*');

            return DataTables::of($drivers)
                ->addIndexColumn()
                ->addColumn('full_name', function ($driver) {
                    return $driver->first_name . ' ' . ($driver->middle_name ? $driver->middle_name . ' ' : '') . $driver->last_name . ($driver->suffix ? ' ' . $driver->suffix : '');
                })
                ->addColumn('company_name', function ($driver) {
                    return $driver->company->company_name ?? 'N/A';
                })
                ->addColumn('status', function ($driver) {
                    $statusColors = [
                        'draft' => 'bg-gray-100 text-gray-800 dark:bg-gray-700 dark:text-gray-300',
                        'submitted' => 'bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-200',
                        'under_review' => 'bg-yellow-100 text-yellow-800 dark:bg-yellow-900 dark:text-yellow-200',
                        'approved' => 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200',
                        'rejected' => 'bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-200',
                    ];

                    $statusLabels = [
                        'draft' => 'Draft',
                        'submitted' => 'Submitted',
                        'under_review' => 'Under Review',
                        'approved' => 'Approved',
                        'rejected' => 'Rejected',
                    ];

                    return '<span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium ' . $statusColors[$driver->status] . '">' . $statusLabels[$driver->status] . '</span>';
                })
                ->addColumn('documents', function ($driver) {
                    $docs = [];
                    if ($driver->twic_card) {
                        $docs[] = 'TWIC';
                    }
                    if ($driver->passport) {
                        $docs[] = 'Passport';
                    }
                    return count($docs) > 0 ? implode(', ', $docs) : 'None';
                })
                ->addColumn('action', function ($driver) {
                    return '<div class="flex items-center space-x-2">
                                <a href="' . route('drivers.show', $driver->id) . '" class="inline-flex items-center justify-center w-8 h-8 text-green-600 border border-green-200 rounded-lg hover:bg-green-50 dark:border-green-800 dark:text-green-400 dark:hover:bg-green-900/30">
                                    <i class="fas fa-eye text-xs"></i>
                                </a>
                                <a href="' . route('drivers.edit', $driver->id) . '" class="inline-flex items-center justify-center w-8 h-8 text-blue-600 border border-blue-200 rounded-lg hover:bg-blue-50 dark:border-blue-800 dark:text-blue-400 dark:hover:bg-blue-900/30">
                                    <i class="fas fa-edit text-xs"></i>
                                </a>
                                <button type="button" onclick="deleteDriver(' . $driver->id . ')" class="inline-flex items-center justify-center w-8 h-8 text-red-600 border border-red-200 rounded-lg hover:bg-red-50 dark:border-red-800 dark:text-red-400 dark:hover:bg-red-900/30">
                                    <i class="fas fa-trash text-xs"></i>
                                </button>
                            </div>';
                })
                ->rawColumns(['status', 'action'])
                ->make(true);
        }

        return view('admin.driver.index');
    }

    public function create()
    {
        $companies = Company::where('status', 'active')->get();
        $countries = Country::orderBy('name')->get();
        $defaultCountry = Country::where('iso_code', 'US')->first();
        $states = $defaultCountry ? $defaultCountry->states()->orderBy('name')->get() : collect();
        $currentStep = 1;
        return view('admin.driver.create', compact('companies', 'countries', 'states', 'defaultCountry', 'currentStep'));
    }

    public function store(Request $request)
    {
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
                \App\Models\State::find($request->state)?->name : $request->state) : null;

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
                \App\Models\State::find($request->license_state)?->name : $request->license_state) : null;

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
        $driver = Driver::with('company')->findOrFail($id);
        return view('admin.driver.show', compact('driver'));
    }

    public function edit($id)
    {
        $driver = Driver::with('company')->findOrFail($id);
        $companies = Company::where('status', 'active')->get();
        return view('admin.driver.edit', compact('driver', 'companies'));
    }

    public function update(Request $request, $id)
    {
        $driver = Driver::findOrFail($id);

        // Form validation
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
            'status' => 'required|in:draft,submitted,under_review,approved,rejected',
        ]);

        // Any error checking
        if ($validator->fails()) {
            // FIXED: Use proper toastr syntax
            toastr()->error($validator->errors()->first());
            return back();
        }

        DB::beginTransaction();

        try {
            // Format SSN (remove any non-numeric characters for storage)
            $ssn = preg_replace('/[^0-9]/', '', $request->ssn);

            // Update driver
            $driver->update([
                'company_id' => $request->company_id,
                'first_name' => $request->first_name,
                'middle_name' => $request->middle_name,
                'last_name' => $request->last_name,
                'suffix' => $request->suffix,
                'date_of_birth' => $request->date_of_birth,
                'ssn' => $ssn, // In production, encrypt this field
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
            ]);

            // Commit transaction
            DB::commit();

            // FIXED: Use proper toastr syntax
            toastr()->success('Driver updated successfully!');
            return redirect()->route('admin.driver.index');
        } catch (Exception $e) {
            // Rollback transaction on error
            DB::rollBack();

            // Log the error
            logger()->error('Driver update failed: ' . $e->getMessage(), [
                'exception' => $e,
                'driver_id' => $id,
                'request_data' => $request->except(['ssn'])
            ]);

            // FIXED: Use proper toastr syntax
            toastr()->error('Failed to update driver. Please try again.');
            return back()->withInput()->withErrors([
                'system_error' => 'An error occurred while updating the driver. Please try again.'
            ]);
        }
    }

    public function destroy($id)
    {
        DB::beginTransaction();

        try {
            $driver = Driver::findOrFail($id);
            $driver->delete();

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Driver deleted successfully!'
            ]);
        } catch (Exception $e) {
            DB::rollBack();

            logger()->error('Driver deletion failed: ' . $e->getMessage(), [
                'exception' => $e,
                'driver_id' => $id
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to delete driver. Please try again.'
            ], 500);
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

    public function license($driver_id)
    {
        $currentStep = 2;
        return view('admin.driver.license', compact('currentStep', 'driver_id'));
    }

    public function licenseStore(Request $request)
    {
        // Validate input
        $request->validate([
            'license_front' => 'nullable|image|mimes:jpg,jpeg,png,webp|max:2048',
            'license_back' => 'nullable|image|mimes:jpg,jpeg,png,webp|max:2048',
            'driver_id' => 'required|exists:drivers,id',
        ]);

        try {
            DB::beginTransaction();

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

            // Use updateOrCreate - only updates license_front and license_back
            DriverDocument::updateOrCreate(
                [
                    'driver_id' => $request->driver_id,
                ],
                [
                    'license_front' => $license_front,
                    'license_back' => $license_back,
                    // medical_card and document_path remain unchanged
                ]
            );

            DB::commit();

            return redirect()->route('admin.driver.medical.card', ['driver_id' => $request->driver_id]);
        } catch (\Exception $e) {
            DB::rollBack();

            Log::error('License Upload Error: ' . $e->getMessage(), [
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);

            return redirect()
                ->back()
                ->with('error', 'Something went wrong while uploading the license.');
        }
    }

    public function medicalCard($driver_id)
    {
        $currentStep = 3;
        return view('admin.driver.medical-card', compact('currentStep', 'driver_id'));
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

            // Find existing document first to handle file deletion
            $existingDocument = DriverDocument::where('driver_id', $request->driver_id)->first();

            $medical_card = $existingDocument ? $existingDocument->medical_card : null;

            // Handle license front upload
            if ($request->hasFile('medical_card')) {
                // Delete old file if exists
                if ($existingDocument && $existingDocument->medical_card && Storage::disk('public')->exists($existingDocument->medical_card)) {
                    Storage::disk('public')->delete($existingDocument->medical_card);
                }

                $file = $request->file('medical_card');
                $fileName = 'medical_card_' . time() . '_' . uniqid() . '.' . $file->getClientOriginalExtension();
                $medical_card = $file->storeAs('images/documents', $fileName, 'public');
            }


            // Use updateOrCreate - only updates license_front and license_back
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

            return redirect()->route('admin.driver.forfeiture', ['driver_id' => $request->driver_id]);
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

    public function forfeiture($driver_id)
    {
        $currentStep = 4;
        return view('admin.driver.forfeiture', compact('currentStep', 'driver_id'));
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

            return redirect()->route('admin.driver.violation', ['driver_id' => $request->driver_id]);
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

    public function violation($driver_id)
    {
        $currentStep = 5;
        return view('admin.driver.violation-record', compact('currentStep', 'driver_id'));
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

            // Redirect to next step (update with your actual next step route)
            // For now, redirect back to show success message
            return redirect()->route('admin.driver.alcohol.and.drug.test', ['driver_id' => $request->driver_id]);
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

    public function alcoholAndDrugTest($driver_id)
    {
        $currentStep = 6;

        $driver_document = DriverDocument::where('driver_id', $driver_id)->first();

        if (!$driver_document) {
            $driver_document = new DriverDocument();
        }

        return view('admin.driver.alcohol-and-drug-test', compact('currentStep', 'driver_id', 'driver_document'));
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

            // Redirect to next step
            return redirect()->route('admin.driver.psp', ['driver_id' => $request->driver_id]);
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

    public function psp($driver_id)
    {
        $currentStep = 7;

        $driver_document = DriverDocument::where('driver_id', $driver_id)->first();

        if (!$driver_document) {
            $driver_document = new DriverDocument();
        }

        $authUser = Auth::user()->load('company');
        if (!$authUser) {
            toastr()->error('Authenticated user not found.');
            return redirect()->back();
        }

        return view('admin.driver.psp-driver-disclosure', compact('currentStep', 'driver_id', 'driver_document', 'authUser'));
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

            // Redirect to next step (Alcohol & Drug Testing Policy)
            return redirect()->route('admin.driver.fmcsa.consent', ['driver_id' => $request->driver_id]);
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

    public function consent($driver_id)
    {
        $currentStep = 8;

        $driver = Driver::find($driver_id);
        $driver_name = $driver ? $driver->first_name . ' ' . $driver->last_name : 'Applicant';

        $driver_document = DriverDocument::where('driver_id', $driver_id)->first();
        if (!$driver_document) {
            $driver_document = new DriverDocument();
        }

        $authUser = Auth::user()->load('company');
        if (!$authUser) {
            toastr()->error('Authenticated user not found.');
            return redirect()->back();
        }

        return view('admin.driver.general-consent', compact('currentStep', 'driver_id', 'driver_document', 'driver_name', 'authUser'));
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

            // Redirect to next step (Alcohol & Drug Testing Policy)
            return redirect()->route('admin.driver.alcohol.and.drug.test.policy', ['driver_id' => $request->driver_id]);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error saving FMCSA consent: ' . $e->getMessage());
            toastr()->error('An error occurred while saving the FMCSA consent. Please try again.');
            return back()->withInput()->withErrors([
                'error' => 'An unexpected error occurred. Please try again.'
            ]);
        }
    }

    public function alcoholAndDrugTestPolicy($driver_id)
    {
        $currentStep = 9;

        $driver = Driver::find($driver_id);
        $driver_name = $driver ? $driver->first_name . ' ' . $driver->last_name : 'Applicant';

        $driver_document = DriverDocument::where('driver_id', $driver_id)->first();
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

        return view('admin.driver.alcohol-and-drug-test-policy', compact('currentStep', 'driver_id', 'driver_document', 'driver_name', 'authUser', 'policyPdf'));
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

            // Redirect to next step (Alcohol & Drug Testing Policy)
            return redirect()->route('admin.driver.general.work.policy', ['driver_id' => $request->driver_id]);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error saving Alcohol & Drug Testing Policy: ' . $e->getMessage());
            toastr()->error('An error occurred while saving the Alcohol & Drug Testing Policy. Please try again.');
            return back()->withInput()->withErrors([
                'error' => 'An unexpected error occurred. Please try again.'
            ]);
        }
    }


    public function generalWorkPolicy($driver_id)
    {
        $currentStep = 10;

        $driver = Driver::find($driver_id);
        $driver_name = $driver ? $driver->first_name . ' ' . $driver->last_name : 'Applicant';

        $driver_document = DriverDocument::where('driver_id', $driver_id)->first();
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

        return view('admin.driver.general-work-policy', compact('currentStep', 'driver_id', 'driver_document', 'driver_name', 'authUser', 'policyPdf'));
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


            DB::commit();
            toastr()->success('General Work Policy saved successfully.');

            // Redirect to next step ()
            return redirect()->route('admin.driver.index');
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error saving General Work Policy: ' . $e->getMessage());
            toastr()->error('An error occurred while saving the General Work Policy. Please try again.');
            return back()->withInput()->withErrors([
                'error' => 'An unexpected error occurred. Please try again.'
            ]);
        }
    }
}
