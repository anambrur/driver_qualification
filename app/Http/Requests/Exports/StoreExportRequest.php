<?php

namespace App\Http\Requests\Exports;

use App\Models\Driver;
use App\Models\Export;
use App\Tenancy\TenantRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreExportRequest extends FormRequest
{
    /**
     * A driver ZIP also needs drivers.view and the driver itself.
     */
    public function authorize(): bool
    {
        $driver = $this->driver();
        $user = $this->user();

        return $driver === null || ($user?->can('drivers.view') === true && $user->can('view', $driver));
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'type' => ['required', Rule::in(Export::ZIP_TYPES)],
            'driver_id' => ['required_if:type,zip_driver', 'prohibited_unless:type,zip_driver', 'integer', TenantRule::exists('drivers')->whereNull('deleted_at')],
        ];
    }

    /**
     * @return array<string, int>
     */
    public function parameters(): array
    {
        return $this->validated('type') === 'zip_driver' ? ['driver_id' => (int) $this->validated('driver_id')] : [];
    }

    private function driver(): ?Driver
    {
        $id = $this->input('type') === 'zip_driver' ? $this->input('driver_id') : null;

        return is_numeric($id) ? Driver::query()->find((int) $id) : null;
    }
}
