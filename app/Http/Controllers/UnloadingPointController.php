<?php

namespace App\Http\Controllers;

use App\Models\UnloadingPoint;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class UnloadingPointController extends Controller
{
    /** Pesan konfirmasi setelah data disimpan (ditampilkan sebagai toast di halaman list). */
    private const PESAN_SUKSES = 'Terima kasih, data Anda berhasil diinput. Silakan cek kesesuaian data pada tampilan menu Dashboard.';

    public function index(Request $request): Response
    {
        $orgId = auth()->user()->organization_id;

        $points = UnloadingPoint::where('organization_id', $orgId)
            ->when($request->search, fn ($q) => $q->where('name', 'like', "%{$request->search}%")
                ->orWhere('customer_name', 'like', "%{$request->search}%"))
            ->latest()
            ->paginate(10);

        return Inertia::render('UnloadingPoints/Index', [
            'points'  => $points,
            'filters' => $request->only(['search']),
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
        return Inertia::render('UnloadingPoints/Create');
    }

    private function rules(): array
    {
        return [
            'name'          => 'required|string|max:255',
            'customer_name' => 'nullable|string|max:255',
            'latitude'      => 'required|numeric|between:-90,90',
            'longitude'     => 'required|numeric|between:-180,180',
            'province'      => 'required|string|max:100',
            'city'          => 'required|string|max:100',
            'district'      => 'nullable|string|max:100',
            'address'       => 'nullable|string',
            'capacity'      => 'nullable|numeric|min:0',
            'unit'          => 'required|string|in:ton,kg',
            'price'         => 'nullable|numeric|min:0',
            'has_jetty'     => 'boolean',
            'jetty_name'    => 'nullable|string|max:255',
            'pic_name'      => 'nullable|string|max:100',
            'pic_phone'     => 'nullable|string|max:30',
            'notes'         => 'nullable|string',
        ];
    }

    /** Kolom price & has_jetty NOT NULL — form boleh kosong, jadi null dinormalkan. */
    private function normalize(array $data): array
    {
        $data['price']     = $data['price'] ?? 0;
        $data['has_jetty'] = $data['has_jetty'] ?? false;

        return $data;
    }

    public function store(Request $request)
    {
        $validated = $this->normalize($request->validate($this->rules()));

        UnloadingPoint::create([
            ...$validated,
            'organization_id' => auth()->user()->organization_id,
            'status'          => 'active',
        ]);

        return redirect()->route('unloading-points.index')
            ->with('success', self::PESAN_SUKSES);
    }

    public function show(UnloadingPoint $unloadingPoint): Response
    {
        $this->authorize('viewAny', UnloadingPoint::class);

        return Inertia::render('UnloadingPoints/Show', [
            'point' => $unloadingPoint,
            'can'   => $this->permissions(),
        ]);
    }

    public function edit(UnloadingPoint $unloadingPoint): Response
    {
        $this->authorize('update', $unloadingPoint);

        return Inertia::render('UnloadingPoints/Edit', [
            'point' => $unloadingPoint,
        ]);
    }

    public function update(Request $request, UnloadingPoint $unloadingPoint)
    {
        $this->authorize('update', $unloadingPoint);

        $unloadingPoint->update($this->normalize($request->validate($this->rules())));

        return redirect()->route('unloading-points.index')
            ->with('success', self::PESAN_SUKSES);
    }

    public function destroy(UnloadingPoint $unloadingPoint)
    {
        $this->authorize('delete', $unloadingPoint);

        $unloadingPoint->delete();

        return redirect()->route('unloading-points.index')
            ->with('success', 'Titik bongkar berhasil dihapus');
    }
}
