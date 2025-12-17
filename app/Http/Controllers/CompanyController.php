<?php

namespace App\Http\Controllers;

use Exception;
use App\Models\User;
use App\Models\Company;
use App\Models\PolicyPdf;
use Illuminate\Support\Str;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Hash;
use Yajra\DataTables\Facades\DataTables;
use Illuminate\Support\Facades\Validator;

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
                        return '<img src="' . asset('storage/' . $company->logo) . '" alt="' . $company->company_name . '" class="w-10 h-10 rounded-full object-cover">';
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
                ->addColumn('action', function ($company) {
                    return '<div class="flex items-center space-x-2">
                                <a href="' . route('admin.settings.company.edit', $company->id) . '" class="inline-flex items-center justify-center w-8 h-8 text-blue-600 border border-blue-200 rounded-lg hover:bg-blue-50 dark:border-blue-800 dark:text-blue-400 dark:hover:bg-blue-900/30">
                                    <i class="fas fa-edit text-xs"></i>
                                </a>
                                <button type="button" onclick="deleteCompany(' . $company->id . ')" class="inline-flex items-center justify-center w-8 h-8 text-red-600 border border-red-200 rounded-lg hover:bg-red-50 dark:border-red-800 dark:text-red-400 dark:hover:bg-red-900/30">
                                    <i class="fas fa-trash text-xs"></i>
                                </button>
                            </div>';
                })
                ->rawColumns(['logo', 'status', 'action'])
                ->make(true);
        }

        return view('admin.settings.company.index');
    }

    public function create()
    {
        return view('admin.settings.company.create');
    }

    public function store(Request $request)
    {
        // Form validation
        $validator = Validator::make($request->all(), [
            'company_name' => 'required|string|max:255',
            'email' => 'required|email|unique:companies,email',
            'password' => 'required|string|min:8|confirmed',
            'address' => 'required|string|max:255',
            'city' => 'required|string|max:255',
            'state' => 'required|string|max:255',
            'zip' => 'required|string|max:20',
            'description' => 'nullable|string',
            'phone' => 'nullable|string|max:20',
            'fax' => 'nullable|string|max:20',
            'logo' => 'nullable|image|mimes:jpeg,png,jpg,gif,svg|max:2048',
            'status' => 'required|in:active,inactive',
        ]);

        // Any error checking
        if ($validator->fails()) {
            foreach ($validator->errors()->all() as $error) {
                toastr()->error($error);
            }
            return back()->withInput();
        }

        DB::beginTransaction();

        try {
            // Generate a random password if not provided (though it's required in validation)
            $password = $request->password ?: Str::random(12);

            // Create user first
            $user = User::create([
                'name' => $request->company_name,
                'email' => $request->email,
                'password' => Hash::make($password),
                'email_verified_at' => now(), // Auto-verify company emails
                'status' => $request->status === 'active' ? 'active' : 'inactive',
            ]);

            // Handle logo upload
            $logoPath = null;
            if ($request->hasFile('logo')) {
                $logoFile = $request->file('logo');
                $logoName = time() . '_' . Str::slug($request->company_name) . '.' . $logoFile->getClientOriginalExtension();

                // Define storage path
                $storagePath = 'images/companies/logos';

                // Check if directory exists, if not create it
                $fullPath = storage_path('app/public/' . $storagePath);
                if (!file_exists($fullPath)) {
                    File::makeDirectory($fullPath, 0755, true, true);
                }

                $logoPath = $logoFile->storeAs($storagePath, $logoName, 'public');
            }

            // Generate slug for company
            $slug = Str::slug($request->company_name);
            $originalSlug = $slug;
            $slugCounter = 1;

            // Ensure slug is unique
            while (Company::where('slug', $slug)->exists()) {
                $slug = $originalSlug . '-' . $slugCounter;
                $slugCounter++;
            }

            // Create company with user_id
            $company = Company::create([
                'user_id' => $user->id,
                'company_name' => $request->company_name,
                'slug' => $slug,
                'email' => $request->email,
                'address' => $request->address,
                'city' => $request->city,
                'state' => $request->state,
                'zip' => $request->zip,
                'description' => $request->description,
                'phone' => $request->phone,
                'fax' => $request->fax,
                'logo' => $logoPath,
                'status' => $request->status,
            ]);

            // Commit transaction
            DB::commit();

            // CORRECT TOASTR SUCCESS SYNTAX
            toastr()->success('Company created successfully!');

            // Redirect to company index or show page
            return redirect()->route('admin.settings.company');
        } catch (Exception $e) {
            // Rollback transaction on error
            DB::rollBack();

            // Log the error
            logger()->error('Company creation failed: ' . $e->getMessage(), [
                'exception' => $e,
                'request_data' => $request->except(['password', 'password_confirmation'])
            ]);

            // CORRECT TOASTR ERROR SYNTAX
            toastr()->error('Failed to create company. Please try again.');

            return back()->withInput()->withErrors([
                'system_error' => 'An error occurred while creating the company. Please try again.'
            ]);
        }
    }

    public function edit($id)
    {
        $company = Company::with('user')->findOrFail($id);
        return view('admin.settings.company.edit', compact('company'));
    }

    public function update(Request $request, $id)
    {
        $company = Company::with('user')->findOrFail($id);

        // Form validation
        $validator = Validator::make($request->all(), [
            'company_name' => 'required|string|max:255',
            'email' => 'required|email|unique:companies,email,' . $id,
            'password' => 'nullable|string|min:8|confirmed',
            'address' => 'required|string|max:255',
            'city' => 'required|string|max:255',
            'state' => 'required|string|max:255',
            'zip' => 'required|string|max:20',
            'description' => 'nullable|string',
            'phone' => 'nullable|string|max:20',
            'fax' => 'nullable|string|max:20',
            'logo' => 'nullable|image|mimes:jpeg,png,jpg,gif,svg|max:2048',
            'status' => 'required|in:active,inactive',
        ]);

        // Any error checking
        if ($validator->fails()) {
            foreach ($validator->errors()->all() as $error) {
                toastr()->error($error);
            }
            return back()->withInput();
        }

        DB::beginTransaction();

        try {
            // Update user
            $userData = [
                'name' => $request->company_name,
                'email' => $request->email,
                'status' => $request->status === 'active' ? 'active' : 'inactive',
            ];

            // Update password only if provided
            if ($request->filled('password')) {
                $userData['password'] = Hash::make($request->password);
            }

            $company->user->update($userData);

            // Handle logo upload
            $logoPath = $company->logo;
            if ($request->hasFile('logo')) {
                $logoFile = $request->file('logo');
                $logoName = time() . '_' . Str::slug($request->company_name) . '.' . $logoFile->getClientOriginalExtension();
                $logoPath = $logoFile->storeAs('companies/logos', $logoName, 'public');
            }

            // Generate new slug if company name changed
            $slug = $company->slug;
            if ($company->company_name !== $request->company_name) {
                $slug = Str::slug($request->company_name);
                $originalSlug = $slug;
                $slugCounter = 1;

                // Ensure slug is unique
                while (Company::where('slug', $slug)->where('id', '!=', $id)->exists()) {
                    $slug = $originalSlug . '-' . $slugCounter;
                    $slugCounter++;
                }
            }

            // Update company
            $company->update([
                'company_name' => $request->company_name,
                'slug' => $slug,
                'email' => $request->email,
                'address' => $request->address,
                'city' => $request->city,
                'state' => $request->state,
                'zip' => $request->zip,
                'description' => $request->description,
                'phone' => $request->phone,
                'fax' => $request->fax,
                'logo' => $logoPath,
                'status' => $request->status,
            ]);

            // Commit transaction
            DB::commit();

            toastr()->success('Company updated successfully!');
            return redirect()->route('admin.settings.company');
        } catch (Exception $e) {
            // Rollback transaction on error
            DB::rollBack();

            // Log the error
            logger()->error('Company update failed: ' . $e->getMessage(), [
                'exception' => $e,
                'company_id' => $id,
                'request_data' => $request->except(['password', 'password_confirmation'])
            ]);

            toastr()->error('Failed to update company. Please try again.');
            return back()->withInput()->withErrors([
                'system_error' => 'An error occurred while updating the company. Please try again.'
            ]);
        }
    }

    public function destroy($id)
    {
        DB::beginTransaction();

        try {
            $company = Company::with('user')->findOrFail($id);
            $user = $company->user;

            // Delete company
            $company->delete();

            // Delete associated user
            if ($user) {
                $user->delete();
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Company deleted successfully!'
            ]);
        } catch (Exception $e) {
            DB::rollBack();

            logger()->error('Company deletion failed: ' . $e->getMessage(), [
                'exception' => $e,
                'company_id' => $id
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to delete company. Please try again.'
            ], 500);
        }
    }

    public function policyPDF()
    {
        return view('admin.settings.company.policy-pdf');
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
            if (!$request->hasFile('alcohol_drug_test_policy_pdf') && !$request->hasFile('general_work_policy_pdf')) {
                toastr()->warning('Please select at least one PDF file to upload.');
                return back()->withInput();
            }

            $alcoholPdfPath = null;
            $generalPdfPath = null;

            // Define storage path
            $storagePath = 'policy_pdfs';

            // Ensure directory exists
            $fullPath = storage_path('app/public/' . $storagePath);
            if (!File::exists($fullPath)) {
                File::makeDirectory($fullPath, 0755, true, true);
            }

            // Handle Alcohol & Drug Test Policy PDF
            if ($request->hasFile('alcohol_drug_test_policy_pdf')) {
                $alcoholPdfFile = $request->file('alcohol_drug_test_policy_pdf');

                // Generate a safe filename
                $originalName = pathinfo($alcoholPdfFile->getClientOriginalName(), PATHINFO_FILENAME);
                $safeName = Str::slug($originalName);
                $alcoholPdfName = time() . '_alcohol_drug_policy_' . $safeName . '.' . $alcoholPdfFile->getClientOriginalExtension();

                // Store file
                $alcoholPdfPath = $alcoholPdfFile->storeAs($storagePath, $alcoholPdfName, 'public');
            }

            // Handle General Work Policy PDF
            if ($request->hasFile('general_work_policy_pdf')) {
                $generalPdfFile = $request->file('general_work_policy_pdf');

                // Generate a safe filename
                $originalName = pathinfo($generalPdfFile->getClientOriginalName(), PATHINFO_FILENAME);
                $safeName = Str::slug($originalName);
                $generalPdfName = time() . '_general_work_policy_' . $safeName . '.' . $generalPdfFile->getClientOriginalExtension();

                // Store file
                $generalPdfPath = $generalPdfFile->storeAs($storagePath, $generalPdfName, 'public');
            }

            // Create or update policy PDF record
            $policyPdf = PolicyPdf::first();

            if (!$policyPdf) {
                // Create first record if none exists
                $policyPdf = PolicyPdf::create([
                    'alcohol_drug_test_policy_pdf' => $alcoholPdfPath,
                    'general_work_policy_pdf' => $generalPdfPath,
                ]);
            } else {
                // Delete old files before updating database
                if ($request->hasFile('alcohol_drug_test_policy_pdf') && $policyPdf->alcohol_drug_test_policy_pdf) {
                    $oldPath = storage_path('app/public/' . $policyPdf->alcohol_drug_test_policy_pdf);
                    if (File::exists($oldPath)) {
                        File::delete($oldPath);
                    }
                }

                if ($request->hasFile('general_work_policy_pdf') && $policyPdf->general_work_policy_pdf) {
                    $oldPath = storage_path('app/public/' . $policyPdf->general_work_policy_pdf);
                    if (File::exists($oldPath)) {
                        File::delete($oldPath);
                    }
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
            return redirect()->route('admin.settings.company');
        } catch (\Exception $e) {
            Log::error('Policy PDF Upload Error: ' . $e->getMessage(), [
                'exception' => $e,
                'request_data' => $request->except(['alcohol_drug_test_policy_pdf', 'general_work_policy_pdf'])
            ]);

            toastr()->error('An error occurred while uploading the PDF files. Please try again.');
            return back()->withInput()->withErrors(['system_error' => 'Upload failed: ' . $e->getMessage()]);
        }
    }
}
