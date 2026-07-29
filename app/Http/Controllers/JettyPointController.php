<?php

namespace App\Http\Controllers;

use App\Models\JettyPoint;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class JettyPointController extends Controller
{
    /** Pesan konfirmasi setelah data disimpan (ditampilkan sebagai toast di halaman list). */
    private const PESAN_SUKSES = 'Terima kasih, data Anda berhasil diinput. Silakan cek kesesuaian data pada tampilan menu Dashboard.';

    public function index(Request $request): Response
    {
        $orgId = auth()->user()->organization_id;

        $jetties = JettyPoint::where('organization_id', $orgId)
            ->when($request->search, fn ($q) => $q->where('name', 'like', "%{$request->search}%")
                ->orWhere('operator', 'like', "%{$request->search}%"))
            ->latest()
            ->paginate(10);

        return Inertia::render('JettyPoints/Index', [
            'jetties' => $jetties,
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
        return Inertia::render('JettyPoints/Create');
    }

    private function rules(): array
    {
        return [
            'name'      => 'required|string|max:255',
            'operator'  => 'nullable|string|max:255',
            'latitude'  => 'required|numeric|between:-90,90',
            'longitude' => 'required|numeric|between:-180,180',
            'province'  => 'required|string|max:100',
            'city'      => 'required|string|max:100',
            'district'  => 'nullable|string|max:100',
            'address'   => 'nullable|string',
            'capacity'  => 'nullable|numeric|min:0',
            'unit'      => 'required|string|in:ton,kg',
            'price'     => 'nullable|numeric|min:0',
            'draft'     => 'nullable|string|max:50',
            'pic_name'  => 'nullable|string|max:100',
            'pic_phone' => 'nullable|string|max:30',
            'notes'     => 'nullable|string',
        ];
    }

    /** Kolom price NOT NULL (default 0) — form boleh kosong, jadi null dinormalkan ke 0. */
    private function normalize(array $data): array
    {
        $data['price'] = $data['price'] ?? 0;

        return $data;
    }

    public function store(Request $request)
    {
        $validated = $this->normalize($request->validate($this->rules()));

        JettyPoint::create([
            ...$validated,
            'organization_id' => auth()->user()->organization_id,
            'status'          => 'active',
        ]);

        return redirect()->route('jetty-points.index')
            ->with('success', self::PESAN_SUKSES);
    }

    public function show(JettyPoint $jettyPoint): Response
    {
        $this->authorize('viewAny', JettyPoint::class);

        return Inertia::render('JettyPoints/Show', [
            'jetty' => $jettyPoint,
            'can'   => $this->permissions(),
        ]);
    }

    public function edit(JettyPoint $jettyPoint): Response
    {
        $this->authorize('update', $jettyPoint);

        return Inertia::render('JettyPoints/Edit', [
            'jetty' => $jettyPoint,
        ]);
    }

    public function update(Request $request, JettyPoint $jettyPoint)
    {
        $this->authorize('update', $jettyPoint);

        $jettyPoint->update($this->normalize($request->validate($this->rules())));

        return redirect()->route('jetty-points.index')
            ->with('success', self::PESAN_SUKSES);
    }

    public function destroy(JettyPoint $jettyPoint)
    {
        $this->authorize('delete', $jettyPoint);

        $jettyPoint->delete();

        return redirect()->route('jetty-points.index')
            ->with('success', 'Titik dermaga berhasil dihapus');
    }
}
