<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Support\AucDiagnostics;
use Inertia\Inertia;
use Inertia\Response;

class DiagnosticsController extends Controller
{
    public function __invoke(AucDiagnostics $diagnostics): Response
    {
        return Inertia::render('admin/Diagnostics', [
            'report' => $diagnostics->report(),
        ]);
    }
}
