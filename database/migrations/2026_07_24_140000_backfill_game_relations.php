<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $child = DB::table('auc_games')->where('app_id', 'h1000042')->first();
        if ($child === null) {
            return;
        }

        $mother = DB::table('auc_games')->where('game', 'wuguan')->where('id', '!=', $child->id)->first();
        if ($mother === null) {
            $motherId = DB::table('auc_games')->insertGetId([
                'name' => '小小武馆', 'old_name' => '小小武馆', 'game' => 'wuguan',
                'old_id' => 'hw_f1200016', 'company' => 'bingniao', 'business' => 'hw',
                'status' => true, 'created_at' => now(), 'updated_at' => now(),
            ]);
        } else {
            $motherId = $mother->id;
        }

        DB::table('auc_game_relations')->updateOrInsert(
            ['mother_game_id' => $motherId, 'child_game_id' => $child->id],
            ['status' => true, 'created_at' => now(), 'updated_at' => now()],
        );
    }

    public function down(): void
    {
        // Keep catalog data on rollback; only the relation migration owns its schema.
    }
};
