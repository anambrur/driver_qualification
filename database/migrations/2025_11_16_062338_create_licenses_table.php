<?php

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
        Schema::create('licenses', function (Blueprint $table) {
            $table->id();
            // RESTRICT: base column of the generated current_driver_id.
            $table->foreignId('driver_id')->constrained()->restrictOnDelete();
            $table->string('first_name'); // name as printed on the licence
            $table->string('last_name');
            $table->date('issued');
            $table->date('expires');
            $table->char('country_code', 2);
            $table->char('state_code', 2);
            $table->string('class');
            $table->string('license_number');
            $table->boolean('is_h_placarded_hazmat')->default(false);
            $table->boolean('is_n_tank_vehicle')->default(false);
            $table->boolean('is_p_passengers')->default(false);
            $table->boolean('is_t_double_trailer')->default(false);
            $table->boolean('is_s_school_bus')->default(false);
            $table->boolean('is_x_placarded_hazmat')->default(false);
            $table->boolean('is_current')->default(false);
            $table->unsignedBigInteger('current_driver_id')->nullable()->storedAs('CASE WHEN is_current THEN driver_id END')->unique();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('licenses');
    }
};
