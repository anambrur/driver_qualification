<?php

use App\Support\CheckConstraints;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('company_settings', function (Blueprint $table) {
            $table->foreignId('company_id')->primary()->constrained()->cascadeOnDelete();
            $table->string('trip_number_prefix', 10)->nullable();
            $table->enum('distance_unit', ['mi', 'km'])->default('mi');
            $table->enum('weight_unit', ['lb', 'kg'])->default('lb');
            $table->char('currency', 3)->default('USD');
            $table->unsignedTinyInteger('week_starts_on')->default(0); // 0 = Sunday
            $table->unsignedSmallInteger('overtime_daily_after_minutes')->default(480);
            $table->unsignedSmallInteger('double_overtime_daily_after_minutes')->nullable()->default(720);
            $table->unsignedSmallInteger('overtime_weekly_after_minutes')->nullable();
            $table->decimal('overtime_multiplier', 4, 2)->default(1.50);
            $table->decimal('double_overtime_multiplier', 4, 2)->default(2.00);
            $table->timestamps();
        });

        CheckConstraints::add('company_settings', [
            'company_settings_double_overtime_after_check' => 'double_overtime_daily_after_minutes IS NULL OR double_overtime_daily_after_minutes > overtime_daily_after_minutes',
            'company_settings_multipliers_check' => 'overtime_multiplier >= 1 AND double_overtime_multiplier >= overtime_multiplier',
        ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('company_settings');
    }
};
