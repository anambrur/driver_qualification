<?php

namespace App\Http\Controllers\Platform;

use App\Http\Controllers\Controller;
use App\Http\Requests\Platform\InspectionTemplateItemRequest;
use App\Http\Requests\Platform\InspectionTemplateRequest;
use App\Models\InspectionTemplate;
use App\Models\InspectionTemplateItem;
use App\Models\PlatformUser;
use App\Services\Platform\InspectionTemplateService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

/**
 * Pre-trip checklist versions (08 §9, LKP-01): drafts, items, publish, new version, activate,
 * deactivate.
 */
class InspectionTemplateController extends Controller
{
    public function __construct(private InspectionTemplateService $templates) {}

    public function index(): View
    {
        $templates = InspectionTemplate::query()->withCount('items')
            ->addSelect(['is_used' => DB::table('inspections')->selectRaw('1')->whereColumn('template_id', 'inspection_templates.id')->limit(1)])
            ->orderBy('name')->orderByDesc('version')->get();

        return view('platform.inspection-templates.index', compact('templates'));
    }

    public function store(InspectionTemplateRequest $request): RedirectResponse
    {
        $template = $this->templates->create($request->validated('name'));

        return redirect()->route('platform.lookups.inspection-templates.edit', $template)->with('success', 'Draft created. Add its items, then publish it.');
    }

    public function edit(InspectionTemplate $template): View
    {
        return view('platform.inspection-templates.edit', [
            'template' => $template,
            'items' => $template->items()->get(),
            'editable' => ! $template->isUsed(),
        ]);
    }

    public function storeItem(InspectionTemplateItemRequest $request, InspectionTemplate $template): RedirectResponse
    {
        $this->templates->addItem($template, $request->item());

        return back()->with('success', 'Item added.');
    }

    public function updateItem(InspectionTemplateItemRequest $request, InspectionTemplate $template, InspectionTemplateItem $item): RedirectResponse
    {
        $this->templates->updateItem($template, $item, $request->item());

        return back()->with('success', 'Item saved.');
    }

    public function destroyItem(InspectionTemplate $template, InspectionTemplateItem $item): RedirectResponse
    {
        $this->templates->deleteItem($template, $item);

        return back()->with('success', 'Item deleted.');
    }

    public function publish(Request $request, InspectionTemplate $template): RedirectResponse
    {
        $this->templates->publish($this->actor($request), $template);

        return back()->with('success', "{$template->label()} published. New inspections use it.");
    }

    public function newVersion(InspectionTemplate $template): RedirectResponse
    {
        $draft = $this->templates->newVersion($template);

        return redirect()->route('platform.lookups.inspection-templates.edit', $draft)->with('success', "Draft {$draft->label()} ready to edit.");
    }

    public function activate(Request $request, InspectionTemplate $template): RedirectResponse
    {
        $this->templates->activate($this->actor($request), $template);

        return back()->with('success', "{$template->label()} is active. New inspections use it.");
    }

    public function deactivate(Request $request, InspectionTemplate $template): RedirectResponse
    {
        $this->templates->deactivate($this->actor($request), $template);

        return back()->with('success', "{$template->label()} deactivated.");
    }

    public function destroy(Request $request, InspectionTemplate $template): RedirectResponse
    {
        $this->templates->deleteDraft($this->actor($request), $template);

        return redirect()->route('platform.lookups.inspection-templates.index')->with('success', 'Draft deleted.');
    }

    private function actor(Request $request): PlatformUser
    {
        /** @var PlatformUser */
        return $request->user('platform');
    }
}
