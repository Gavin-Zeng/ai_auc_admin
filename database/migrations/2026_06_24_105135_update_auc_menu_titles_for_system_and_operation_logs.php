<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        DB::table('auc_menus')
            ->where('code', 'applications')
            ->update(['title' => '系统管理']);

        DB::table('auc_menus')
            ->where('code', 'audit_logs')
            ->update(['title' => '操作日志']);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::table('auc_menus')
            ->where('code', 'applications')
            ->update(['title' => '应用管理']);

        DB::table('auc_menus')
            ->where('code', 'audit_logs')
            ->update(['title' => '审计日志']);
    }
};
