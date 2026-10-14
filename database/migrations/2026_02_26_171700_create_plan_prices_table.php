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
        Schema::create('plan_prices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('plan_id')->constrained()->restrictOnDelete();
            $table->enum('interval', ['month', 'year']);
            $table->decimal('amount', 10, 2);
            $table->char('currency', 3)->default('USD');
            $table->string('stripe_price_id')->nullable()->unique();
            $table->timestamp('archived_at')->nullable();
            $table->unsignedTinyInteger('current_flag')->nullable()->storedAs('CASE WHEN archived_at IS NULL THEN 1 END');
            $table->timestamps();

            $table->unique(['plan_id', 'interval', 'currency', 'current_flag']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('plan_prices');
    }
};
