<?php

namespace App\Http\Controllers;

use App\Models\JettyPoint;
use App\Models\PalmOilSource;
use App\Models\PawmPLTU;
use App\Models\UnloadingPoint;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function __invoke(Request $request): Response
    {
        $orgId = auth()->user()->organization_id;

        $palmSources = PalmOilSource::where('organization_id', $orgId)
            ->where('status', 'active')
            ->get();

        $unloadingPoints = UnloadingPoint::where('organization_id', $orgId)
            ->where('status', 'active')
            ->get();

        $jettyPoints = JettyPoint::where('organization_id', $orgId)
            ->where('status', 'active')
            ->get();

        // Data PLTU tetap dikirim (tidak dihapus) — hanya tidak lagi digambar sebagai
        // titik oranye di peta, sesuai permintaan tampilan.
        $pltuLocations = PawmPLTU::where('status', 'operational')->get();

        return Inertia::render('Dashboard/Index', [
            'palm_sources'     => $palmSources,
            'unloading_points' => $unloadingPoints,
            'jetty_points'     => $jettyPoints,
            'pltu_locations'   => $pltuLocations,
            'palm_summary'     => [
                'total_volume'    => $palmSources->sum('stock_volume'),
                'source_count'    => $palmSources->count(),
                'low_stock_count' => $palmSources->filter(fn ($s) => $s->isLowStock())->count(),
                'customer_count'  => $unloadingPoints->count(),
                'jetty_count'     => $jettyPoints->count(),
                'pltu_count'      => $pltuLocations->count(),
            ],
        ]);
    }
}
