<?php

namespace App\Services\Entitlements;

use App\Features\Feature;
use App\Features\FeatureState;
use App\Models\Company;

/**
 * What one company is entitled to (05 §4): a state per feature and a value per limit.
 */
final class Entitlements
{
    /**
     * @param  array<string, FeatureState>  $states
     * @param  array<string, int|null>  $limits  null = unlimited
     */
    public function __construct(
        private readonly array $states,
        private readonly array $limits,
    ) {}

    public static function for(Company $company): self
    {
        return app(EntitlementResolver::class)->for($company);
    }

    public function state(Feature|string $feature): FeatureState
    {
        return $this->states[$feature instanceof Feature ? $feature->value : $feature] ?? FeatureState::Off;
    }

    /**
     * On or read-only: its pages, menus and exports are reachable.
     */
    public function allows(Feature|string $feature): bool
    {
        return $this->state($feature)->allowsRead();
    }

    /**
     * The effective limit: null is unlimited, 0 when the plan does not include it.
     */
    public function limit(Feature $feature): ?int
    {
        return $this->state($feature) === FeatureState::Off ? 0 : ($this->limits[$feature->value] ?? null);
    }

    /**
     * @return array{states: array<string, string>, limits: array<string, int|null>}
     */
    public function toArray(): array
    {
        return [
            'states' => array_map(fn (FeatureState $state) => $state->value, $this->states),
            'limits' => $this->limits,
        ];
    }

    /**
     * @param  array{states: array<string, string>, limits: array<string, int|null>}  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(array_map(fn (string $state) => FeatureState::from($state), $data['states']), $data['limits']);
    }
}
