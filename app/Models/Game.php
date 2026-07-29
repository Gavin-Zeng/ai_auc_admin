<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Game extends Model
{
    protected $table = 'auc_games';

    protected $fillable = ['name', 'app_id', 'old_name', 'uptime', 'ios_appid', 'yy_gamename', 'proportion', 'game', 'yyb_id', 'old_id', 'plat', 'company', 'business', 'pkg_name', 'channel_config', 'tag', 'cost_reg', 'os', 'status'];

    protected function casts(): array
    {
        return ['status' => 'boolean', 'proportion' => 'decimal:4', 'cost_reg' => 'decimal:4', 'uptime' => 'datetime'];
    }
}
