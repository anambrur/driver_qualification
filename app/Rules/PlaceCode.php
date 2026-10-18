<?php

namespace App\Rules;

use App\Services\Driver\DriverCrudService;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * The posted country or state (id, code or name) must resolve to the two-letter code the
 * char(2) columns store, so an unknown value is a field error instead of a failed insert.
 */
final class PlaceCode implements ValidationRule
{
    private function __construct(private readonly string $kind) {}

    public static function country(): self
    {
        return new self('country');
    }

    public static function state(): self
    {
        return new self('state');
    }

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_scalar($value)) {
            $fail("Select a valid {$this->kind}.");

            return;
        }

        $crud = app(DriverCrudService::class);
        $code = $this->kind === 'country' ? $crud->countryCode($value) : $crud->stateCode($value);

        if ($code === null || strlen($code) !== 2) {
            $fail("Select a valid {$this->kind}.");
        }
    }
}
