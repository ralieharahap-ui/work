<?php

namespace App\Services;

use App\Models\Account;
use App\Models\JournalEntry;
use Illuminate\Support\Str;

/**
 * Layer audit AI (Tahap 8 automasi PDF: "Continuous test for unusual journal,
 * unsupported accounts, duplicate payment, negative margin").
 *
 * Menghasilkan antrean pengecualian (exception queue) berbasis aturan yang
 * deterministik — dipindai dari jurnal, tanpa layanan luar.
 */
class AnomalyDetector
{
    /**
     * Pindai jurnal satu organisasi pada satu tahun.
     * @return array{exceptions: array, summary: array, margin: array}
     */
    public function scan(string $orgId, int $year): array
    {
        $entries = JournalEntry::where('organization_id', $orgId)
            ->whereYear('entry_date', $year)
            ->with(['lines.account:id,code,name,is_active,type'])
            ->orderBy('entry_date')
            ->get();

        $exceptions = collect()
            ->concat($this->unbalanced($entries))
            ->concat($this->zeroAmount($entries))
            ->concat($this->inactiveAccounts($entries))
            ->concat($this->missingAttachment($entries))
            ->concat($this->duplicates($entries));

        // Urutkan berdasarkan tingkat keparahan (high → low).
        $rank = ['high' => 0, 'medium' => 1, 'low' => 2];
        $exceptions = $exceptions
            ->sortBy(fn ($e) => $rank[$e['severity']] ?? 9)
            ->values()
            ->all();

        return [
            'exceptions' => $exceptions,
            'summary'    => [
                'total'  => count($exceptions),
                'high'   => collect($exceptions)->where('severity', 'high')->count(),
                'medium' => collect($exceptions)->where('severity', 'medium')->count(),
                'low'    => collect($exceptions)->where('severity', 'low')->count(),
            ],
            'margin' => $this->negativeMargin($orgId, $year),
        ];
    }

    /** Jurnal tidak seimbang (debet ≠ kredit). */
    private function unbalanced($entries): array
    {
        return $entries->filter(fn ($e) => ! $e->isBalanced())
            ->map(fn ($e) => $this->row($e, 'unbalanced', 'high',
                'Jurnal tidak seimbang',
                'Total debet ' . $this->rp($e->lines->sum('debit')) . ' ≠ kredit ' . $this->rp($e->lines->sum('credit')) . '.'))
            ->values()->all();
    }

    /** Jurnal sudah diposting namun bernilai nol. */
    private function zeroAmount($entries): array
    {
        return $entries->filter(fn ($e) => $e->is_posted && (float) $e->lines->sum('debit') === 0.0)
            ->map(fn ($e) => $this->row($e, 'zero', 'low',
                'Nominal nol', 'Jurnal telah diposting namun total nilainya nol.'))
            ->values()->all();
    }

    /** Baris memakai akun nonaktif (unsupported account). */
    private function inactiveAccounts($entries): array
    {
        $out = [];
        foreach ($entries as $e) {
            $bad = $e->lines->filter(fn ($l) => $l->account && ! $l->account->is_active)
                ->map(fn ($l) => $l->account->code . ' ' . $l->account->name)
                ->unique()->values();
            if ($bad->isNotEmpty()) {
                $out[] = $this->row($e, 'inactive_account', 'medium',
                    'Akun nonaktif dipakai', 'Akun tidak aktif: ' . $bad->implode(', ') . '.');
            }
        }
        return $out;
    }

    /** Jurnal terposting tanpa dokumen pendukung. */
    private function missingAttachment($entries): array
    {
        return $entries->filter(fn ($e) => $e->is_posted && $e->lines->sum('debit') > 0 && $e->attachments()->count() === 0)
            ->map(fn ($e) => $this->row($e, 'no_attachment', 'low',
                'Tanpa dokumen pendukung', 'Jurnal terposting tanpa lampiran bukti (document completeness).'))
            ->values()->all();
    }

    /**
     * Kemungkinan duplikat/pembayaran ganda: tanggal + total nilai sama pada
     * lebih dari satu jurnal (indikatif — perlu ditinjau manual).
     */
    private function duplicates($entries): array
    {
        $out = [];
        $entries->groupBy(fn ($e) => $e->entry_date->toDateString() . '|' . round($e->lines->sum('debit'), 2))
            ->filter(fn ($grp) => $grp->count() > 1 && (float) $grp->first()->lines->sum('debit') > 0)
            ->each(function ($grp) use (&$out) {
                $nos = $grp->pluck('entry_no')->implode(', ');
                foreach ($grp as $e) {
                    $out[] = $this->row($e, 'duplicate', 'medium',
                        'Kemungkinan duplikat',
                        'Tanggal & nilai identik dengan jurnal lain (' . $nos . '). Tinjau kemungkinan pembayaran ganda.');
                }
            });
        return $out;
    }

    /**
     * Margin negatif per bulan: total pendapatan (4xxx) < HPP (5xxx).
     * @return array<int,array>
     */
    private function negativeMargin(string $orgId, int $year): array
    {
        $revenueIds = Account::where('organization_id', $orgId)->where('type', 'revenue')->pluck('id');
        $cogsIds    = Account::where('organization_id', $orgId)
            ->where('code', 'like', '5%')->pluck('id');

        $out = [];
        for ($m = 1; $m <= 12; $m++) {
            $from = \Carbon\Carbon::create($year, $m, 1)->startOfMonth()->toDateString();
            $to   = \Carbon\Carbon::create($year, $m, 1)->endOfMonth()->toDateString();

            $rev = $this->sumLines($revenueIds, $from, $to, 'credit');
            $cogs = $this->sumLines($cogsIds, $from, $to, 'debit');
            if ($rev <= 0 && $cogs <= 0) continue;

            $margin = $rev - $cogs;
            if ($margin < 0) {
                $out[] = [
                    'month'  => \Carbon\Carbon::create($year, $m, 1)->translatedFormat('F'),
                    'revenue'=> $rev,
                    'cogs'   => $cogs,
                    'margin' => $margin,
                ];
            }
        }
        return $out;
    }

    private function sumLines($accountIds, string $from, string $to, string $side): float
    {
        return (float) \App\Models\JournalLine::whereIn('account_id', $accountIds)
            ->whereHas('journalEntry', fn ($q) => $q->where('is_posted', true)->whereBetween('entry_date', [$from, $to]))
            ->sum($side)
            - (float) \App\Models\JournalLine::whereIn('account_id', $accountIds)
                ->whereHas('journalEntry', fn ($q) => $q->where('is_posted', true)->whereBetween('entry_date', [$from, $to]))
                ->sum($side === 'credit' ? 'debit' : 'credit');
    }

    private function row(JournalEntry $e, string $type, string $severity, string $title, string $detail): array
    {
        return [
            'type'        => $type,
            'severity'    => $severity,
            'title'       => $title,
            'detail'      => $detail,
            'entry_id'    => $e->id,
            'entry_no'    => $e->entry_no,
            'entry_date'  => $e->entry_date->toDateString(),
            'description' => $e->description,
            'status'      => $e->status ?? 'draft',
        ];
    }

    private function rp($n): string
    {
        return 'Rp ' . number_format((float) $n, 0, ',', '.');
    }
}
