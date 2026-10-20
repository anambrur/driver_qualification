<?php

namespace App\Http\Controllers\Platform;

use App\Http\Controllers\Controller;
use App\Models\DemoRequest;
use App\Models\PlatformUser;
use App\Services\Platform\SiteContentService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Homepage "Schedule a demo" requests.
 */
class DemoRequestController extends Controller
{
    public function __construct(private SiteContentService $content) {}

    public function index(Request $request): View
    {
        $status = $request->query('status') === 'handled' ? 'handled' : 'open';

        $demoRequests = DemoRequest::query()
            ->when($status === 'handled', fn ($q) => $q->handled(), fn ($q) => $q->open())
            ->with('handler:id,name')
            ->latest('id')
            ->paginate(25)
            ->withQueryString();

        return view('platform.content.demo-requests.index', [
            'demoRequests' => $demoRequests,
            'status' => $status,
            'openCount' => DemoRequest::query()->open()->count(),
        ]);
    }

    public function update(Request $request, DemoRequest $demoRequest): RedirectResponse
    {
        $this->content->markDemoRequestHandled($this->actor($request), $demoRequest);

        return back()->with('success', 'Marked as handled.');
    }

    public function destroy(Request $request, DemoRequest $demoRequest): RedirectResponse
    {
        $this->content->deleteDemoRequest($this->actor($request), $demoRequest);

        return back()->with('success', 'Demo request deleted.');
    }

    private function actor(Request $request): PlatformUser
    {
        /** @var PlatformUser */
        return $request->user('platform');
    }
}
