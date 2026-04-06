<?php

use App\Models\SiteSetting;

if (!function_exists('settings')) {
    /**
     * Get a site setting by key.
     * Since it's a single-row model, we retrieve the global setting instance
     * and return the property that matches the requested key.
     *
     * @param string $key
     * @param mixed $default
     * @return mixed
     */
    function settings($key, $default = null)
    {
        try {
            $settings = SiteSetting::getSettings();
            
            if ($settings && isset($settings->{$key})) {
                return $settings->{$key};
            }
        } catch (\Exception $e) {
            // Failsafe in case DB table doesn't exist yet
        }

        return $default;
    }
}
