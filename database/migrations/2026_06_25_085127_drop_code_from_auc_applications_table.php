<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $columns = Schema::getColumnListing('auc_applications');

        if (! in_array('code', $columns, true)) {
            return;
        }

        Schema::table('auc_applications', function (Blueprint $table) {
            $table->dropUnique('auc_applications_tenant_id_code_unique');
            $table->dropColumn('code');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('auc_applications', function (Blueprint $table) {
            $table->string('code')->after('tenant_id');
            $table->unique(['tenant_id', 'code']);
        });
    }
};
