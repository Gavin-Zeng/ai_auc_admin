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
        Schema::create('auc_regions', function (Blueprint $table): void {
            $table->id();
            $table->string('code', 16)->unique();
            $table->string('name', 100);
            $table->string('status', 20)->default('active');
            $table->timestamps();
        });

        Schema::create('auc_languages', function (Blueprint $table): void {
            $table->id();
            $table->string('code', 16)->unique();
            $table->string('name', 100);
            $table->string('status', 20)->default('active');
            $table->timestamps();
        });

        Schema::create('auc_channels', function (Blueprint $table): void {
            $table->id();
            $table->string('code', 32)->unique();
            $table->string('name', 100);
            $table->string('status', 20)->default('active');
            $table->timestamps();
        });

        Schema::create('auc_games', function (Blueprint $table): void {
            $table->id();
            $table->string('code', 40)->unique();
            $table->string('name', 120);
            $table->string('type', 40)->default('mobile');
            $table->string('status', 24)->default('PREPARING');
            $table->string('default_language', 16)->default('zh-CN');
            $table->string('icon')->nullable();
            $table->timestamps();
        });

        Schema::create('auc_game_versions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('game_id')->constrained('auc_games')->cascadeOnDelete();
            $table->string('code', 40);
            $table->string('name', 120);
            $table->string('market_type', 24)->default('OVERSEAS');
            $table->string('status', 24)->default('PREPARING');
            $table->timestamps();
            $table->unique(['game_id', 'code']);
        });

        Schema::create('auc_release_units', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('game_id')->constrained('auc_games')->cascadeOnDelete();
            $table->foreignId('game_version_id')->constrained('auc_game_versions')->cascadeOnDelete();
            $table->foreignId('publisher_tenant_id')->nullable()->constrained('auc_tenants')->nullOnDelete();
            $table->foreignId('region_id')->constrained('auc_regions')->restrictOnDelete();
            $table->foreignId('channel_id')->constrained('auc_channels')->restrictOnDelete();
            $table->string('code', 80)->unique();
            $table->string('name', 160);
            $table->string('status', 20)->default('active');
            $table->timestamps();
            $table->index(['game_id', 'game_version_id', 'status'], 'auc_release_units_lookup');
        });

        Schema::create('auc_game_version_languages', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('game_version_id')->constrained('auc_game_versions')->cascadeOnDelete();
            $table->foreignId('language_id')->constrained('auc_languages')->cascadeOnDelete();
            $table->timestamps();
            $table->unique(['game_version_id', 'language_id'], 'auc_game_version_languages_unique');
        });

        Schema::create('auc_tenant_games', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained('auc_tenants')->cascadeOnDelete();
            $table->foreignId('game_id')->constrained('auc_games')->cascadeOnDelete();
            $table->foreignId('game_version_id')->nullable()->constrained('auc_game_versions')->cascadeOnDelete();
            $table->string('duty', 40);
            $table->timestamp('valid_from')->nullable();
            $table->timestamp('valid_to')->nullable();
            $table->string('status', 20)->default('active');
            $table->timestamps();
            $table->unique(['tenant_id', 'game_id', 'game_version_id'], 'auc_tenant_games_unique');
        });

        Schema::create('auc_game_members', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('game_id')->constrained('auc_games')->cascadeOnDelete();
            $table->foreignId('tenant_user_id')->constrained('auc_tenant_users')->cascadeOnDelete();
            $table->string('duty', 40);
            $table->string('status', 20)->default('active');
            $table->timestamps();
            $table->unique(['game_id', 'tenant_user_id']);
        });

        Schema::create('auc_role_business_scopes', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('role_id')->constrained('auc_roles')->cascadeOnDelete();
            $table->foreignId('application_id')->nullable()->constrained('auc_applications')->cascadeOnDelete();
            $table->string('scope_type', 24);
            $table->string('scope_mode', 16)->default('NONE');
            $table->timestamps();
            $table->unique(['role_id', 'application_id', 'scope_type'], 'auc_role_business_scopes_unique');
        });

        Schema::create('auc_role_scope_items', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('role_business_scope_id')->constrained('auc_role_business_scopes')->cascadeOnDelete();
            $table->string('resource_type', 40);
            $table->unsignedBigInteger('resource_id')->nullable();
            $table->string('resource_code', 80);
            $table->timestamps();
            $table->unique(['role_business_scope_id', 'resource_type', 'resource_code'], 'auc_role_scope_items_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('auc_role_scope_items');
        Schema::dropIfExists('auc_role_business_scopes');
        Schema::dropIfExists('auc_game_members');
        Schema::dropIfExists('auc_tenant_games');
        Schema::dropIfExists('auc_game_version_languages');
        Schema::dropIfExists('auc_release_units');
        Schema::dropIfExists('auc_game_versions');
        Schema::dropIfExists('auc_games');
        Schema::dropIfExists('auc_channels');
        Schema::dropIfExists('auc_languages');
        Schema::dropIfExists('auc_regions');
    }
};
