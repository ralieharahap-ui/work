<?php

namespace App\Http\Controllers;

use App\Models\Document;
use App\Models\JettyPoint;
use App\Models\JournalEntry;
use App\Models\JournalLine;
use App\Models\TaskProject;
use Illuminate\Support\Facades\DB;
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
            'overview' => $this->overview($orgId, (int) $request->query('year', now()->year), $palmSources),
        ]);
    }

    /**
     * Ringkasan untuk panel dashboard (hanya baca). Semua angka berasal dari data yang ada:
     * proyek (task_projects), Surat Jalan (ton), jurnal posted akun pendapatan, & stok sumber.
     */
    private function overview(string $orgId, int $year, $palmSources): array
    {
        $year = max(2000, min($year, (int) now()->year + 1));

        // Proyek berjalan + progres tugas selesai.
        $projects = TaskProject::where('organization_id', $orgId)
            ->withCount(['tasks', 'tasks as done_count' => fn ($q) => $q->where('status', 'Done')])
            ->latest('updated_at')
            ->get();
        $closed = ['done', 'completed', 'selesai', 'closed', 'cancelled', 'dibatalkan'];
        $activeProjects = $projects->reject(fn ($p) => in_array(strtolower((string) $p->status), $closed, true));

        // Pengiriman (ton) dari item Surat Jalan bersatuan ton, per bulan.
        $shipTons = function (int $y) use ($orgId): array {
            $months = array_fill(1, 12, 0.0);
            Document::where('organization_id', $orgId)
                ->where('type', 'surat_jalan')
                ->where(fn ($q) => $q->whereNull('status')->orWhere('status', '!=', 'cancelled'))
                ->whereYear('doc_date', $y)
                ->get(['doc_date', 'meta'])
                ->each(function ($d) use (&$months) {
                    foreach ($d->meta['items'] ?? [] as $it) {
                        if (strtolower(trim((string) ($it['unit'] ?? ''))) === 'ton') {
                            $months[(int) $d->doc_date->month] += (float) ($it['qty'] ?? 0);
                        }
                    }
                });
            return array_values($months);
        };
        $monthly   = $shipTons($year);
        $prevTons  = array_sum($shipTons($year - 1));

        // Nilai penjualan = saldo kredit akun pendapatan pada jurnal posted.
        $revenue = fn (int $y) => (float) JournalLine::query()
            ->join('journal_entries as je', 'je.id', '=', 'journal_lines.journal_entry_id')
            ->join('accounts as a', 'a.id', '=', 'journal_lines.account_id')
            ->where('je.organization_id', $orgId)
            ->where('je.is_posted', true)
            ->where('a.type', 'revenue')
            // Hanya pendapatan usaha (4xxxx); pendapatan lainnya (bunga, laba aset) bukan penjualan.
            ->where(fn ($q) => $q->where('a.fs_group', 'Pendapatan')
                ->orWhere(fn ($w) => $w->whereNull('a.fs_group')->where('a.code', 'like', '4%')))
            ->whereYear('je.entry_date', $y)
            ->sum(DB::raw('journal_lines.credit - journal_lines.debit'));
        $salesNow  = $revenue($year);
        $salesPrev = $revenue($year - 1);

        $trend = fn (float $now, float $prev) => $prev > 0 ? round(($now - $prev) / $prev * 100, 1) : null;

        // Komposisi stok per provinsi (4 terbesar + lainnya).
        $byProv = $palmSources->groupBy(fn ($s) => ucwords(mb_strtolower(trim((string) $s->province))) ?: 'Lainnya')
            ->map(fn ($g) => (float) $g->sum('stock_volume'))
            ->sortDesc();
        $composition = $byProv->take(4)->map(fn ($v, $k) => ['label' => $k, 'value' => $v])->values()->all();
        if ($byProv->count() > 4) {
            $composition[] = ['label' => 'Lainnya', 'value' => (float) $byProv->slice(4)->sum()];
        }

        // Aktivitas terbaru: dokumen & jurnal.
        $docs = Document::where('organization_id', $orgId)->latest()->limit(6)->get(['id', 'type', 'number', 'status', 'meta', 'created_at'])
            ->map(fn ($d) => [
                'kind'  => 'document',
                'type'  => $d->type,
                'title' => $d->number,
                'sub'   => $d->meta['extra']['perihal'] ?? ($d->meta['party']['name'] ?? null),
                'status'=> $d->status ?? 'on_review',
                'href'  => '/documents/' . $d->id,
                'at'    => $d->created_at?->toIso8601String(),
            ]);
        $journals = JournalEntry::where('organization_id', $orgId)->latest()->limit(6)->get(['id', 'entry_no', 'description', 'status', 'created_at'])
            ->map(fn ($j) => [
                'kind'  => 'journal',
                'type'  => 'journal',
                'title' => $j->entry_no,
                'sub'   => $j->description,
                'status'=> $j->status,
                'href'  => '/books/journal',
                'at'    => $j->created_at?->toIso8601String(),
            ]);
        $activity = $docs->concat($journals)->sortByDesc('at')->take(6)->values()->all();

        return [
            'year'            => $year,
            'active_projects' => $activeProjects->count(),
            'total_projects'  => $projects->count(),
            'shipment_tons'   => array_sum($monthly),
            'shipment_trend'  => $trend(array_sum($monthly), $prevTons),
            'monthly_tons'    => $monthly,
            'sales'           => $salesNow,
            'sales_trend'     => $trend($salesNow, $salesPrev),
            'composition'     => $composition,
            'projects'        => $activeProjects->take(3)->map(fn ($p) => [
                'title'    => $p->title,
                'tasks'    => (int) $p->tasks_count,
                'done'     => (int) $p->done_count,
                'end_date' => $p->end_date?->toDateString(),
            ])->values()->all(),
            'activity'        => $activity,
        ];
    }
}
