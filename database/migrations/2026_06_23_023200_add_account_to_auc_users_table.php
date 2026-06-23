<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('auc_users', function (Blueprint $table) {
            $table->string('account', 18)->nullable()->after('id')->unique();
        });

        DB::table('auc_users')
            ->orderBy('id')
            ->get(['id'])
            ->each(function (object $user, int $index): void {
                DB::table('auc_users')
                    ->where('id', $user->id)
                    ->update(['account' => 'account'.$this->alphabeticSuffix($index + 1)]);
            });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('auc_users', function (Blueprint $table) {
            $table->dropUnique(['account']);
            $table->dropColumn('account');
        });
    }

    private function alphabeticSuffix(int $number): string
    {
        $suffix = '';

        while ($number > 0) {
            $number--;
            $suffix = chr(97 + ($number % 26)).$suffix;
            $number = intdiv($number, 26);
        }

        return $suffix;
    }
};
