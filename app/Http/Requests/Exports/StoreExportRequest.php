<?php

namespace App\Http\Requests\Exports;

use App\Models\Driver;
use App\Models\Export;
use App\Services\Exports\ExportService;
use App\Tenancy\TenantRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreExportRequest extends FormRequest
{
    /**
     * What the ZIP holds must be visible to the user (ExportService::canAccess); a driver ZIP
     * also needs the driver itself.
     */
    public function authorize(): bool
    {
        $user = $this->user();
        $type = (string) $this->input('type');
        $driver = $this->driver();

        if ($user === null || ! in_array($type, Export::ZIP_TYPES, true)) {
            return true; // validation answers
        }

        return app(ExportService::class)->canAccess($user, $type) && ($driver === null || $user->can('view', $driver));
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
