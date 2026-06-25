<?php

namespace App\Console\Commands;

use App\Support\AucDiagnostics;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('auc:diagnose')]
#[Description('检查 AUC 默认公司、权限、菜单、角色和应用配置是否完整')]
class AucDiagnose extends Command
{
    /**
     * Execute the console command.
     */
    public function handle(AucDiagnostics $diagnostics): int
    {
        $report = $diagnostics->report();

        foreach ($report['checks'] as $check) {
            if ($check['passed']) {
                $this->components->info($check['label']);
            } else {
                $this->components->error($check['label']);
            }
        }

        return $report['passed'] ? self::SUCCESS : self::FAILURE;
    }
}
