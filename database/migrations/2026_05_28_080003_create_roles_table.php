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
        Schema::create('auc_roles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('auc_tenants')->cascadeOnDelete();
            $table->string('code');
            $table->string('name');
            $table->string('status')->default('active');
            $table->boolean('is_system')->default(false);
            $table->timestamps();

            $table->unique(['tenant_id', 'code']);
            $table->index(['tenant_id', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('auc_roles');
    }
};
