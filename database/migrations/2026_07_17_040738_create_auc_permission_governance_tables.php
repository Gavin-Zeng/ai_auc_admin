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
        Schema::create('auc_departments', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained('auc_tenants')->cascadeOnDelete();
            $table->foreignId('parent_id')->nullable()->constrained('auc_departments')->restrictOnDelete();
            $table->string('code', 40);
            $table->string('name', 100);
            $table->unsignedInteger('sort_order')->default(0);
            $table->string('status', 20)->default('active');
            $table->timestamps();
            $table->unique(['tenant_id', 'code']);
        });

        Schema::create('auc_positions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained('auc_tenants')->cascadeOnDelete();
            $table->string('code', 40);
            $table->string('name', 100);
            $table->string('status', 20)->default('active');
            $table->timestamps();
            $table->unique(['tenant_id', 'code']);
        });

        Schema::create('auc_application_feature_versions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('application_id')->constrained('auc_applications')->cascadeOnDelete();
            $table->string('code', 40);
            $table->string('name', 100);
            $table->string('status', 20)->default('active');
            $table->timestamps();
            $table->unique(['application_id', 'code']);
        });

        Schema::create('auc_feature_version_permissions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('feature_version_id')->constrained('auc_application_feature_versions')->cascadeOnDelete();
            $table->foreignId('permission_id')->constrained('auc_permissions')->cascadeOnDelete();
            $table->timestamps();
            $table->unique(['feature_version_id', 'permission_id'], 'auc_feature_version_permissions_unique');
        });

        Schema::create('auc_tenant_permissions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained('auc_tenants')->cascadeOnDelete();
            $table->foreignId('application_id')->constrained('auc_applications')->cascadeOnDelete();
            $table->foreignId('permission_id')->constrained('auc_permissions')->cascadeOnDelete();
            $table->string('source', 24)->default('FEATURE_VERSION');
            $table->string('status', 20)->default('active');
            $table->timestamps();
            $table->unique(['tenant_id', 'permission_id']);
            $table->index(['tenant_id', 'application_id', 'status'], 'auc_tenant_permissions_lookup');
        });

        Schema::create('auc_permission_releases', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('application_id')->constrained('auc_applications')->cascadeOnDelete();
            $table->unsignedInteger('version_no');
            $table->text('change_summary')->nullable();
            $table->foreignId('published_by')->nullable()->constrained('auc_users')->nullOnDelete();
            $table->timestamp('published_at');
            $table->timestamps();
            $table->unique(['application_id', 'version_no']);
        });

        Schema::create('auc_role_templates', function (Blueprint $table): void {
            $table->id();
            $table->string('code', 60)->unique();
            $table->string('name', 120);
            $table->string('company_type', 40)->nullable();
            $table->unsignedInteger('version')->default(1);
            $table->boolean('company_editable')->default(true);
            $table->string('status', 20)->default('active');
            $table->timestamps();
        });

        Schema::create('auc_role_template_permissions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('role_template_id')->constrained('auc_role_templates')->cascadeOnDelete();
            $table->foreignId('permission_id')->constrained('auc_permissions')->cascadeOnDelete();
            $table->timestamps();
            $table->unique(['role_template_id', 'permission_id'], 'auc_role_template_permissions_unique');
        });

        Schema::create('auc_platform_roles', function (Blueprint $table): void {
            $table->id();
            $table->string('code', 60)->unique();
            $table->string('name', 120);
            $table->json('permissions');
            $table->string('status', 20)->default('active');
            $table->timestamps();
        });

        Schema::create('auc_platform_user_roles', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained('auc_users')->cascadeOnDelete();
            $table->foreignId('platform_role_id')->constrained('auc_platform_roles')->cascadeOnDelete();
            $table->timestamps();
            $table->unique(['user_id', 'platform_role_id']);
        });

        Schema::create('auc_login_logs', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained('auc_users')->nullOnDelete();
            $table->foreignId('tenant_id')->nullable()->constrained('auc_tenants')->nullOnDelete();
            $table->string('result', 20);
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->string('trace_id', 64)->nullable();
            $table->timestamp('occurred_at');
            $table->timestamps();
            $table->index(['tenant_id', 'occurred_at']);
        });

        Schema::create('auc_permission_events', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->nullable()->constrained('auc_tenants')->cascadeOnDelete();
            $table->string('event_type', 80);
            $table->json('payload');
            $table->timestamp('published_at')->nullable();
            $table->timestamps();
            $table->index(['published_at', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('auc_permission_events');
        Schema::dropIfExists('auc_login_logs');
        Schema::dropIfExists('auc_platform_user_roles');
        Schema::dropIfExists('auc_platform_roles');
        Schema::dropIfExists('auc_role_template_permissions');
        Schema::dropIfExists('auc_role_templates');
        Schema::dropIfExists('auc_permission_releases');
        Schema::dropIfExists('auc_tenant_permissions');
        Schema::dropIfExists('auc_feature_version_permissions');
        Schema::dropIfExists('auc_application_feature_versions');
        Schema::dropIfExists('auc_positions');
        Schema::dropIfExists('auc_departments');
    }
};
