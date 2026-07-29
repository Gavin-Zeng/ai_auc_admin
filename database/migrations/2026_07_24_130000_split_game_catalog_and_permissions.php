<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('auc_game_relations', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('mother_game_id')->constrained('auc_games')->cascadeOnDelete();
            $table->foreignId('child_game_id')->constrained('auc_games')->cascadeOnDelete();
            $table->boolean('status')->default(true)->index();
            $table->timestamps();
            $table->unique(['mother_game_id', 'child_game_id']);
        });

        if (Schema::hasColumn('auc_games', 'parent_id')) {
            DB::table('auc_games')->whereNotNull('parent_id')->orderBy('id')->get(['id', 'parent_id'])->each(function (object $game): void {
                DB::table('auc_game_relations')->insertOrIgnore([
                    'mother_game_id' => $game->parent_id,
                    'child_game_id' => $game->id,
                    'status' => true,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            });

            Schema::table('auc_games', function (Blueprint $table): void {
                $table->dropForeign(['parent_id']);
                $table->dropColumn('parent_id');
            });
        }

        Schema::dropIfExists('auc_tenant_games');
    }

    public function down(): void
    {
        Schema::table('auc_games', function (Blueprint $table): void {
            $table->foreignId('parent_id')->nullable()->constrained('auc_games')->nullOnDelete();
        });

        DB::table('auc_game_relations')->orderBy('id')->get(['mother_game_id', 'child_game_id'])->each(function (object $relation): void {
            DB::table('auc_games')->whereKey($relation->child_game_id)->update(['parent_id' => $relation->mother_game_id]);
        });

        Schema::dropIfExists('auc_game_relations');
    }
};
