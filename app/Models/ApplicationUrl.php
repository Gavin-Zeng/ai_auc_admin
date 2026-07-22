<?php

namespace App\Models;

use Database\Factories\ApplicationUrlFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ApplicationUrl extends Model
{
    /** @use HasFactory<ApplicationUrlFactory> */
    use HasFactory;

    protected $table = 'auc_application_urls';

    protected $fillable = ['application_id', 'base_url', 'redirect_uri', 'is_default', 'status'];

    public function application(): BelongsTo
    {
        return $this->belongsTo(Application::class);
    }

    protected function casts(): array
    {
        return ['is_default' => 'boolean', 'status' => 'boolean'];
    }
}
