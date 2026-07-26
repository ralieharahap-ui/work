<?php

namespace App\Http\Controllers;

use App\Models\PalmOilSource;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class PalmOilSourceController extends Controller
{
    public function index(Request $request): Response
    {
        $orgId = auth()->user()->organization_id;

        $sources = PalmOilSource::where('organization_id', $orgId)
            ->when($request->search, fn($q) => $q->where('name', 'like', "%{$request->search}%")
                ->orWhere('supplier_name', 'like', "%{$request->search}%"))
            ->when($request->status, fn($q) => $q->where('status', $request->status))
            ->latest()
            ->paginate(10);

        return Inertia::render('PalmOilSources/Index', [
            'sources' => $sources,
            'filters' => $request->only(['search', 'status']),
            'can'     => $this->permissions(),
        ]);
    }

    private function permissions(): array
    {
        $user = auth()->user();

        return [
            'view'   => $user->can('inventory.view'),
            'create' => $user->can('inventory.create'),
            'edit'   => $user->can('inventory.edit'),
            'delete' => $user->can('inventory.delete'),
        ];
    }

    public function create(): Response
    {
        return Inertia::render('PalmOilSources/Create');
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'supplier_name' => 'nullable|string|max:255',
            'latitude' => 'required|numeric|between:-90,90',
            'longitude' => 'required|numeric|between:-180,180',
            'province' => 'required|string|max:100',
            'city' => 'required|string|max:100',
            'district' => 'nullable|string|max:100',
            'address' => 'nullable|string',
            'stock_volume' => 'required|numeric|min:0',
            'unit' => 'required|string|in:ton,kg,g',
            'stock_min' => 'required|numeric|min:0',
            'price' => 'nullable|numeric|min:0',
            'notes' => 'nullable|string',
        ]);

        $source = PalmOilSource::create([
            ...$validated,
            'organization_id' => auth()->user()->organization_id,
            'status' => 'active',
        ]);

        return redirect()->route('palm-oil-sources.show', $source->id)
            ->with('success', 'Sumber cangkang sawit berhasil ditambahkan');
    }

    public function show(PalmOilSource $palmOilSource): Response
    {
        $this->authorize('viewAny', PalmOilSource::class);

        return Inertia::render('PalmOilSources/Show', [
            'source' => $palmOilSource,
            'can'    => $this->permissions(),
        ]);
    }

    public function edit(PalmOilSource $palmOilSource): Response
    {
        $this->authorize('update', $palmOilSource);

        return Inertia::render('PalmOilSources/Edit', [
            'source' => $palmOilSource,
        ]);
    }

    public function update(Request $request, PalmOilSource $palmOilSource)
    {
        $this->authorize('update', $palmOilSource);

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'supplier_name' => 'nullable|string|max:255',
            'latitude' => 'required|numeric|between:-90,90',
            'longitude' => 'required|numeric|between:-180,180',
            'province' => 'required|string|max:100',
            'city' => 'required|string|max:100',
            'district' => 'nullable|string|max:100',
            'address' => 'nullable|string',
            'stock_volume' => 'required|numeric|min:0',
            'unit' => 'required|string|in:ton,kg,g',
            'stock_min' => 'required|numeric|min:0',
            'price' => 'nullable|numeric|min:0',
            'notes' => 'nullable|string',
        ]);

        $palmOilSource->update($validated);

        return redirect()->route('palm-oil-sources.show', $palmOilSource->id)
            ->with('success', 'Data sumber cangkang sawit berhasil diperbarui');
    }

    public function destroy(PalmOilSource $palmOilSource)
    {
        $this->authorize('delete', $palmOilSource);

        $palmOilSource->delete();

        return redirect()->route('palm-oil-sources.index')
            ->with('success', 'Sumber cangkang sawit berhasil dihapus');
    }

    public function api()
    {
        $orgId = auth()->user()->organization_id;

        $sources = PalmOilSource::where('organization_id', $orgId)
            ->where('status', 'active')
            ->get();

        return response()->json([
            'sources' => $sources,
            'totalVolume' => $sources->sum('stock_volume'),
            'sourceCount' => $sources->count(),
            'lowStockCount' => $sources->filter(fn($s) => $s->isLowStock())->count(),
        ]);
    }
}
