<?php

namespace App\Models;

use Database\Factories\MenuFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Menu extends Model
{
    /** @use HasFactory<MenuFactory> */
    use HasFactory;

    protected $table = 'auc_menus';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'tenant_id',
        'menu_group_id',
        'application_id',
        'parent_id',
        'code',
        'title',
        'href',
        'icon',
        'required_permissions',
        'sort_order',
        'is_visible',
        'status',
    ];

    /**
     * @return BelongsTo<Tenant, $this>
     */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    /**
     * @return BelongsTo<MenuGroup, $this>
     */
    public function group(): BelongsTo
    {
        return $this->belongsTo(MenuGroup::class, 'menu_group_id');
    }

    /**
     * @return BelongsTo<Application, $this>
     */
    public function application(): BelongsTo
    {
        return $this->belongsTo(Application::class);
    }

    /**
     * @return BelongsTo<Menu, $this>
     */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(Menu::class, 'parent_id');
    }

    /**
     * @return HasMany<Menu, $this>
     */
    public function children(): HasMany
    {
        return $this->hasMany(Menu::class, 'parent_id');
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'required_permissions' => 'array',
            'sort_order' => 'integer',
            'is_visible' => 'boolean',
        ];
    }
}
