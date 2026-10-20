<?php

namespace App\Http\Controllers;

use App\Http\Requests\ContactMessageRequest;
use App\Services\Platform\DemoRequestService;
use Illuminate\Http\RedirectResponse;

/**
 * The Contact page form; messages land in the platform inbox with the demo requests.
 */
class ContactMessageController extends Controller
{
    public function store(ContactMessageRequest $request, DemoRequestService $inbox): RedirectResponse
    {
        $inbox->submit($request->messageAttributes(), 'contact');

        return redirect()->to(route('pages.show', 'contact').'#contact-form')->with('contact_sent', true);
    }
}
