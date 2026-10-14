<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
| Spatie 8 tables with teams (06 §4.1). team_id is the company id, or 0 for platform roles,
| and is never null. roles.team_id has no FK to companies because 0 is not a company.
| model_has_roles points at roles through (team_id, role_id), so a pivot can never use a
| role from another team.
*/
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $tableNames = config('permission.table_names');
        $columnNames = config('permission.column_names');
        $team = $columnNames['team_foreign_key'] ?? null;

        throw_if(empty($tableNames), new Exception('Error: config/permission.php not loaded. Run [php artisan config:clear] and try again.'));
        throw_if(! config('permission.teams') || $team !== 'team_id', new Exception('Error: config/permission.php must have teams = true and team_foreign_key = team_id.'));

        Schema::create($tableNames['permissions'], static function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('guard_name');
            $table->string('feature_key', 64)->nullable();
            $table->boolean('is_write')->default(false);
            $table->boolean('self_scoped')->default(false);
            $table->string('description')->nullable();
            $table->timestamps();

            $table->unique(['name', 'guard_name']);
            $table->foreign('feature_key')->references('key')->on('features')->restrictOnDelete();
        });

        Schema::create($tableNames['roles'], static function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('team_id');
            $table->string('name');
            $table->string('guard_name');
            $table->string('system_key')->nullable();
            $table->boolean('is_system')->default(false);
            $table->string('description')->nullable();
            $table->timestamps();

            $table->unique(['team_id', 'name', 'guard_name']);
            $table->unique(['team_id', 'system_key']);
            $table->unique(['team_id', 'id']);
        });

        Schema::create($tableNames['model_has_permissions'], static function (Blueprint $table) use ($tableNames) {
            $table->unsignedBigInteger('team_id');
            $table->foreignId('permission_id')->constrained($tableNames['permissions'])->cascadeOnDelete();
            $table->string('model_type');
            $table->unsignedBigInteger('model_id');

            $table->primary(['team_id', 'permission_id', 'model_id', 'model_type'], 'model_has_permissions_permission_model_type_primary');
            $table->index(['model_type', 'model_id', 'team_id']);
        });

        Schema::create($tableNames['model_has_roles'], static function (Blueprint $table) use ($tableNames) {
            $table->unsignedBigInteger('team_id');
            $table->unsignedBigInteger('role_id');
            $table->string('model_type');
            $table->unsignedBigInteger('model_id');

            $table->primary(['team_id', 'role_id', 'model_id', 'model_type'], 'model_has_roles_role_model_type_primary');
            $table->index(['model_type', 'model_id', 'team_id']);
            $table->foreign(['team_id', 'role_id'])->references(['team_id', 'id'])->on($tableNames['roles'])->cascadeOnDelete();
        });

        Schema::create($tableNames['role_has_permissions'], static function (Blueprint $table) use ($tableNames) {
            $table->foreignId('permission_id')->constrained($tableNames['permissions'])->cascadeOnDelete();
            $table->foreignId('role_id')->constrained($tableNames['roles'])->cascadeOnDelete();

            $table->primary(['permission_id', 'role_id'], 'role_has_permissions_permission_id_role_id_primary');
            $table->index('role_id');
        });

        app('cache')
            ->store(config('permission.cache.store') != 'default' ? config('permission.cache.store') : null)
            ->forget(config('permission.cache.key'));
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $tableNames = config('permission.table_names');

        Schema::dropIfExists($tableNames['role_has_permissions']);
        Schema::dropIfExists($tableNames['model_has_roles']);
        Schema::dropIfExists($tableNames['model_has_permissions']);
        Schema::dropIfExists($tableNames['roles']);
        Schema::dropIfExists($tableNames['permissions']);
    }
};
