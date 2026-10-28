<?php

namespace App\Http\Requests\Operations;

use App\Models\InspectionResult;
use Closure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Answers keyed by template item id: one at a time from the autosave, all of them from the form.
 */
class SaveInspectionResultsRequest extends FormRequest
{
    /**
     * The whole form also posts the empty note of every unanswered item; those are no answer.
     */
    protected function prepareForValidation(): void
    {
        $results = $this->input('results');

        if (is_array($results)) {
            $this->merge(['results' => array_filter($results, fn ($answer) => ! is_array($answer) || filled($answer['result'] ?? null) || filled($answer['note'] ?? null))]);
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'results' => ['required', 'array', 'max:500', $this->itemKeys(...)],
            'results.*' => ['array:result,note'],
            'results.*.result' => ['required', Rule::in(array_keys(InspectionResult::RESULTS))],
            'results.*.note' => ['nullable', 'string', 'max:1000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return ['results.*.result.required' => 'Choose pass, fail or n/a before adding a note.'];
    }

    /**
     * @return array<int, array{result: string, note?: string|null}>
     */
    public function answers(): array
    {
        /** @var array<int, array{result: string, note?: string|null}> */
        return $this->validated('results');
    }

    private function itemKeys(string $attribute, mixed $value, Closure $fail): void
    {
        if (is_array($value) && collect(array_keys($value))->contains(fn ($key) => ! ctype_digit((string) $key))) {
            $fail('Answer only the items of this checklist.');
        }
    }
}
