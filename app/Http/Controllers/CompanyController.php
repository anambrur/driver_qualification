<?php

namespace App\Http\Controllers;

use App\Models\Company;
use App\Models\PolicyPdf;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class CompanyController extends Controller
{
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

        // Status belongs to the platform (/platform) and the login email to Profile (D3b)
        // Form validation
        $validator = Validator::make($request->all(), [
            'company_name' => 'required|string|max:255',
            'password' => 'nullable|string|min:8|confirmed',
            'address' => 'required|string|max:255',
            'city' => 'required|string|max:255',
            'state' => 'required|string|max:255',
            'zip' => 'required|string|max:20',
            'description' => 'nullable|string',
            'phone' => 'nullable|string|max:20',
            'fax' => 'nullable|string|max:20',
            'logo' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:2048',
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
                'address' => $request->address,
                'city' => $request->city,
                'state' => $request->state,
                'zip' => $request->zip,
                'description' => $request->description,
                'phone' => $request->phone,
                'fax' => $request->fax,
                'logo' => $logoPath,
            ]);

            // Commit transaction
            DB::commit();

            if ($oldLogo && $oldLogo !== $logoPath) {
                Storage::disk('public')->delete($oldLogo);
            }

            toastr()->success('Company updated successfully!');

            return redirect()->route('admin.settings.company.edit', $company->id);
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
     * Policy PDFs (PO-14): a company owner manages their own company's set. The platform default
     * set moves to site_settings (14 §3, WP9).
     */
    public function policyPDF()
    {
        $companyId = $this->policyCompanyId();
        $policy = PolicyPdf::where('company_id', $companyId)->first();

        return view('admin.settings.company.policy-pdf', [
            'existingPolicies' => $policy ? collect([$policy]) : collect(),
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
            $storagePath = 'policy_pdfs/'.$companyId;

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

            return redirect()->route('admin.settings.policy.pdf');
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
     * The policy set the user manages: their own company's (the route requires company.edit).
     */
    private function policyCompanyId(): int
    {
        return (int) auth()->user()->company_id;
    }

    /**
     * Company-role users may only manage their own company profile.
     */
    private function ensureCanManageCompany(Company $company): void
    {
        $user = auth()->user();

        // Another company is a 404 (03 §5)
        abort_unless((int) $user->company_id === (int) $company->id, 404);

        if ($user->hasRole('company')) {
            return;
        }

        abort(403, 'You can only manage your own company account.');
    }
}
