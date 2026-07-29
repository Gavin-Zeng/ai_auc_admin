<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Game;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class GameController extends Controller
{
    public function index(Request $request): Response
    {
        abort_unless($request->user()->isPlatformAdmin(), 403);
        $search = $request->string('search')->toString();
        $items = Game::query()
            ->select(['id', 'name', 'app_id', 'old_name', 'old_id', 'pkg_name'])
            ->when($search, fn (Builder $q) => $q->where(fn (Builder $query) => $query->where('name', 'like', "%{$search}%")->orWhere('app_id', 'like', "%{$search}%")))
            ->latest('id')
            ->paginate(10)
            ->withQueryString();

        return Inertia::render('admin/ResourceIndex', [
            'resource' => ['name' => 'games', 'label' => '游戏管理', 'description' => '固定游戏目录，仅供权限分配使用。', 'readOnly' => true, 'fields' => [], 'columns' => ['name', 'app_id', 'old_name', 'old_id', 'pkg_name']],
            'items' => $items,
            'filters' => ['search' => $search, 'company_id' => null],
            'options' => [],
        ]);
    }
}
