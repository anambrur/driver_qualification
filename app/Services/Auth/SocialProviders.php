<?php

namespace App\Services\Auth;

use App\Models\SiteSetting;

/**
 * Social login is a platform capability (05 §2): a provider is offered only when its OAuth keys
 * are in the env and Platform > Settings > Site switches it on.
 */
class SocialProviders
{
    public const PROVIDERS = ['google' => 'Google', 'facebook' => 'Facebook'];

    public function isConfigured(string $provider): bool
    {
        return isset(self::PROVIDERS[$provider])
            && filled(config("services.{$provider}.client_id"))
            && filled(config("services.{$provider}.client_secret"));
    }

    public function isEnabled(string $provider): bool
    {
        return $this->isConfigured($provider) && (bool) SiteSetting::getSettings()->{"{$provider}_login_enabled"};
    }

    /**
     * @return array<string, string> provider => label
     */
    public function enabled(): array
    {
        return array_filter(self::PROVIDERS, fn (string $provider) => $this->isEnabled($provider), ARRAY_FILTER_USE_KEY);
    }

    public static function label(string $provider): string
    {
        return self::PROVIDERS[$provider] ?? ucfirst($provider);
    }
}
