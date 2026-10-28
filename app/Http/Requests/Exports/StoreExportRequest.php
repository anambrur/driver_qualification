<?php

namespace App\Http\Requests\Exports;

use App\Models\Driver;
use App\Services\Exports\ExportService;
use App\Tenancy\TenantRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreExportRequest extends FormRequest
{
    /** A trip ZIP is requested on its trip (trips.export). */
    private const TYPES = ['zip_driver', 'zip_company'];

    /**
     * What the ZIP holds must be visible to the user (ExportService::canAccess); a driver ZIP
     * also needs the driver itself. The company ZIP adds the trips when the user sees them.
     */
    public function authorize(): bool
    {
        $user = $this->user();
        $type = (string) $this->input('type');
        $driver = $this->driver();

        if ($user === null || ! in_array($type, self::TYPES, true)) {
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
            'type' => ['required', Rule::in(self::TYPES)],
            'driver_id' => ['required_if:type,zip_driver', 'prohibited_unless:type,zip_driver', 'integer', TenantRule::exists('drivers')->whereNull('deleted_at')],
        ];
    }

    /**
     * @return array<string, int>
     */
    public function parameters(): array
    {
        if ($this->validated('type') === 'zip_driver') {
            return ['driver_id' => (int) $this->validated('driver_id')];
        }

        // The company ZIP holds the trips only for a requester who may see them
        return $this->user()?->can('trips.view') ? ['trips' => 1] : [];
    }

    private function driver(): ?Driver
    {
        $id = $this->input('type') === 'zip_driver' ? $this->input('driver_id') : null;

        return is_numeric($id) ? Driver::query()->find((int) $id) : null;
    }
}
