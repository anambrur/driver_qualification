<?php

namespace App\Services\Platform;

use App\Mail\DemoRequestReceivedMail;
use App\Models\DemoRequest;
use Illuminate\Support\Facades\Mail;

/**
 * Homepage "Schedule a demo" submissions: stored for the platform inbox and mailed to support.
 */
class DemoRequestService
{
    /**
     * @param  array<string, mixed>  $attributes
     */
    public function submit(array $attributes): DemoRequest
    {
        $demoRequest = DemoRequest::query()->create($attributes);

        $to = settings('support_email') ?: settings('email');
        if ($to) {
            Mail::to($to)->queue(new DemoRequestReceivedMail($demoRequest));
        }

        return $demoRequest;
    }
}
