<?php

namespace App\Http\Controllers;

use App\Models\CalculationScenario;
use App\Models\JettyPoint;
use App\Models\PalmOilSource;
use App\Models\UnloadingPoint;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ProjectCalculatorController extends Controller
{
    public function index(Request $request): Response
    {
        $orgId = auth()->user()->organization_id;

        return Inertia::render('ProjectCalculator/Index', [
            'sources' => PalmOilSource::where('organization_id', $orgId)->where('status', 'active')
                ->get(['id', 'name', 'city', 'province', 'price', 'stock_volume', 'unit']),
            'customers' => UnloadingPoint::where('organization_id', $orgId)->where('status', 'active')
                ->get(['id', 'name', 'customer_name', 'city', 'province', 'price', 'capacity']),
            'jetties' => JettyPoint::where('organization_id', $orgId)->where('status', 'active')
                ->get(['id', 'name', 'city', 'province', 'price']),
            'scenarios' => CalculationScenario::with('user:id,name')
                ->where('organization_id', $orgId)
                ->latest()
                ->get(),
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name'           => 'required|string|max:255',
            'volume'         => 'required|numeric|min:0',
            'price_cangkang' => 'required|numeric|min:0',
            'transport'      => 'required|numeric|min:0',
            'via_water'      => 'boolean',
            'tongkang'       => 'required|numeric|min:0',
            'handling'       => 'required|numeric|min:0',
            'muat'           => 'required|numeric|min:0',
            'bongkar'        => 'required|numeric|min:0',
            'price_customer' => 'required|numeric|min:0',
            'is_wapu'        => 'boolean',
            'target_margin'  => 'required|numeric|min:0|max:100',
            'total_cost'     => 'required|numeric',
            'total_revenue'  => 'required|numeric',
            'total_margin'   => 'required|numeric',
            'margin_pct'     => 'required|numeric',
            'notes'          => 'nullable|string',
        ]);

        CalculationScenario::create([
            ...$validated,
            'is_wapu'         => $validated['is_wapu'] ?? false,
            'organization_id' => auth()->user()->organization_id,
            'user_id'         => auth()->id(),
        ]);

        return back()->with('success', 'Skenario kalkulasi berhasil disimpan');
    }

    public function destroy(CalculationScenario $scenario)
    {
        abort_unless($scenario->organization_id === auth()->user()->organization_id, 403);

        $scenario->delete();

        return back()->with('success', 'Skenario dihapus');
    }
}
