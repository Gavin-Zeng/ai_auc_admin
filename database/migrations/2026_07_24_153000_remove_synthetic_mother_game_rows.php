<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('auc_games')->whereNull('app_id')->whereNotNull('old_id')->delete();
    }

    public function down(): void
    {
        // Synthetic mother rows are no longer part of the game catalog model.
    }
};
