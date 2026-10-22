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
        // 08 §8: the subject says what a document is about, the folder where it is filed (F18).
        // Subjects are in a CHECK, so their FKs RESTRICT. trip_id, trip_event_id,
        // fuel_purchase_id, expense_id and inspection_id get their FKs in Phases 2–4 from
        // MySQL-only migrations (altering this table on SQLite drops the CHECK).
        Schema::create('documents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->restrictOnDelete();
            $table->unsignedBigInteger('folder_id')->nullable();
            $table->unsignedBigInteger('file_id');
            $table->unsignedBigInteger('original_file_id')->nullable();
            $table->string('title');
            $table->foreignId('document_type_id')->nullable()->constrained()->restrictOnDelete();
            $table->date('issued_on')->nullable();
            $table->date('expires_on')->nullable();
            $table->unsignedBigInteger('driver_id')->nullable();
            $table->unsignedBigInteger('vehicle_id')->nullable();
            $table->unsignedBigInteger('trailer_id')->nullable();
            $table->unsignedBigInteger('trip_id')->nullable();
            $table->unsignedBigInteger('trip_event_id')->nullable();
            $table->unsignedBigInteger('fuel_purchase_id')->nullable();
            $table->unsignedBigInteger('expense_id')->nullable();
            $table->unsignedBigInteger('service_log_id')->nullable();
            $table->unsignedBigInteger('inspection_id')->nullable();
            $table->foreignId('uploaded_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['company_id', 'id']);
            $table->index(['company_id', 'folder_id']);
            $table->foreign(['company_id', 'folder_id'])->references(['company_id', 'id'])->on('folders')->restrictOnDelete();
            $table->foreign(['company_id', 'file_id'])->references(['company_id', 'id'])->on('stored_files')->restrictOnDelete();
            $table->foreign(['company_id', 'original_file_id'])->references(['company_id', 'id'])->on('stored_files')->restrictOnDelete();
            $table->foreign(['company_id', 'driver_id'])->references(['company_id', 'id'])->on('drivers')->restrictOnDelete();
            $table->foreign(['company_id', 'vehicle_id'])->references(['company_id', 'id'])->on('vehicles')->restrictOnDelete();
            $table->foreign(['company_id', 'trailer_id'])->references(['company_id', 'id'])->on('trailers')->restrictOnDelete();
            $table->foreign(['company_id', 'service_log_id'])->references(['company_id', 'id'])->on('service_logs')->restrictOnDelete();
        });

        CheckConstraints::add('documents', [
            'documents_one_subject_check' => '(driver_id IS NOT NULL) + (vehicle_id IS NOT NULL) + (trailer_id IS NOT NULL)'
                .' + (trip_id IS NOT NULL) + (trip_event_id IS NOT NULL) + (fuel_purchase_id IS NOT NULL)'
                .' + (expense_id IS NOT NULL) + (service_log_id IS NOT NULL) + (inspection_id IS NOT NULL) <= 1',
        ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('documents');
    }
};
