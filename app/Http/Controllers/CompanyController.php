<?php

namespace App\Http\Controllers;

use App\Exceptions\LimitReached;
use App\Models\Company;
use App\Models\PolicyPdf;
use App\Services\Files\StoredFileService;
use App\Support\Ui\Toast;
use App\Tenancy\TenantContext;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
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

        // Status belongs to the platform (/platform); the owner's login, email and password to Profile (D3b)
        // Form validation
        $validator = Validator::make($request->all(), [
            'company_name' => 'required|string|max:255',
            'address' => 'required|string|max:255',
            'city' => 'required|string|max:255',
            'state' => 'required|string|size:2',
            'zip' => 'required|string|max:20',
            'description' => 'nullable|string',
            'phone' => 'nullable|string|max:20',
            'fax' => 'nullable|string|max:20',
            'logo' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:2048',
        ]);

        // Any error checking
        if ($validator->fails()) {
            foreach ($validator->errors()->all() as $error) {
                Toast::error($error);
            }

            return back()->withInput()->withErrors($validator);
        }

        DB::beginTransaction();

        try {
            // Handle logo upload / removal; the old file is deleted once committed (PO-22)
            $oldLogo = $company->logo_path;
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
                'state_code' => strtoupper((string) $request->state),
                'zip' => $request->zip,
                'description' => $request->description,
                'phone' => $request->phone,
                'fax' => $request->fax,
                'logo_path' => $logoPath,
            ]);

            // Commit transaction
            DB::commit();

            if ($oldLogo && $oldLogo !== $logoPath) {
                Storage::disk('public')->delete($oldLogo);
            }

            Toast::success('Company updated successfully!');

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

            Toast::error('Failed to update company. Please try again.');

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

    public function policyPDFStore(Request $request, StoredFileService $files)
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
                Toast::error($error);
            }

            return back()->withInput()->withErrors($validator);
        }

        // Check if at least one file was uploaded
        if (! $request->hasFile('alcohol_drug_test_policy_pdf') && ! $request->hasFile('general_work_policy_pdf')) {
            Toast::warning('Please select at least one PDF file to upload.');

            return back()->withInput();
        }

        try {
            // Private stored files; applicants and staff open them through policyFile() routes
            $files->transaction(function () use ($request, $files) {
                $policyPdf = PolicyPdf::firstOrNew(['company_id' => $this->policyCompanyId()]);

                foreach (PolicyPdf::POLICIES as $policy) {
                    if ($request->hasFile("{$policy}_policy_pdf")) {
                        $files->purgeWhenUnreferenced($policyPdf->getAttribute("{$policy}_policy_file_id"));
                        $policyPdf->setAttribute("{$policy}_policy_file_id", $files->store($request->file("{$policy}_policy_pdf"), 'policies')->id);
                    }
                }

                $policyPdf->save();
            });

            // Prepare success message based on what was uploaded
            $message = '';
            if ($request->hasFile('alcohol_drug_test_policy_pdf') && $request->hasFile('general_work_policy_pdf')) {
                $message = 'Both policy PDFs uploaded successfully!';
            } elseif ($request->hasFile('alcohol_drug_test_policy_pdf')) {
                $message = 'Alcohol & Drug Test Policy PDF uploaded successfully!';
            } else {
                $message = 'General Work Policy PDF uploaded successfully!';
            }

            Toast::success($message);

            return redirect()->route('admin.settings.policy.pdf');
        } catch (LimitReached $e) {
            throw $e;
        } catch (Exception $e) {
            Log::error('Policy PDF Upload Error: '.$e->getMessage(), [
                'exception' => $e,
                'request_data' => $request->except(['alcohol_drug_test_policy_pdf', 'general_work_policy_pdf']),
            ]);

            Toast::error('An error occurred while uploading the PDF files. Please try again.');

            return back()->withInput()->withErrors(['system_error' => 'Upload failed. Please try again.']);
        }
    }

    /**
     * One of the company's policy PDFs, for its staff (settings and the driver wizard).
     */
    public function policyFile(string $policy, StoredFileService $files)
    {
        return $files->response(PolicyPdf::resolveFor($this->policyCompanyId())->fileFor($policy));
    }

    /**
     * The policy set of the current tenant (the routes check the permission).
     */
    private function policyCompanyId(): int
    {
        return app(TenantContext::class)->requireCompanyId();
    }

    /**
     * Users with company.edit (the route) manage their own company only: another is a 404 (03 §5).
     */
    private function ensureCanManageCompany(Company $company): void
    {
        abort_unless((int) auth()->user()->company_id === (int) $company->id, 404);
    }
}
