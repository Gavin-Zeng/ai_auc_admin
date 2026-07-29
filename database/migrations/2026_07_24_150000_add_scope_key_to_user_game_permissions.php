<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('auc_user_game_permissions', function (Blueprint $table): void {
            $table->string('scope_key', 80)->nullable()->after('scope_type');
        });
    }

    public function down(): void
    {
        Schema::table('auc_user_game_permissions', function (Blueprint $table): void {
            $table->dropColumn('scope_key');
        });
    }
};
