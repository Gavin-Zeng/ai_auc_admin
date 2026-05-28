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
        Schema::create('auc_applications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('auc_tenants')->cascadeOnDelete();
            $table->string('code');
            $table->string('name');
            $table->string('client_id')->unique();
            $table->string('client_secret');
            $table->string('base_url');
            $table->string('redirect_uri');
            $table->string('icon')->nullable();
            $table->json('required_permissions')->nullable();
            $table->string('status')->default('active');
            $table->timestamps();

            $table->unique(['tenant_id', 'code']);
            $table->index(['tenant_id', 'status']);
            $table->index(['client_id', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('auc_applications');
    }
};
