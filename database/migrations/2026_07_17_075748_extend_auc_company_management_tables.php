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
            $table->string('contact_name', 50)->nullable()->after('domain');
            $table->string('contact_phone', 32)->nullable()->after('contact_name');
            $table->string('contact_email', 160)->nullable()->after('contact_phone');
            $table->text('remark')->nullable()->after('contact_email');
            $table->index(['type', 'registered_region'], 'auc_tenants_type_region_index');
            $table->index('created_at');
        });

        Schema::create('auc_tenant_game_scopes', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_game_id')->constrained('auc_tenant_games')->cascadeOnDelete();
            $table->string('scope_type', 24);
            $table->string('scope_mode', 16);
            $table->timestamps();
            $table->unique(['tenant_game_id', 'scope_type'], 'auc_tenant_game_scopes_unique');
        });

        Schema::create('auc_tenant_game_scope_items', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_game_scope_id')->constrained('auc_tenant_game_scopes')->cascadeOnDelete();
            $table->string('resource_type', 24);
            $table->unsignedBigInteger('resource_id');
            $table->string('resource_code', 80);
            $table->timestamps();
            $table->unique(['tenant_game_scope_id', 'resource_type', 'resource_id'], 'auc_tenant_game_scope_items_unique');
        });

        Schema::create('auc_tenant_game_applications', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_game_id')->constrained('auc_tenant_games')->cascadeOnDelete();
            $table->foreignId('tenant_application_id')->constrained('auc_tenant_applications')->cascadeOnDelete();
            $table->timestamps();
            $table->unique(['tenant_game_id', 'tenant_application_id'], 'auc_tenant_game_applications_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('auc_tenant_game_applications');
        Schema::dropIfExists('auc_tenant_game_scope_items');
        Schema::dropIfExists('auc_tenant_game_scopes');

        Schema::table('auc_tenants', function (Blueprint $table): void {
            $table->dropIndex('auc_tenants_type_region_index');
            $table->dropIndex(['created_at']);
            $table->dropColumn(['contact_name', 'contact_phone', 'contact_email', 'remark']);
        });
    }
};
