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
        Schema::create('auc_sso_auth_codes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('auc_tenants')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('auc_users')->cascadeOnDelete();
            $table->foreignId('application_id')->constrained('auc_applications')->cascadeOnDelete();
            $table->string('code')->unique();
            $table->string('redirect_uri');
            $table->string('state')->nullable();
            $table->timestamp('expires_at');
            $table->timestamp('used_at')->nullable();
            $table->timestamps();

            $table->index(['application_id', 'code']);
            $table->index(['tenant_id', 'user_id']);
            $table->index(['expires_at', 'used_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('auc_sso_auth_codes');
    }
};
