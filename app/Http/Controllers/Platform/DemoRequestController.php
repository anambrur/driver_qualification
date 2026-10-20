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
 * The inbox: homepage "Schedule a demo" requests and Contact page messages.
 */
class DemoRequestController extends Controller
{
    public function __construct(private SiteContentService $content) {}

    public function index(Request $request): View
    {
        $status = $request->query('status') === 'handled' ? 'handled' : 'open';
        $source = array_key_exists((string) $request->query('source'), DemoRequest::SOURCES) ? (string) $request->query('source') : null;

        $demoRequests = DemoRequest::query()
            ->when($status === 'handled', fn ($q) => $q->handled(), fn ($q) => $q->open())
            ->when($source, fn ($q) => $q->where('source', $source))
            ->with('handler:id,name')
            ->latest('id')
            ->paginate(25)
            ->withQueryString();

        return view('platform.content.demo-requests.index', [
            'demoRequests' => $demoRequests,
            'status' => $status,
            'source' => $source,
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

        return back()->with('success', 'Message deleted.');
    }

    private function actor(Request $request): PlatformUser
    {
        /** @var PlatformUser */
        return $request->user('platform');
    }
}
