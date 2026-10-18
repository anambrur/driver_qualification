<?php

namespace App\DataTables\Platform;

use App\Models\Company;
use App\Services\Platform\PlatformCompanyService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Yajra\DataTables\Facades\DataTables;

/**
 * Server-side company list (platform/companies): metadata only (04 §5). Search matches the
 * company name, its contact email and the owner's email.
 */
final class CompanyTable
{
    public function __construct(private readonly Request $request) {}

    public function json(): JsonResponse
    {
        $query = Company::query()
            ->select(['companies.id', 'company_name', 'email', 'status', 'owner_user_id', 'companies.created_at'])
            ->with('owner:id,name,email');

        $status = $this->request->input('status');
        if (in_array($status, PlatformCompanyService::STATUSES, true)) {
            $query->where('status', $status);
        }

        $term = trim((string) data_get($this->request->input('search'), 'value', ''));
        if ($term !== '') {
            $like = '%'.addcslashes($term, '%_\\').'%';
            $query->where(fn ($q) => $q
                ->where('company_name', 'like', $like)
                ->orWhere('email', 'like', $like)
                ->orWhereHas('owner', fn ($q) => $q->where('email', 'like', $like)));
        }

        return DataTables::eloquent($query)
            ->addColumn('company', fn (Company $company) => view('platform.partials.cell', [
                'title' => $company->company_name, 'href' => route('platform.companies.show', $company), 'sub' => $company->email,
            ])->render())
            ->addColumn('owner', fn (Company $company) => view('platform.partials.cell', [
                'title' => $company->owner?->name, 'sub' => $company->owner?->email,
            ])->render())
            ->addColumn('status', fn (Company $company) => view('partials.ui.status-badge', ['status' => $company->status])->render())
            ->addColumn('created_at_formatted', fn (Company $company) => $company->created_at?->format('M j, Y'))
            ->orderColumn('company', 'company_name $1')
            ->orderColumn('created_at_formatted', 'companies.created_at $1')
            ->filter(fn () => null) // searched above
            ->rawColumns(['company', 'owner', 'status'])
            ->make(true);
    }
}
