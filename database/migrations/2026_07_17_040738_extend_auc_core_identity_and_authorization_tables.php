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
        Schema::table('auc_tenants', function (Blueprint $table): void {
            $table->string('short_name', 50)->nullable()->after('name');
            $table->string('type', 40)->default('publisher')->after('short_name');
            $table->string('registered_region', 16)->nullable()->after('type');
            $table->string('default_language', 16)->default('zh-CN')->after('registered_region');
            $table->string('default_timezone', 64)->default('Asia/Shanghai')->after('default_language');
            $table->string('default_currency', 3)->default('USD')->after('default_timezone');
            $table->timestamp('valid_from')->nullable()->after('status');
            $table->unsignedInteger('version')->default(1)->after('settings');
        });

        Schema::table('auc_users', function (Blueprint $table): void {
            $table->string('status', 24)->default('active')->after('is_platform_admin');
            $table->timestamp('locked_at')->nullable()->after('status');
        });

        Schema::table('auc_tenant_users', function (Blueprint $table): void {
            $table->foreignId('department_id')->nullable()->after('user_id')->constrained('auc_departments')->nullOnDelete();
            $table->foreignId('position_id')->nullable()->after('department_id')->constrained('auc_positions')->nullOnDelete();
            $table->string('member_type', 24)->default('employee')->after('position_id');
            $table->timestamp('valid_from')->nullable()->after('status');
            $table->timestamp('valid_to')->nullable()->after('valid_from');
            $table->index(['tenant_id', 'status', 'valid_to']);
        });

        Schema::table('auc_applications', function (Blueprint $table): void {
            $table->string('code', 40)->nullable()->after('id');
            $table->string('type', 40)->default('other')->after('name');
            $table->string('integration_level', 8)->default('L2')->after('type');
            $table->string('responsible_person')->nullable()->after('icon');
            $table->unique('code');
        });

        Schema::table('auc_permissions', function (Blueprint $table): void {
            $table->foreignId('parent_id')->nullable()->after('application_id')->constrained('auc_permissions')->nullOnDelete();
            $table->string('type', 20)->default('ACTION')->after('name');
            $table->string('route')->nullable()->after('group');
            $table->string('icon')->nullable()->after('route');
            $table->unsignedInteger('sort_order')->default(0)->after('icon');
            $table->boolean('is_visible')->default(false)->after('sort_order');
            $table->string('risk_level', 16)->default('NORMAL')->after('is_visible');
            $table->string('source', 20)->default('PLATFORM')->after('risk_level');
            $table->index(['application_id', 'parent_id', 'sort_order']);
        });

        Schema::table('auc_roles', function (Blueprint $table): void {
            $table->foreignId('application_id')->nullable()->after('tenant_id')->constrained('auc_applications')->nullOnDelete();
            $table->string('type', 24)->default('COMPANY')->after('name');
            $table->text('description')->nullable()->after('type');
            $table->timestamp('valid_from')->nullable()->after('status');
            $table->timestamp('valid_to')->nullable()->after('valid_from');
        });

        Schema::table('auc_user_roles', function (Blueprint $table): void {
            $table->foreignId('tenant_user_id')->nullable()->after('tenant_id')->constrained('auc_tenant_users')->cascadeOnDelete();
            $table->timestamp('valid_from')->nullable()->after('role_id');
            $table->timestamp('valid_to')->nullable()->after('valid_from');
            $table->index(['tenant_user_id', 'valid_to']);
        });

        Schema::table('auc_tenant_applications', function (Blueprint $table): void {
            $table->foreignId('feature_version_id')->nullable()->after('application_id')->constrained('auc_application_feature_versions')->nullOnDelete();
            $table->string('management_mode', 24)->default('COMPANY_MANAGED')->after('required_permissions');
            $table->unsignedInteger('user_limit')->nullable()->after('management_mode');
            $table->timestamp('valid_from')->nullable()->after('user_limit');
            $table->timestamp('valid_to')->nullable()->after('valid_from');
            $table->index(['tenant_id', 'status', 'valid_to']);
        });

        Schema::table('auc_audit_logs', function (Blueprint $table): void {
            $table->string('result', 16)->default('SUCCESS')->after('action');
            $table->string('risk_level', 16)->default('NORMAL')->after('result');
            $table->string('trace_id', 64)->nullable()->after('ip_address');
            $table->text('reason')->nullable()->after('trace_id');
            $table->json('before_data')->nullable()->after('reason');
            $table->json('after_data')->nullable()->after('before_data');
            $table->index('trace_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('auc_audit_logs', function (Blueprint $table): void {
            $table->dropIndex(['trace_id']);
            $table->dropColumn(['result', 'risk_level', 'trace_id', 'reason', 'before_data', 'after_data']);
        });

        Schema::table('auc_tenant_applications', function (Blueprint $table): void {
            $table->dropIndex(['tenant_id', 'status', 'valid_to']);
            $table->dropConstrainedForeignId('feature_version_id');
            $table->dropColumn(['management_mode', 'user_limit', 'valid_from', 'valid_to']);
        });

        Schema::table('auc_user_roles', function (Blueprint $table): void {
            $table->dropIndex(['tenant_user_id', 'valid_to']);
            $table->dropConstrainedForeignId('tenant_user_id');
            $table->dropColumn(['valid_from', 'valid_to']);
        });

        Schema::table('auc_roles', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('application_id');
            $table->dropColumn(['type', 'description', 'valid_from', 'valid_to']);
        });

        Schema::table('auc_permissions', function (Blueprint $table): void {
            $table->dropIndex(['application_id', 'parent_id', 'sort_order']);
            $table->dropConstrainedForeignId('parent_id');
            $table->dropColumn(['type', 'route', 'icon', 'sort_order', 'is_visible', 'risk_level', 'source']);
        });

        Schema::table('auc_applications', function (Blueprint $table): void {
            $table->dropUnique(['code']);
            $table->dropColumn(['code', 'type', 'integration_level', 'responsible_person']);
        });

        Schema::table('auc_tenant_users', function (Blueprint $table): void {
            $table->dropIndex(['tenant_id', 'status', 'valid_to']);
            $table->dropConstrainedForeignId('department_id');
            $table->dropConstrainedForeignId('position_id');
            $table->dropColumn(['member_type', 'valid_from', 'valid_to']);
        });

        Schema::table('auc_users', function (Blueprint $table): void {
            $table->dropColumn(['status', 'locked_at']);
        });

        Schema::table('auc_tenants', function (Blueprint $table): void {
            $table->dropColumn(['short_name', 'type', 'registered_region', 'default_language', 'default_timezone', 'default_currency', 'valid_from', 'version']);
        });
    }
};
