<?php

namespace App\Http\Controllers;

use App\Models\SiteSetting;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class TawkToSettingController extends Controller
{
    /**
     * Display the Tawk.to settings form.
     */
    public function index()
    {
        $setting = SiteSetting::getSettings();

        return view('admin.settings.tawk.index', compact('setting'));
    }

    /**
     * Update the Tawk.to settings in storage.
     */
    public function update(Request $request)
    {
        $setting = SiteSetting::getSettings();

        $validated = $request->validate([
            'tawk_enabled' => 'nullable|boolean',
            'tawk_widget_code' => ['nullable', 'string', 'max:20000', 'required_if:tawk_enabled,1'],
        ]);

        $widgetCode = trim((string) ($validated['tawk_widget_code'] ?? ''));
        $updates = [
            'tawk_enabled' => $request->boolean('tawk_enabled'),
        ];

        if ($widgetCode !== '') {
            [$propertyId, $widgetId] = $this->extractTawkIdentifiers($widgetCode);

            $updates['tawk_widget_code'] = $widgetCode;
            $updates['tawk_property_id'] = $propertyId;
            $updates['tawk_widget_id'] = $widgetId;
        }

        $setting->update($updates);

        toastr()->success('Tawk.to chat settings updated successfully.');

        return redirect()->back();
    }

    /**
     * Extract validated identifiers from an official Tawk.to widget snippet.
     *
     * The submitted snippet is stored for editing but is never rendered.
     *
     * @return array{0: string, 1: string}
     */
    private function extractTawkIdentifiers(string $widgetCode): array
    {
        $withoutComments = preg_replace('/<!--.*?-->/s', '', $widgetCode) ?? $widgetCode;

        if (
            preg_match_all('/<script\b/i', $withoutComments) !== 1
            || preg_match_all('/<\/script>/i', $withoutComments) !== 1
            || preg_match('/^\s*<script\b[^>]*>.*<\/script>\s*$/is', $withoutComments) !== 1
            || ! str_contains($widgetCode, 'Tawk_API')
            || ! str_contains($widgetCode, 'Tawk_LoadStart')
        ) {
            throw ValidationException::withMessages([
                'tawk_widget_code' => 'Paste the complete official Tawk.to widget code.',
            ]);
        }

        $pattern = '~https://embed\.tawk\.to/([a-zA-Z0-9_-]{1,100})/([a-zA-Z0-9_-]{1,100})(?=[\'"\s;]|$)~';
        $matchCount = preg_match_all($pattern, $widgetCode, $matches);

        if (
            $matchCount !== 1
            || substr_count(strtolower($widgetCode), 'embed.tawk.to') !== 1
        ) {
            throw ValidationException::withMessages([
                'tawk_widget_code' => 'The widget code must contain exactly one official Tawk.to embed URL.',
            ]);
        }

        return [$matches[1][0], $matches[2][0]];
    }
}
