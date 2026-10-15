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
        // The audited platform Asset Transfer (PO-08, D11, 04 §7): copy to the target, retire at
        // the source. Every FK is RESTRICT: the asset columns are in the CHECK.
        Schema::create('asset_transfers', function (Blueprint $table) {
            $table->id();
            $table->enum('asset_type', ['vehicle', 'trailer']);
            $table->foreignId('source_company_id')->constrained('companies')->restrictOnDelete();
            $table->foreignId('target_company_id')->constrained('companies')->restrictOnDelete();
            $table->unsignedBigInteger('source_vehicle_id')->nullable();
            $table->unsignedBigInteger('target_vehicle_id')->nullable();
            $table->unsignedBigInteger('source_trailer_id')->nullable();
            $table->unsignedBigInteger('target_trailer_id')->nullable();
            $table->text('reason');
            $table->enum('status', ['completed', 'failed']);
            $table->json('copied_counts')->nullable();
            $table->unsignedBigInteger('bytes_copied')->default(0);
            $table->foreignId('performed_by_platform_user_id')->constrained('platform_users')->restrictOnDelete();
            $table->timestamp('performed_at');
            $table->timestamps();

            $table->foreign(['source_company_id', 'source_vehicle_id'], 'asset_transfers_source_vehicle_foreign')
                ->references(['company_id', 'id'])->on('vehicles')->restrictOnDelete();
            $table->foreign(['target_company_id', 'target_vehicle_id'], 'asset_transfers_target_vehicle_foreign')
                ->references(['company_id', 'id'])->on('vehicles')->restrictOnDelete();
            $table->foreign(['source_company_id', 'source_trailer_id'], 'asset_transfers_source_trailer_foreign')
                ->references(['company_id', 'id'])->on('trailers')->restrictOnDelete();
            $table->foreign(['target_company_id', 'target_trailer_id'], 'asset_transfers_target_trailer_foreign')
                ->references(['company_id', 'id'])->on('trailers')->restrictOnDelete();

            $table->index(['source_company_id', 'performed_at']);
            $table->index(['target_company_id', 'performed_at']);
        });

        CheckConstraints::add('asset_transfers', [
            'asset_transfers_companies_check' => 'source_company_id <> target_company_id',
            'asset_transfers_asset_check' => "(asset_type = 'vehicle' AND source_vehicle_id IS NOT NULL AND source_trailer_id IS NULL AND target_trailer_id IS NULL)"
                ." OR (asset_type = 'trailer' AND source_trailer_id IS NOT NULL AND source_vehicle_id IS NULL AND target_vehicle_id IS NULL)",
        ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('asset_transfers');
    }
};
