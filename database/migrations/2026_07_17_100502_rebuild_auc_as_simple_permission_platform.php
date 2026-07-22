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
        Schema::disableForeignKeyConstraints();

        foreach ([
            'auc_sso_auth_codes', 'auc_role_scope_items', 'auc_role_business_scopes',
            'auc_role_permissions', 'auc_user_roles', 'auc_tenant_permissions',
            'auc_tenant_game_applications', 'auc_tenant_game_scope_items',
            'auc_tenant_game_scopes', 'auc_tenant_games', 'auc_game_members',
            'auc_release_units', 'auc_game_version_languages', 'auc_game_versions',
            'auc_games', 'auc_channels', 'auc_languages', 'auc_regions',
            'auc_role_template_permissions', 'auc_role_templates',
            'auc_platform_user_roles', 'auc_platform_roles', 'auc_feature_version_permissions',
            'auc_application_feature_versions', 'auc_permission_releases',
            'auc_permission_events', 'auc_permissions', 'auc_menus', 'auc_menu_groups',
            'auc_tenant_applications', 'auc_roles', 'auc_tenant_users',
            'auc_departments', 'auc_positions', 'auc_applications', 'auc_tenants',
            'auc_login_logs', 'auc_audit_logs', 'auc_users', 'auc_password_reset_tokens',
        ] as $table) {
            Schema::dropIfExists($table);
        }

        Schema::create('auc_tenants', function (Blueprint $table): void {
            $table->id();
            $table->string('name', 120)->unique();
            $table->boolean('status')->default(true)->index();
            $table->timestamps();
        });

        Schema::create('auc_applications', function (Blueprint $table): void {
            $table->id();
            $table->string('name', 120)->unique();
            $table->string('client_id', 80)->unique();
            $table->string('client_secret');
            $table->boolean('status')->default(true)->index();
            $table->timestamps();
        });

        Schema::create('auc_application_urls', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('application_id')->constrained('auc_applications')->cascadeOnDelete();
            $table->string('base_url', 500);
            $table->string('redirect_uri', 500);
            $table->boolean('is_default')->default(false);
            $table->boolean('status')->default(true);
            $table->timestamps();
            $table->unique(['application_id', 'redirect_uri']);
        });

        Schema::create('auc_tenant_applications', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained('auc_tenants')->cascadeOnDelete();
            $table->foreignId('application_id')->constrained('auc_applications')->cascadeOnDelete();
            $table->timestamps();
            $table->unique(['tenant_id', 'application_id']);
        });

        Schema::create('auc_roles', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained('auc_tenants')->cascadeOnDelete();
            $table->string('name', 120);
            $table->boolean('status')->default(true);
            $table->timestamps();
            $table->unique(['tenant_id', 'name']);
        });

        Schema::create('auc_users', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->nullable()->constrained('auc_tenants')->nullOnDelete();
            $table->foreignId('role_id')->nullable()->constrained('auc_roles')->nullOnDelete();
            $table->string('name', 120);
            $table->string('account', 32)->unique();
            $table->string('password');
            $table->boolean('is_company_admin')->default(false);
            $table->boolean('is_platform_admin')->default(false);
            $table->boolean('status')->default(true)->index();
            $table->rememberToken();
            $table->timestamps();
        });

        Schema::create('auc_menus', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('application_id')->constrained('auc_applications')->cascadeOnDelete();
            $table->foreignId('parent_id')->nullable()->constrained('auc_menus')->cascadeOnDelete();
            $table->string('name', 120);
            $table->string('path', 255)->nullable();
            $table->boolean('is_visible')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('status')->default(true);
            $table->timestamps();
            $table->unique(['application_id', 'path']);
        });

        Schema::create('auc_role_menus', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('role_id')->constrained('auc_roles')->cascadeOnDelete();
            $table->foreignId('menu_id')->constrained('auc_menus')->cascadeOnDelete();
            $table->timestamps();
            $table->unique(['role_id', 'menu_id']);
        });

        Schema::create('auc_sso_auth_codes', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained('auc_tenants')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('auc_users')->cascadeOnDelete();
            $table->foreignId('application_id')->constrained('auc_applications')->cascadeOnDelete();
            $table->string('code')->unique();
            $table->string('redirect_uri', 500);
            $table->string('state')->nullable();
            $table->timestamp('expires_at');
            $table->timestamp('used_at')->nullable();
            $table->timestamps();
        });

        Schema::enableForeignKeyConstraints();
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        throw new RuntimeException('极简架构迁移不支持回滚，请使用 migrate:fresh。');
    }
};
