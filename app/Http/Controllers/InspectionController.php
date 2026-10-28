<?php

namespace App\Http\Controllers;

use App\Http\Requests\Operations\CompleteInspectionRequest;
use App\Http\Requests\Operations\InspectionAttachmentRequest;
use App\Http\Requests\Operations\SaveInspectionResultsRequest;
use App\Models\Document;
use App\Models\Inspection;
use App\Models\Trip;
use App\Services\Documents\DocumentService;
use App\Services\Operations\InspectionService;
use App\Support\Ui\Toast;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Pre-trip inspections of a trip (req 5.1–5.3), recorded by staff for the driver until the app
 * (Phase 3): start, answer on one screen (saved as you go), complete or abandon.
 */
class InspectionController extends Controller
{
    public function __construct(private InspectionService $inspections) {}

    public function store(Request $request, Trip $trip): RedirectResponse
    {
        Gate::authorize('update', $trip);
        $inspection = $this->inspections->start($trip, $request->user());

        return redirect()->route('inspections.show', $inspection);
    }

    public function show(Request $request, Inspection $inspection): View
    {
        Gate::authorize('view', $inspection);

        $inspection->load([
            'trip:id,trip_number,status,driver_id,vehicle_id,trailer_id', 'driver:id,first_name,last_name', 'vehicle:id,unit_no', 'trailer:id,unit_no',
            'template.items', 'results', 'performedBy:id,name', 'documents' => fn ($q) => $q->select(['id', 'inspection_id', 'title', 'file_id'])->with('file:id,original_name'),
        ]);
        $inspection->setAttribute('results_count', $inspection->results->count())->setAttribute('items_count', $inspection->template->items->count());

        return view('inspections.show', [
            'inspection' => $inspection,
            'results' => $inspection->results->keyBy('template_item_id'),
            'attachments' => $inspection->documents->groupBy('title'),
            'canAbandon' => $inspection->isOpen() && $request->user()->can('inspections.manage'),
            'canRecord' => $inspection->isOpen() && $inspection->trip->status === 'assigned' && $request->user()->can('inspections.manage'),
            'crewChanged' => $inspection->isOpen() && ! $inspection->matchesCrewOf($inspection->trip),
        ]);
    }

    public function updateResults(SaveInspectionResultsRequest $request, Inspection $inspection): JsonResponse|RedirectResponse
    {
        Gate::authorize('update', $inspection);
        $inspection = $this->inspections->saveResults($inspection, $request->answers(), $request->user());

        if ($request->expectsJson()) {
            return response()->json(['answered' => $inspection->results_count, 'total' => $inspection->items_count, 'progress' => $inspection->progress()]);
        }

        Toast::success('Answers saved.');

        return redirect()->route('inspections.show', $inspection);
    }

    public function complete(CompleteInspectionRequest $request, Inspection $inspection): RedirectResponse
    {
        Gate::authorize('update', $inspection);
        $inspection = $this->inspections->complete($inspection, $request->odometer(), $request->validated('notes'), $request->user());

        $inspection->result === 'no_defects'
            ? Toast::success('Inspection completed: no defects.')
            : Toast::warning('Inspection completed: defects found. The trip can start after a new inspection with no defects.');

        return redirect()->to(route('trips.show', $inspection->trip_id).'#inspection');
    }

    public function abandon(Request $request, Inspection $inspection): RedirectResponse
    {
        Gate::authorize('update', $inspection);
        $inspection = $this->inspections->abandon($inspection, $request->user());

        Toast::success('Inspection abandoned.');

        return redirect()->to(route('trips.show', $inspection->trip_id).'#inspection');
    }

    public function attach(InspectionAttachmentRequest $request, Inspection $inspection): RedirectResponse
    {
        Gate::authorize('update', $inspection);
        $documents = $this->inspections->attach($inspection, (int) $request->validated('template_item_id'), $request->file('files'), $request->user());

        Toast::success($documents->count() === 1 ? 'File added to the trip folder.' : "{$documents->count()} files added to the trip folder.");

        return redirect()->to(route('inspections.show', $inspection).'#item-'.$request->validated('template_item_id'));
    }

    public function attachment(Request $request, Inspection $inspection, Document $document, DocumentService $documents): StreamedResponse
    {
        Gate::authorize('view', $inspection);
        abort_unless((int) $document->inspection_id === $inspection->id, 404);
        // The trip folder's own rule (trips.view), as its Documents tab applies it
        Gate::authorize('view', $document);

        return $documents->download($document, $request->user(), inline: true);
    }
}
