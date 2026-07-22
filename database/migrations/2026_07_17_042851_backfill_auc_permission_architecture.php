<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $usedApplicationCodes = DB::table('auc_applications')
            ->whereNotNull('code')
            ->pluck('code')
            ->mapWithKeys(fn (string $code): array => [strtolower($code) => true])
            ->all();

        DB::table('auc_applications')
            ->orderBy('id')
            ->get(['id', 'client_id', 'code'])
            ->each(function (object $application) use (&$usedApplicationCodes): void {
                if (filled($application->code)) {
                    return;
                }

                $baseCode = Str::of($application->client_id)
                    ->lower()
                    ->replaceMatches('/[^a-z0-9_]+/', '_')
                    ->trim('_')
                    ->limit(32, '')
                    ->toString();
                $baseCode = $baseCode !== '' ? $baseCode : 'application';
                $baseCode = preg_match('/^[a-z]/', $baseCode) === 1 ? $baseCode : 'app_'.$baseCode;
                $code = $baseCode;
                $suffix = 2;

                while (isset($usedApplicationCodes[strtolower($code)])) {
                    $code = Str::limit($baseCode, 27, '').'_'.$suffix;
                    $suffix++;
                }

                DB::table('auc_applications')->where('id', $application->id)->update(['code' => $code]);
                $usedApplicationCodes[strtolower($code)] = true;
            });

        DB::table('auc_user_roles')
            ->whereNull('tenant_user_id')
            ->orderBy('id')
            ->get(['id', 'tenant_id', 'user_id'])
            ->each(function (object $userRole): void {
                $membershipId = DB::table('auc_tenant_users')
                    ->where('tenant_id', $userRole->tenant_id)
                    ->where('user_id', $userRole->user_id)
                    ->value('id');

                if ($membershipId === null) {
                    throw new RuntimeException("Cannot resolve membership for auc_user_roles row {$userRole->id}.");
                }

                DB::table('auc_user_roles')->where('id', $userRole->id)->update(['tenant_user_id' => $membershipId]);
            });

        DB::table('auc_tenant_applications as tenant_application')
            ->join('auc_permissions as permission', 'permission.application_id', '=', 'tenant_application.application_id')
            ->where('permission.status', 'active')
            ->orderBy('tenant_application.id')
            ->select([
                'tenant_application.tenant_id',
                'tenant_application.application_id',
                'tenant_application.status',
                'permission.id as permission_id',
            ])
            ->get()
            ->each(function (object $row): void {
                DB::table('auc_tenant_permissions')->updateOrInsert([
                    'tenant_id' => $row->tenant_id,
                    'permission_id' => $row->permission_id,
                ], [
                    'application_id' => $row->application_id,
                    'source' => 'LEGACY_BOOTSTRAP',
                    'status' => $row->status,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            });

        if (DB::table('auc_applications')->whereNull('code')->exists()
            || DB::table('auc_user_roles')->whereNull('tenant_user_id')->exists()) {
            throw new RuntimeException('AUC permission architecture backfill is incomplete.');
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::table('auc_tenant_permissions')->where('source', 'LEGACY_BOOTSTRAP')->delete();
    }
};
