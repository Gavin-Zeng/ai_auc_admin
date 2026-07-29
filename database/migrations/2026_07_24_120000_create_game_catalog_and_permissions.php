<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('auc_games', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('parent_id')->nullable()->constrained('auc_games')->nullOnDelete();
            $table->string('name', 120);
            $table->string('app_id', 80)->nullable()->index();
            $table->string('old_name', 120)->nullable();
            $table->dateTime('uptime')->nullable();
            $table->string('ios_appid', 80)->nullable();
            $table->string('yy_gamename', 120)->nullable();
            $table->decimal('proportion', 10, 4)->default(1);
            $table->string('game', 80)->nullable()->index();
            $table->string('yyb_id', 80)->nullable();
            $table->string('old_id', 80)->nullable()->index();
            $table->string('plat', 32)->nullable();
            $table->string('company', 80)->nullable();
            $table->string('business', 32)->nullable();
            $table->string('pkg_name', 160)->nullable();
            $table->text('channel_config')->nullable();
            $table->string('tag', 120)->nullable();
            $table->decimal('cost_reg', 12, 4)->default(0);
            $table->string('os', 32)->nullable();
            $table->boolean('status')->default(true)->index();
            $table->timestamps();
        });

        Schema::create('auc_tenant_games', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained('auc_tenants')->cascadeOnDelete();
            $table->foreignId('game_id')->constrained('auc_games')->cascadeOnDelete();
            $table->timestamps();
            $table->unique(['tenant_id', 'game_id']);
        });

        Schema::create('auc_user_game_permissions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained('auc_tenants')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('auc_users')->cascadeOnDelete();
            $table->string('scope_type', 16);
            $table->foreignId('game_id')->nullable()->constrained('auc_games')->cascadeOnDelete();
            $table->boolean('status')->default(true)->index();
            $table->timestamps();
            $table->unique(['user_id', 'scope_type', 'game_id'], 'auc_user_game_permissions_unique');
            $table->index(['tenant_id', 'user_id']);
        });

        $mother = DB::table('auc_games')->insertGetId([
            'name' => '小小武馆', 'old_name' => '小小武馆', 'game' => 'wuguan',
            'old_id' => 'hw_f1200016', 'company' => 'bingniao', 'business' => 'hw',
            'status' => true, 'created_at' => now(), 'updated_at' => now(),
        ]);
        DB::table('auc_games')->insert([
            'parent_id' => $mother, 'name' => '小小武馆-iOS', 'app_id' => 'h1000042',
            'old_name' => '小小武馆', 'uptime' => '2024-01-31 15:48:40',
            'ios_appid' => 'id1619033506', 'proportion' => 1, 'game' => 'wuguan_ios',
            'old_id' => 'hw_f1200016', 'plat' => 'ios', 'company' => 'bingniao',
            'business' => 'hw', 'pkg_name' => 'com.idlegames.wuguan.appstore',
            'cost_reg' => 0, 'os' => 'ios', 'status' => true, 'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('auc_user_game_permissions');
        Schema::dropIfExists('auc_tenant_games');
        Schema::dropIfExists('auc_games');
    }
};
