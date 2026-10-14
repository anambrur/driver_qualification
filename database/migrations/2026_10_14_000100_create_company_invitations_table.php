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
        Schema::create('company_invitations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->string('email');
            $table->unsignedBigInteger('role_id');
            $table->unsignedBigInteger('driver_id')->nullable(); // driver-login invites
            $table->char('token_hash', 64)->unique();
            $table->foreignId('invited_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('expires_at');
            $table->timestamp('accepted_at')->nullable();
            $table->foreignId('accepted_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('revoked_at')->nullable();
            $table->timestamps();

            // The role must belong to the company's own team (06 §4.1).
            $table->foreign(['company_id', 'role_id'])->references(['team_id', 'id'])->on('roles')->cascadeOnDelete();
            $table->foreign(['company_id', 'driver_id'])->references(['company_id', 'id'])->on('drivers')->restrictOnDelete();
            $table->index(['company_id', 'email']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('company_invitations');
    }
};
