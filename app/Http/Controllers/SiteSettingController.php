<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\Plan;
use App\Models\PlatformUser;
use App\Models\SiteSetting;
use App\Support\Ui\Color;
use App\Support\Ui\Theme;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

class SiteSettingController extends Controller
{
    /**
     * Display the site settings form.
     */
    public function index()
    {
        // Using the model's static method to ensure a record always exists
        // and is cached properly.
        $setting = SiteSetting::getSettings();
        $plans = Plan::query()->ordered()->get(['id', 'name']);

        return view('platform.settings.site.index', compact('setting', 'plans'));
    }

    /**
     * Update the site settings in storage.
     */
    public function update(Request $request)
    {
        $setting = SiteSetting::getSettings();

        $validated = $request->validate([
            'site_name' => 'required|string|max:255',
            // Raster images only: no svg/html/php on the public disk. mimes checks the file content.
            'logo' => 'nullable|image|mimes:jpeg,png,jpg,gif,webp|max:2048',
            'favicon' => 'nullable|file|mimes:ico,png,jpg,jpeg,gif,webp|max:1024',
            // Appearance: one brand colour drives the whole UI (partials/theme-head)
            'logo_dark' => 'nullable|image|mimes:jpeg,png,jpg,gif,webp|max:2048',
            'primary_color' => ['nullable', 'string', 'regex:'.Color::HEX.'D'],
            'accent_color' => ['nullable', 'string', 'regex:'.Color::HEX.'D'],
            'default_color_mode' => ['sometimes', 'required', Rule::in(Theme::MODES)],
            'email' => 'nullable|email|max:255',
            'phone' => 'nullable|string|max:50',
            'address' => 'nullable|string|max:500',
            'facebook_url' => 'nullable|url|max:255',
            'twitter_url' => 'nullable|url|max:255',
            'linkedin_url' => 'nullable|url|max:255',
            'meta_title' => 'nullable|string|max:255',
            'meta_description' => 'nullable|string|max:1000',
            'meta_keywords' => 'nullable|string|max:500',
            'google_analytics_id' => ['nullable', 'string', 'max:50', 'regex:/^G-[A-Z0-9]+$/D'],
            // Billing and support (07 §5, 04 §4.2)
            'trial_plan_id' => 'nullable|integer|exists:plans,id',
            'trial_days' => 'required|integer|min:1|max:90',
            'grace_days' => 'required|integer|min:0|max:60',
            'support_session_max_minutes' => 'required|integer|min:15|max:1440',
        ]);

        // Handle File Uploads
        if ($request->hasFile('logo')) {
            // Delete old logo if exists
            if ($setting->logo) {
                Storage::disk('public')->delete($setting->logo);
            }
            $validated['logo'] = $request->file('logo')->store('settings', 'public');
        }

        if ($request->hasFile('logo_dark')) {
            if ($setting->logo_dark) {
                Storage::disk('public')->delete($setting->logo_dark);
            }
            $validated['logo_dark'] = $request->file('logo_dark')->store('settings', 'public');
        }

        foreach (['primary_color', 'accent_color'] as $color) {
            if (array_key_exists($color, $validated) && $validated[$color] !== null) {
                $validated[$color] = strtolower($validated[$color]);
            }
        }

        if ($request->hasFile('favicon')) {
            // Delete old favicon if exists
            if ($setting->favicon) {
                Storage::disk('public')->delete($setting->favicon);
            }
            $validated['favicon'] = $request->file('favicon')->store('settings', 'public');
        }

        $setting->fill($validated);
        $changes = $setting->getDirty();
        $setting->save();

        if ($changes !== []) {
            /** @var PlatformUser $actor */
            $actor = $request->user('platform');
            AuditLog::record($actor, null, 'site_settings.updated', $setting, $changes);
        }

        toastr()->success('Site settings updated successfully.');

        return redirect()->back();
    }
}
