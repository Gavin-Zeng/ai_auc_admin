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
        Schema::create('auc_tenant_users', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('auc_tenants')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('auc_users')->cascadeOnDelete();
            $table->string('status')->default('active');
            $table->boolean('is_owner')->default(false);
            $table->unsignedInteger('permission_version')->default(1);
            $table->timestamps();

            $table->unique(['tenant_id', 'user_id']);
            $table->index(['tenant_id', 'status']);
            $table->index(['tenant_id', 'user_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('auc_tenant_users');
    }
};
