<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (! Schema::hasTable('auc_tenant_applications')) {
            Schema::create('auc_tenant_applications', function (Blueprint $table) {
                $table->id();
                $table->foreignId('tenant_id')->constrained('auc_tenants')->cascadeOnDelete();
                $table->foreignId('application_id')->constrained('auc_applications')->cascadeOnDelete();
                $table->json('required_permissions')->nullable();
                $table->string('status')->default('active');
                $table->unsignedInteger('sort_order')->default(0);
                $table->timestamps();

                $table->unique(['tenant_id', 'application_id']);
                $table->index(['tenant_id', 'status']);
                $table->index(['application_id', 'status']);
            });
        }

        $applicationColumns = Schema::getColumnListing('auc_applications');

        if (in_array('tenant_id', $applicationColumns, true)) {
            DB::table('auc_applications')
                ->orderBy('id')
                ->get(['id', 'tenant_id', 'required_permissions', 'status', 'created_at', 'updated_at'])
                ->each(function (object $application): void {
                    DB::table('auc_tenant_applications')->updateOrInsert([
                        'tenant_id' => $application->tenant_id,
                        'application_id' => $application->id,
                    ], [
                        'required_permissions' => $application->required_permissions,
                        'status' => $application->status ?? 'active',
                        'sort_order' => 0,
                        'created_at' => $application->created_at,
                        'updated_at' => $application->updated_at,
                    ]);
                });
        }

        Schema::table('auc_applications', function (Blueprint $table) use ($applicationColumns): void {
            if (in_array('code', $applicationColumns, true)) {
                $table->dropUnique('auc_applications_tenant_id_code_unique');
                $table->dropColumn('code');
            }

            if (in_array('tenant_id', $applicationColumns, true)) {
                if (Config::string('database.default') === 'sqlite') {
                    $table->dropIndex('auc_applications_tenant_id_status_index');
                }

                $table->dropConstrainedForeignId('tenant_id');
            }

            if (in_array('required_permissions', $applicationColumns, true)) {
                $table->dropColumn('required_permissions');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('auc_applications', function (Blueprint $table): void {
            $table->foreignId('tenant_id')->nullable()->after('id')->constrained('auc_tenants')->nullOnDelete();
            $table->json('required_permissions')->nullable()->after('icon');
        });

        DB::table('auc_tenant_applications')
            ->orderBy('id')
            ->get(['tenant_id', 'application_id', 'required_permissions'])
            ->each(function (object $tenantApplication): void {
                DB::table('auc_applications')
                    ->where('id', $tenantApplication->application_id)
                    ->update([
                        'tenant_id' => $tenantApplication->tenant_id,
                        'required_permissions' => $tenantApplication->required_permissions,
                    ]);
            });

        Schema::dropIfExists('auc_tenant_applications');
    }
};
