<?php

namespace App\Http\Controllers\Platform;

use App\Http\Controllers\Controller;
use App\Http\Requests\ReplyEmailRequest;
use App\Models\AuditLog;
use App\Models\PlatformUser;
use App\Models\SiteSetting;
use App\Services\Mail\ReplyToVerificationService;
use App\Support\Ui\Toast;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * The platform's email for replies: platform mail, notifications, and company mail whose company has none.
 */
class ReplyEmailController extends Controller
{
    public function __construct(private ReplyToVerificationService $verification) {}

    public function store(ReplyEmailRequest $request): RedirectResponse
    {
        $setting = SiteSetting::getSettings();
        $this->verification->request($setting, $request->validated('reply_to_email'));

        AuditLog::record($this->actor($request), null, 'site_settings.reply_email.requested', $setting, [
            'pending_reply_to_email' => $setting->pending_reply_to_email,
        ]);

        Toast::success($setting->pending_reply_to_email
            ? "We sent a link to {$setting->pending_reply_to_email}. Open it to start getting replies there."
            : 'This email already gets your replies.');

        return back();
    }

    public function destroy(Request $request): RedirectResponse
    {
        $setting = SiteSetting::getSettings();
        $this->verification->remove($setting);

        AuditLog::record($this->actor($request), null, 'site_settings.reply_email.removed', $setting);
        Toast::success('Email for replies removed.');

        return back();
    }

    private function actor(Request $request): PlatformUser
    {
        /** @var PlatformUser */
        return $request->user('platform');
    }
}
