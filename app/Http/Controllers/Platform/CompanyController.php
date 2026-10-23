<?php

namespace App\Http\Controllers\Platform;

use App\DataTables\Platform\CompanyTable;
use App\Features\Feature;
use App\Http\Controllers\Controller;
use App\Http\Requests\Platform\CompanyProfileRequest;
use App\Http\Requests\Platform\CompanyPurgeRequest;
use App\Http\Requests\Platform\CompanyStatusRequest;
use App\Jobs\RunCompanyPurge;
use App\Models\AuditLog;
use App\Models\Company;
use App\Models\PlanPrice;
use App\Models\PlatformUser;
use App\Models\User;
use App\Services\Authorization\OwnershipService;
use App\Services\Company\CompanyPurgeService;
use App\Services\Entitlements\Entitlements;
use App\Services\Entitlements\Limits;
use App\Services\Platform\PlatformCompanyService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

/**
 * Companies from the platform: metadata, usage counts, lifecycle and ownership (04 §5). No
 * drivers, documents or other tenant records are shown here.
 */
class CompanyController extends Controller
{
    public function __construct(private PlatformCompanyService $companies) {}

    public function index(Request $request): View|JsonResponse
    {
        if ($request->ajax()) {
            return (new CompanyTable($request))->json();
        }

        return view('platform.companies.index');
    }

    public function show(Company $company, CompanyPurgeService $purge): View
    {
        $canPurge = (bool) auth('platform')->user()?->can('platform.companies.purge');

        $company->load('owner:id,name,email');
        $limits = Entitlements::for($company);
        $usage = collect([Feature::MaxUsers, Feature::MaxDrivers, Feature::MaxVehicles, Feature::MaxTrailers])
            ->mapWithKeys(fn (Feature $limit) => [$limit->label() => [Limits::count($company->id, $limit), $limits->limit($limit)]]);

        return view('platform.companies.show', [
            'company' => $company,
            'usage' => $usage,
            'storageLimitGb' => $limits->limit(Feature::StorageGb),
            'subscription' => $company->subscriptions()->open()->with('planPrice.plan')->first(),
            'overrides' => DB::table('company_feature_overrides')->where('company_id', $company->id)->orderByDesc('id')->limit(50)->get(),
            'prices' => PlanPrice::query()->current()->with('plan:id,name')->get(),
            'features' => Feature::cases(),
            'purgeBlockers' => $canPurge ? $purge->blockers($company) : [],
            'purgeCounts' => $canPurge ? $purge->counts($company) : [],
        ]);
    }

    public function create(): View
    {
        return view('platform.companies.form', ['company' => null]);
    }

    public function store(CompanyProfileRequest $request): RedirectResponse
    {
        $company = $this->companies->create($this->actor($request), $request->profile(), $request->validated('owner_name'), $request->validated('owner_email'));

        return redirect()->route('platform.companies.show', $company)->with('success', 'Company created. The owner was emailed a link to set their password.');
    }

    public function edit(Company $company): View
    {
        return view('platform.companies.form', compact('company'));
    }

    public function update(CompanyProfileRequest $request, Company $company): RedirectResponse
    {
        $this->companies->update($this->actor($request), $company, $request->profile());

        return redirect()->route('platform.companies.show', $company)->with('success', 'Company updated.');
    }

    public function status(CompanyStatusRequest $request, Company $company): RedirectResponse
    {
        $this->companies->setStatus($this->actor($request), $company, $request->validated('status'), $request->validated('reason'));

        return back()->with('success', 'Company status updated.');
    }

    /**
     * Starts the purge (08 §15) as a queued job; the job re-checks the rules under a lock.
     */
    public function purge(CompanyPurgeRequest $request, Company $company, CompanyPurgeService $purge): RedirectResponse
    {
        if ($blockers = $purge->blockers($company)) {
            return back()->withErrors(['purge' => implode(' ', $blockers)]);
        }

        $actor = $this->actor($request);
        AuditLog::record($actor, $company->id, 'company.purge_started', $company, ['reason' => $request->validated('reason')]);
        RunCompanyPurge::dispatch($company->id, $company->company_name, $actor->id, $request->validated('reason'));

        return redirect()->route('platform.companies.index')
            ->with('success', "Purging {$company->company_name}. Platform Owners get an email when it is done.");
    }

    /**
     * On a verified request (04 §5): the new owner is named by email and must be an active
     * member of this company. Other users are never listed here.
     */
    public function transferOwnership(Request $request, Company $company, OwnershipService $ownership): RedirectResponse
    {
        $email = mb_strtolower(trim((string) $request->validate(['email' => ['required', 'email']])['email']));
        $newOwnerId = User::query()->where('company_id', $company->id)->where('email', $email)->value('id');

        if ($newOwnerId === null) {
            return back()->withErrors(['email' => 'No user of this company has that email.']);
        }

        try {
            $ownership->transfer($this->actor($request), $company, (int) $newOwnerId);
        } catch (AuthorizationException $e) {
            return back()->withErrors(['email' => $e->getMessage() ?: 'Ownership could not be transferred.']);
        }

        return back()->with('success', 'Ownership transferred.');
    }

    private function actor(Request $request): PlatformUser
    {
        /** @var PlatformUser */
        return $request->user('platform');
    }
}
