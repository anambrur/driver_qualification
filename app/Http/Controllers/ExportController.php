<?php

namespace App\Http\Controllers;

use App\DataTables\Exports\ExportTable;
use App\Http\Requests\Exports\StoreExportRequest;
use App\Models\Export;
use App\Models\Trip;
use App\Services\Exports\ExportService;
use App\Support\Ui\Toast;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * ZIP exports of a driver, a trip or the whole company (req 2.5): the Exports page, requests and
 * downloads. Building happens in BuildExport.
 */
class ExportController extends Controller
{
    public function __construct(private ExportService $exports) {}

    public function index(): View
    {
        return view('exports.index');
    }

    public function data(Request $request): JsonResponse
    {
        return (new ExportTable($request))->json();
    }

    public function store(StoreExportRequest $request): RedirectResponse
    {
        return $this->queued($this->exports->request($request->user(), $request->validated('type'), $request->parameters()));
    }

    /**
     * The trip's ZIP (req 2.5): what its page shows, documents only.
     */
    public function trip(Request $request, Trip $trip): RedirectResponse
    {
        Gate::authorize('view', $trip);
        abort_unless($this->exports->canRequest($request->user(), 'zip_trip'), 403);

        return $this->queued($this->exports->request($request->user(), 'zip_trip', ['trip_id' => $trip->id]));
    }

    public function download(Request $request, Export $export): StreamedResponse
    {
        return $this->exports->download($export, $request->user());
    }

    private function queued(Export $export): RedirectResponse
    {
        $export->wasRecentlyCreated
            ? Toast::success("Preparing your {$export->typeLabel()}. We'll notify you when it's ready.")
            : Toast::info("This {$export->typeLabel()} is already being prepared.");

        return redirect()->route('exports.index');
    }
}
