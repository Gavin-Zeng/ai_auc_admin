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
        Schema::table('auc_tenants', function (Blueprint $table) {
            $table->dropIndex('auc_tenants_type_region_index');
            $table->dropIndex('auc_tenants_status_expires_at_index');
            $table->dropColumn([
                'type',
                'registered_region',
                'default_language',
                'valid_from',
                'expires_at',
                'contact_phone',
                'contact_email',
            ]);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('auc_tenants', function (Blueprint $table) {
            $table->string('type', 32)->default('publisher')->after('short_name');
            $table->string('registered_region', 16)->nullable()->after('type');
            $table->string('default_language', 16)->default('zh-CN')->after('registered_region');
            $table->timestamp('valid_from')->nullable()->after('status');
            $table->timestamp('expires_at')->nullable()->after('valid_from');
            $table->string('contact_phone', 32)->nullable()->after('contact_name');
            $table->string('contact_email', 160)->nullable()->after('contact_phone');
            $table->index(['type', 'registered_region'], 'auc_tenants_type_region_index');
            $table->index(['status', 'expires_at'], 'auc_tenants_status_expires_at_index');
        });
    }
};
