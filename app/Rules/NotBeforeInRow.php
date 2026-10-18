<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\DataAwareRule;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Support\Arr;

/**
 * A row's end date is not before the same row's start date (`experience_to_date.2` against
 * `experience_from_date.2`). An empty start date is not compared.
 */
final class NotBeforeInRow implements DataAwareRule, ValidationRule
{
    /** @var array<string, mixed> */
    private array $data = [];

    public function __construct(private readonly string $startField, private readonly string $startLabel) {}

    public function setData(array $data): static
    {
        $this->data = $data;

        return $this;
    }

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $index = substr($attribute, strrpos($attribute, '.') + 1);
        $start = Arr::get($this->data, $this->startField.'.'.$index);

        if (! is_string($start) || ! is_string($value) || ($from = strtotime($start)) === false || ($to = strtotime($value)) === false) {
            return;
        }

        if ($to < $from) {
            $fail("The :attribute must be on or after the {$this->startLabel}.");
        }
    }
}
