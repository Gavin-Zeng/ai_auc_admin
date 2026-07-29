<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (DB::connection()->getDriverName() === 'sqlite') {
            $this->replaceSqlitePermissionTable();
            Schema::dropIfExists('auc_game_relations');

            return;
        }

        if (collect(Schema::getForeignKeys('auc_user_game_permissions'))->contains(fn (array $key): bool => $key['name'] === 'auc_user_game_permissions_game_id_foreign')) {
            Schema::table('auc_user_game_permissions', function (Blueprint $table): void {
                $table->dropForeign(['game_id']);
            });
        }

        if (! collect(Schema::getIndexes('auc_user_game_permissions'))->contains(fn (array $index): bool => $index['name'] === 'auc_user_game_permissions_user_id_index')) {
            Schema::table('auc_user_game_permissions', function (Blueprint $table): void {
                $table->index('user_id');
            });
        }

        Schema::table('auc_user_game_permissions', function (Blueprint $table): void {
            $table->dropUnique('auc_user_game_permissions_unique');
            $table->dropColumn('game_id');
        });

        Schema::table('auc_user_game_permissions', function (Blueprint $table): void {
            $table->string('scope_key', 80)->default('*')->nullable(false)->change();
            $table->unique(['user_id', 'scope_type', 'scope_key'], 'auc_user_game_permissions_scope_unique');
        });

        Schema::dropIfExists('auc_game_relations');
    }

    private function replaceSqlitePermissionTable(): void
    {
        Schema::create('auc_user_game_permissions_new', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained('auc_tenants')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('auc_users')->cascadeOnDelete();
            $table->string('scope_type', 16);
            $table->string('scope_key', 80)->default('*');
            $table->boolean('status')->default(true)->index();
            $table->timestamps();
            $table->unique(['user_id', 'scope_type', 'scope_key'], 'auc_user_game_permissions_scope_unique');
            $table->index(['tenant_id', 'user_id']);
        });

        DB::table('auc_user_game_permissions')->orderBy('id')->get()->each(function (object $permission): void {
            DB::table('auc_user_game_permissions_new')->insert([
                'id' => $permission->id,
                'tenant_id' => $permission->tenant_id,
                'user_id' => $permission->user_id,
                'scope_type' => $permission->scope_type,
                'scope_key' => $permission->scope_key ?: '*',
                'status' => $permission->status,
                'created_at' => $permission->created_at,
                'updated_at' => $permission->updated_at,
            ]);
        });

        Schema::drop('auc_user_game_permissions');
        Schema::rename('auc_user_game_permissions_new', 'auc_user_game_permissions');
    }

    public function down(): void
    {
        Schema::create('auc_game_relations', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('mother_game_id')->constrained('auc_games')->cascadeOnDelete();
            $table->foreignId('child_game_id')->constrained('auc_games')->cascadeOnDelete();
            $table->boolean('status')->default(true)->index();
            $table->timestamps();
            $table->unique(['mother_game_id', 'child_game_id']);
        });

        Schema::table('auc_user_game_permissions', function (Blueprint $table): void {
            $table->dropUnique('auc_user_game_permissions_scope_unique');
            $table->foreignId('game_id')->nullable()->constrained('auc_games')->cascadeOnDelete();
        });
    }
};
