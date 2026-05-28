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
        Schema::create('auc_menus', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('auc_tenants')->cascadeOnDelete();
            $table->foreignId('menu_group_id')->nullable()->constrained('auc_menu_groups')->nullOnDelete();
            $table->foreignId('application_id')->nullable()->constrained('auc_applications')->nullOnDelete();
            $table->foreignId('parent_id')->nullable()->constrained('auc_menus')->cascadeOnDelete();
            $table->string('code');
            $table->string('title');
            $table->string('href')->nullable();
            $table->string('icon')->nullable();
            $table->json('required_permissions')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_visible')->default(true);
            $table->string('status')->default('active');
            $table->timestamps();

            $table->unique(['tenant_id', 'code']);
            $table->index(['tenant_id', 'status']);
            $table->index(['tenant_id', 'parent_id', 'sort_order']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('auc_menus');
    }
};
