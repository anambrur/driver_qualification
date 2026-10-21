<?php

namespace App\Http\Controllers;

use App\Http\Requests\ReplyEmailRequest;
use App\Models\AuditLog;
use App\Models\Company;
use App\Models\SiteSetting;
use App\Services\Mail\ReplyToVerificationService;
use App\Support\Ui\Toast;
use App\Tenancy\TenantContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * The current company's email for replies, and the signed links that confirm it (company or platform).
 */
class ReplyEmailController extends Controller
{
    public function __construct(private ReplyToVerificationService $verification) {}

    public function store(ReplyEmailRequest $request): RedirectResponse
    {
        $company = $this->company();
        $this->verification->request($company, $request->validated('reply_to_email'));

        AuditLog::record($request->user(), $company->id, 'company.reply_email.requested', $company, [
            'pending_reply_to_email' => $company->pending_reply_to_email,
        ]);

        Toast::success($company->pending_reply_to_email
            ? "We sent a link to {$company->pending_reply_to_email}. Open it to start getting replies there."
            : 'This email already gets your replies.');

        return back();
    }

    public function destroy(Request $request): RedirectResponse
    {
        $company = $this->company();
        $this->verification->remove($company);

        AuditLog::record($request->user(), $company->id, 'company.reply_email.removed', $company);
        Toast::success('Replies will go to the company owner’s email again.');

        return back();
    }

    public function verifyCompany(Company $company, string $hash): View
    {
        return $this->result($this->verification->confirm($company, $hash), $company->reply_to_email);
    }

    public function verifyPlatform(string $hash): View
    {
        $setting = SiteSetting::getSettings();

        return $this->result($this->verification->confirm($setting, $hash), $setting->reply_to_email);
    }

    private function result(bool $verified, ?string $email): View
    {
        return view('settings.reply-email-verified', ['verified' => $verified, 'email' => $email]);
    }

    private function company(): Company
    {
        return Company::query()->findOrFail(app(TenantContext::class)->requireCompanyId());
    }
}
