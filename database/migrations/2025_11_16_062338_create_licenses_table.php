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
            $table->foreignId('driver_id')->constrained('drivers')->onDelete('cascade');
            $table->string('first_name');
            $table->string('last_name');
            $table->date('issued');
            $table->date('expires');
            $table->string('country');
            $table->string('state');
            $table->string('class');
            $table->string('license_number');
            $table->string('repeat_license_number');
            $table->boolean('is_h_placarded_hazmat')->default(false);
            $table->boolean('is_n_tank_vehicle')->default(false);
            $table->boolean('is_p_passengers')->default(false);
            $table->boolean('is_t_double_trailer')->default(false);
            $table->boolean('is_s_school_bus')->default(false);
            $table->boolean('is_x_placarded_hazmat')->default(false);
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
