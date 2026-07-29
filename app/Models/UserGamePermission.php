<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserGamePermission extends Model
{
    protected $table = 'auc_user_game_permissions';

    protected $fillable = ['tenant_id', 'user_id', 'scope_type', 'scope_key', 'status'];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    protected function casts(): array
    {
        return ['status' => 'boolean'];
    }
}
