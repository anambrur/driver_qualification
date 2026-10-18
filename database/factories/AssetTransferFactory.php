<?php

namespace Database\Factories;

use App\Models\AssetTransfer;
use App\Models\Company;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * A completed transfer record for list screens, with a source vehicle or trailer made for it.
 * Real transfers go through AssetTransferService.
 *
 * @extends Factory<AssetTransfer>
 */
class AssetTransferFactory extends Factory
{
    protected $model = AssetTransfer::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'asset_type' => 'vehicle',
            'source_company_id' => CompanyFactory::new(),
            'target_company_id' => CompanyFactory::new(),
            'reason' => 'Fleet sold to the target company.',
            'status' => 'completed',
            'copied_counts' => ['documents' => 2, 'service_logs' => 1],
            'performed_by_platform_user_id' => PlatformUserFactory::new(),
            'performed_at' => now(),
        ];
    }

    public function configure(): static
    {
        return $this->afterMaking(function (AssetTransfer $transfer) {
            $column = "source_{$transfer->asset_type}_id";
            if ($transfer->{$column} === null) {
                $factory = $transfer->asset_type === 'trailer' ? TrailerFactory::new() : VehicleFactory::new();
                $transfer->{$column} = $factory->forCompany(Company::query()->findOrFail($transfer->source_company_id))->create()->id;
            }
        });
    }
}
