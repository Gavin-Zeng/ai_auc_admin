<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('auc_user_game_permissions')->orderBy('id')->get()->each(function (object $permission): void {
            $game = $permission->game_id === null ? null : DB::table('auc_games')->find($permission->game_id);
            $scopeKey = match ($permission->scope_type) {
                'ALL' => '*',
                'MOTHER' => $game?->old_id,
                'CHILD' => $game?->app_id,
                default => null,
            };

            if (filled($scopeKey)) {
                DB::table('auc_user_game_permissions')->where('id', $permission->id)->update(['scope_key' => $scopeKey]);
            }
        });
    }

    public function down(): void
    {
        DB::table('auc_user_game_permissions')->update(['scope_key' => null]);
    }
};
