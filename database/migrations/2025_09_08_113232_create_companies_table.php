<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('companies', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('owner_user_id')->nullable();
            $table->string('company_name');
            $table->string('slug', 300)->unique();
            $table->string('dot_number', 20)->nullable();
            $table->string('mc_number', 20)->nullable();
            $table->text('description')->nullable();

            // Contact. The login identity lives only in users.email (D3b).
            $table->string('email')->nullable();
            $table->string('billing_email')->nullable();
            $table->string('phone', 20)->nullable();
            $table->string('fax', 20)->nullable();
            $table->string('address')->nullable();
            $table->string('city')->nullable();
            $table->char('state_code', 2)->nullable();
            $table->string('zip')->nullable();
            $table->string('timezone')->default('America/Chicago');
            $table->string('logo_path')->nullable();

            $table->enum('status', ['active', 'suspended', 'deactivated'])->default('active');
            $table->timestamp('status_changed_at')->nullable();
            $table->string('status_reason')->nullable();

            $table->string('stripe_customer_id')->nullable()->unique();
            $table->timestamp('trial_used_at')->nullable();
            // The owner email that claimed the trial: unique, so one trial per email (PO-15)
            $table->string('trial_email')->nullable()->unique();
            $table->unsignedInteger('entitlements_version')->default(1);
            $table->unsignedBigInteger('storage_bytes')->default(0);
            $table->timestamps();

            $table->index('status');

            // The owner must be a user of this company: (id, owner_user_id) -> users(company_id, id).
            // MySQL needs users to exist first (add_owner_fk_to_companies). SQLite accepts a forward
            // reference, and a later alter would rebuild the table and drop its enum CHECKs.
            if (DB::getDriverName() === 'sqlite') {
                $table->foreign(['id', 'owner_user_id'])->references(['company_id', 'id'])->on('users')->restrictOnDelete();
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('companies');
    }
};
