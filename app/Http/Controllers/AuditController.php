<?php

namespace App\Http\Controllers;

use App\Services\AnomalyDetector;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AuditController extends Controller
{
    /**
     * Layer Audit AI (Tahap 8 PDF) — antrean pengecualian jurnal.
     */
    public function index(Request $request, AnomalyDetector $detector): Response
    {
        $orgId = auth()->user()->organization_id;
        $year  = (int) $request->get('year', now()->year);

        $result = $detector->scan($orgId, $year);

        return Inertia::render('Books/Audit', [
            'year'       => $year,
            'exceptions' => $result['exceptions'],
            'summary'    => $result['summary'],
            'margin'     => $result['margin'],
        ]);
    }
}
