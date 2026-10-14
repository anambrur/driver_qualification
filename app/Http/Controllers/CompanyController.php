<?php

namespace App\Http\Controllers;

use App\Models\Company;
use App\Models\PolicyPdf;
use App\Models\User;
use App\Services\Company\CompanyProvisioner;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Yajra\DataTables\Facades\DataTables;

class CompanyController extends Controller
{
    public function index(Request $request)
    {
        if ($request->ajax()) {
            $companies = Company::with('user')->select('companies.*');

            return DataTables::of($companies)
                ->addIndexColumn()
                ->addColumn('logo', function ($company) {
                    if ($company->logo) {
                        return '<img src="'.e(asset('storage/'.$company->logo)).'" alt="'.e($company->company_name).'" class="w-10 h-10 rounded-full object-cover">';
                    }

                    return '<div class="w-10 h-10 rounded-full bg-gray-200 dark:bg-gray-700 flex items-center justify-center">
                            <i class="fas fa-building text-gray-500 dark:text-gray-400"></i>
                        </div>';
                })
                ->addColumn('status', function ($company) {
                    $status = $company->status === 'active'
                        ? '<span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200">Active</span>'
                        : '<span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-200">Inactive</span>';

                    return $status;
                })
                ->addColumn('user_email', function ($company) {
                    return $company->user->email ?? 'N/A';
                })
                ->addColumn('apply_url', function ($company) {
                    // Check if slug exists
                    if ($company->slug) {
                        // Create the URL
                        $applyUrl = e(url('/').'/'.$company->slug.'/apply');

                        // Return with copy button
                        return '<div class="flex items-center space-x-2">
                                <a href="'.$applyUrl.'" 
                                   target="_blank" 
                                   class="inline-flex items-center text-brand-600 hover:text-brand-700 dark:text-brand-400 apply-url-link"
                                   title="Open Application Form">
                                    <i class="fas fa-external-link-alt mr-1 text-xs"></i>
                                    Link
                                </a>
                                <button type="button" 
                                        data-url="'.$applyUrl.'"
                                        class="text-gray-500 hover:text-brand-500 dark:text-gray-400 dark:hover:text-brand-400 copy-url-btn"
                                        title="Copy URL">
                                    <i class="fas fa-copy text-xs"></i>
                                </button>
                            </div>';
                    }

                    // Return disabled state if no slug
                    return '<span class="text-gray-400 dark:text-gray-500 cursor-not-allowed" title="Not available">
                            <i class="fas fa-ban mr-1 text-xs"></i>
                            N/A
                        </span>';
                })
                ->addColumn('action', function ($company) {
                    // Deactivate, never delete (PO-13); not offered for the admin's own company
                    $deactivateBtn = $company->status === 'active' && ! $this->isOwnCompany($company)
                        ? '<button type="button" onclick="deleteCompany('.(int) $company->id.')" class="inline-flex items-center justify-center w-8 h-8 text-red-600 border border-red-200 rounded-lg hover:bg-red-50 dark:border-red-800 dark:text-red-400 dark:hover:bg-red-900/30" title="Deactivate">
                                <i class="fas fa-ban text-xs"></i>
                            </button>'
                        : '';

                    return '<div class="flex items-center space-x-2">
                            <a href="'.route('admin.settings.company.edit', $company->id).'" class="inline-flex items-center justify-center w-8 h-8 text-blue-600 border border-blue-200 rounded-lg hover:bg-blue-50 dark:border-blue-800 dark:text-blue-400 dark:hover:bg-blue-900/30" title="Edit">
                                <i class="fas fa-edit text-xs"></i>
                            </a>
                            '.$deactivateBtn.'
                        </div>';
                })
                ->rawColumns(['logo', 'status', 'apply_url', 'action'])
                ->make(true);
        }

        return view('admin.settings.company.index');
    }

    public function create()
    {
        return view('admin.settings.company.create');
    }

    public function store(Request $request, CompanyProvisioner $provisioner)
    {
        // Form validation
        $validator = Validator::make($request->all(), [
            'company_name' => 'required|string|max:255',
            'email' => 'required|email|unique:companies,email|unique:users,email',
            'password' => 'required|string|min:8|confirmed',
            'address' => 'required|string|max:255',
            'city' => 'required|string|max:255',
            'state' => 'required|string|size:2',
            'zip' => 'required|string|max:20',
            'description' => 'nullable|string',
            'phone' => 'nullable|string|max:20',
            'fax' => 'nullable|string|max:20',
            'logo' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:2048',
            'status' => 'required|in:active,inactive',
        ]);

        // Any error checking
        if ($validator->fails()) {
            foreach ($validator->errors()->all() as $error) {
                toastr()->error($error);
            }

            return back()->withInput()->withErrors($validator);
        }

        try {
            // Handle logo upload
            $logoPath = null;
            if ($request->hasFile('logo')) {
                $logoFile = $request->file('logo');

                // Define storage path
                $storagePath = 'images/companies/logos';

                // Check if directory exists, if not create it
                $fullPath = storage_path('app/public/'.$storagePath);
                if (! file_exists($fullPath)) {
                    File::makeDirectory($fullPath, 0755, true, true);
                }

                $logoPath = $logoFile->storeAs($storagePath, $logoFile->hashName(), 'public');
            }

            $isActive = $request->status === 'active';

            $provisioner->provision([
                'company_name' => $request->company_name,
                'email' => $request->email,
                'address' => $request->address,
                'city' => $request->city,
                'state_code' => strtoupper($request->state),
                'zip' => $request->zip,
                'description' => $request->description,
                'phone' => $request->phone,
                'fax' => $request->fax,
                'logo_path' => $logoPath,
                'status' => $isActive ? 'active' : 'deactivated',
                'status_changed_at' => $isActive ? null : now(),
            ], [
                'name' => $request->company_name,
                'email' => $request->email,
                'password' => Hash::make($request->password),
                'email_verified_at' => now(), // Auto-verify company emails
                'status' => $isActive ? 'active' : 'inactive',
            ]);

            // CORRECT TOASTR SUCCESS SYNTAX
            toastr()->success('Company created successfully!');

            // Redirect to company index or show page
            return redirect()->route('admin.settings.company');
        } catch (Exception $e) {
            // Log the error
            logger()->error('Company creation failed: '.$e->getMessage(), [
                'exception' => $e,
                'request_data' => $request->except(['password', 'password_confirmation']),
            ]);

            // CORRECT TOASTR ERROR SYNTAX
            toastr()->error('Failed to create company. Please try again.');

            return back()->withInput()->withErrors([
                'system_error' => 'An error occurred while creating the company. Please try again.',
            ]);
        }
    }

    public function edit($id)
    {
        $company = Company::findOrFail($id);
        $this->ensureCanManageCompany($company);

        return view('admin.settings.company.edit', compact('company'));
    }

    public function update(Request $request, $id)
    {
        $company = Company::findOrFail($id);
        $this->ensureCanManageCompany($company);

        // Status and login email are super-admin fields; owners change their email via Profile
        $isSuperAdmin = auth()->user()->hasRole('super-admin');

        // Form validation
        $validator = Validator::make($request->all(), [
            'company_name' => 'required|string|max:255',
            'email' => [
                Rule::requiredIf($isSuperAdmin),
                'email',
                Rule::unique('companies', 'email')->ignore($company->id),
                Rule::unique('users', 'email')->ignore($company->user_id),
            ],
            'password' => 'nullable|string|min:8|confirmed',
            'address' => 'required|string|max:255',
            'city' => 'required|string|max:255',
            'state' => 'required|string|max:255',
            'zip' => 'required|string|max:20',
            'description' => 'nullable|string',
            'phone' => 'nullable|string|max:20',
            'fax' => 'nullable|string|max:20',
            'logo' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:2048',
            'status' => [Rule::requiredIf($isSuperAdmin), 'in:active,inactive'],
        ]);

        // Any error checking
        if ($validator->fails()) {
            foreach ($validator->errors()->all() as $error) {
                toastr()->error($error);
            }

            return back()->withInput()->withErrors($validator);
        }

        DB::beginTransaction();

        try {
            // Update user
            $userData = ['name' => $request->company_name];

            if ($isSuperAdmin) {
                $userData['email'] = $request->email;
                $userData['status'] = $request->status === 'active' ? 'active' : 'inactive';
            }

            // Update password only if provided
            if ($request->filled('password')) {
                $userData['password'] = Hash::make($request->password);
            }

            $company->user->update($userData);

            // Handle logo upload / removal; the old file is deleted once committed (PO-22)
            $oldLogo = $company->logo;
            $logoPath = $oldLogo;
            if ($request->hasFile('logo')) {
                $logoFile = $request->file('logo');
                $logoPath = $logoFile->storeAs('companies/logos', $logoFile->hashName(), 'public');
            } elseif ($request->boolean('remove_logo')) {
                $logoPath = null;
            }

            // Generate new slug if company name changed
            $slug = $company->slug;
            if ($company->company_name !== $request->company_name) {
                $slug = Str::slug($request->company_name);
                $originalSlug = $slug;
                $slugCounter = 1;

                // Ensure slug is unique
                while (Company::where('slug', $slug)->where('id', '!=', $id)->exists()) {
                    $slug = $originalSlug.'-'.$slugCounter;
                    $slugCounter++;
                }
            }

            // Update company
            $company->update([
                'company_name' => $request->company_name,
                'slug' => $slug,
                'email' => $isSuperAdmin ? $request->email : $company->email,
                'address' => $request->address,
                'city' => $request->city,
                'state' => $request->state,
                'zip' => $request->zip,
                'description' => $request->description,
                'phone' => $request->phone,
                'fax' => $request->fax,
                'logo' => $logoPath,
                'status' => $isSuperAdmin ? $request->status : $company->status,
            ]);

            // Commit transaction
            DB::commit();

            if ($oldLogo && $oldLogo !== $logoPath) {
                Storage::disk('public')->delete($oldLogo);
            }

            toastr()->success('Company updated successfully!');

            if (auth()->user()->hasRole('company')) {
                return redirect()->route('admin.settings.company.edit', $company->id);
            }

            return redirect()->route('admin.settings.company');
        } catch (Exception $e) {
            // Rollback transaction on error
            DB::rollBack();

            // Log the error
            logger()->error('Company update failed: '.$e->getMessage(), [
                'exception' => $e,
                'company_id' => $id,
                'request_data' => $request->except(['password', 'password_confirmation']),
            ]);

            toastr()->error('Failed to update company. Please try again.');

            return back()->withInput()->withErrors([
                'system_error' => 'An error occurred while updating the company. Please try again.',
            ]);
        }
    }

    /**
     * Deactivate a company (PO-13): the company and its owner become inactive, so the owner
     * can no longer log in, and every record and file is kept. A company is never hard-deleted
     * here, and a super-admin can't deactivate their own company.
     */
    public function destroy($id)
    {
        $company = Company::with('user')->findOrFail($id);

        if ($this->isOwnCompany($company)) {
            return response()->json([
                'success' => false,
                'message' => 'You cannot deactivate your own company.',
            ], 422);
        }

        DB::beginTransaction();

        try {
            $company->update(['status' => 'inactive']);
            $company->user?->update(['status' => 'inactive']);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Company deactivated. Its data is kept and the owner can no longer log in.',
            ]);
        } catch (Exception $e) {
            DB::rollBack();

            logger()->error('Company deactivation failed: '.$e->getMessage(), [
                'exception' => $e,
                'company_id' => $id,
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to deactivate company. Please try again.',
            ], 500);
        }
    }

    /**
     * Policy PDFs (PO-14): a super-admin manages the platform set (company_id null), which every
     * company falls back to; a company owner manages their own company's set.
     */
    public function policyPDF()
    {
        $companyId = $this->policyCompanyId();
        $policy = PolicyPdf::where('company_id', $companyId)->first();

        return view('admin.settings.company.policy-pdf', [
            'existingPolicies' => $policy ? collect([$policy]) : collect(),
            'isPlatformSet' => $companyId === null,
        ]);
    }

    public function policyPDFStore(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'alcohol_drug_test_policy_pdf' => 'nullable|file|mimes:pdf|max:10240', // 10MB
            'general_work_policy_pdf' => 'nullable|file|mimes:pdf|max:10240',
        ], [
            'alcohol_drug_test_policy_pdf.mimes' => 'The alcohol & drug test policy must be a PDF file.',
            'alcohol_drug_test_policy_pdf.max' => 'The alcohol & drug test policy PDF must not exceed 10MB.',
            'general_work_policy_pdf.mimes' => 'The general work policy must be a PDF file.',
            'general_work_policy_pdf.max' => 'The general work policy PDF must not exceed 10MB.',
        ]);

        if ($validator->fails()) {
            foreach ($validator->errors()->all() as $error) {
                toastr()->error($error);
            }

            return back()->withInput()->withErrors($validator);
        }

        try {
            // Check if at least one file was uploaded
            if (! $request->hasFile('alcohol_drug_test_policy_pdf') && ! $request->hasFile('general_work_policy_pdf')) {
                toastr()->warning('Please select at least one PDF file to upload.');

                return back()->withInput();
            }

            $alcoholPdfPath = null;
            $generalPdfPath = null;

            // The platform set, or the owner's own company's set (PO-14)
            $companyId = $this->policyCompanyId();

            // Define storage path. Policies are shown to job applicants on the public
            // application form, so they stay on the public disk, under a random name.
            $storagePath = $companyId === null ? 'policy_pdfs' : 'policy_pdfs/'.$companyId;

            // Ensure directory exists
            $fullPath = storage_path('app/public/'.$storagePath);
            if (! File::exists($fullPath)) {
                File::makeDirectory($fullPath, 0755, true, true);
            }

            // Handle Alcohol & Drug Test Policy PDF
            if ($request->hasFile('alcohol_drug_test_policy_pdf')) {
                $alcoholPdfFile = $request->file('alcohol_drug_test_policy_pdf');
                $alcoholPdfPath = $alcoholPdfFile->storeAs($storagePath, $alcoholPdfFile->hashName(), 'public');
            }

            // Handle General Work Policy PDF
            if ($request->hasFile('general_work_policy_pdf')) {
                $generalPdfFile = $request->file('general_work_policy_pdf');
                $generalPdfPath = $generalPdfFile->storeAs($storagePath, $generalPdfFile->hashName(), 'public');
            }

            // Create or update policy PDF record
            $policyPdf = PolicyPdf::where('company_id', $companyId)->first();

            if (! $policyPdf) {
                // Create first record if none exists
                $policyPdf = PolicyPdf::create([
                    'company_id' => $companyId,
                    'alcohol_drug_test_policy_pdf' => $alcoholPdfPath,
                    'general_work_policy_pdf' => $generalPdfPath,
                ]);
            } else {
                // Delete old files before updating database
                if ($request->hasFile('alcohol_drug_test_policy_pdf') && $policyPdf->alcohol_drug_test_policy_pdf) {
                    Storage::disk('public')->delete($policyPdf->alcohol_drug_test_policy_pdf);
                }

                if ($request->hasFile('general_work_policy_pdf') && $policyPdf->general_work_policy_pdf) {
                    Storage::disk('public')->delete($policyPdf->general_work_policy_pdf);
                }

                // Update only the fields that have new files
                $updateData = [];
                if ($request->hasFile('alcohol_drug_test_policy_pdf')) {
                    $updateData['alcohol_drug_test_policy_pdf'] = $alcoholPdfPath;
                }
                if ($request->hasFile('general_work_policy_pdf')) {
                    $updateData['general_work_policy_pdf'] = $generalPdfPath;
                }

                $policyPdf->update($updateData);
            }

            // Prepare success message based on what was uploaded
            $message = '';
            if ($request->hasFile('alcohol_drug_test_policy_pdf') && $request->hasFile('general_work_policy_pdf')) {
                $message = 'Both policy PDFs uploaded successfully!';
            } elseif ($request->hasFile('alcohol_drug_test_policy_pdf')) {
                $message = 'Alcohol & Drug Test Policy PDF uploaded successfully!';
            } else {
                $message = 'General Work Policy PDF uploaded successfully!';
            }

            toastr()->success($message);

            return $companyId === null
                ? redirect()->route('admin.settings.company')
                : redirect()->route('admin.settings.policy.pdf');
        } catch (Exception $e) {
            Log::error('Policy PDF Upload Error: '.$e->getMessage(), [
                'exception' => $e,
                'request_data' => $request->except(['alcohol_drug_test_policy_pdf', 'general_work_policy_pdf']),
            ]);

            toastr()->error('An error occurred while uploading the PDF files. Please try again.');

            return back()->withInput()->withErrors(['system_error' => 'Upload failed. Please try again.']);
        }
    }

    /**
     * The policy set the user manages: null (the platform set) for a super-admin, or a user who
     * holds policy-pdf.edit without a company; otherwise the user's own company.
     */
    private function policyCompanyId(): ?int
    {
        $user = auth()->user();

        if ($user->hasRole('super-admin')) {
            return null;
        }

        $companyId = $user->company?->id;

        if ($companyId === null) {
            abort_unless($user->can('company.edit'), 403);
        }

        return $companyId;
    }

    private function isOwnCompany(Company $company): bool
    {
        return (int) $company->user_id === (int) auth()->id();
    }

    /**
     * Company-role users may only manage their own company profile.
     */
    private function ensureCanManageCompany(Company $company): void
    {
        $user = auth()->user();

        if ($user->hasRole('super-admin')) {
            return;
        }

        // Another company is a 404 (03 §5)
        abort_unless((int) $user->company_id === (int) $company->id, 404);

        if ($user->hasRole('company')) {
            return;
        }

        abort(403, 'You can only manage your own company account.');
    }
}
