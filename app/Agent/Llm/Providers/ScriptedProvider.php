<?php

namespace App\Agent\Llm\Providers;

use App\Agent\Contracts\LlmProvider;
use App\Agent\Data\LlmRequest;
use App\Agent\Data\LlmResponse;
use Illuminate\Support\Str;

/**
 * Penyedia heuristik lokal & deterministik.
 *
 * Bukan tiruan LLM: ini adalah perencana berbasis aturan yang memahami
 * kosakata pekerjaan kantor (laporan, email, agenda, dokumen, rekonsiliasi,
 * tindak lanjut) dan menyusun rencana terstruktur dari tool yang benar-benar
 * tersedia. Fungsinya dua: (1) agent tetap bekerja penuh tanpa kunci API,
 * (2) test dan demo berjalan tanpa jaringan sehingga hasilnya dapat diulang.
 */
class ScriptedProvider implements LlmProvider
{
    /** Kata kunci → jenis task. Urutan menentukan prioritas pencocokan. */
    private const TASK_TYPES = [
        'data_comparison'      => ['bandingkan', 'banding', 'compare', 'selisih', 'perbedaan', 'rekonsiliasi', 'cocokkan'],
        'report_generation'    => ['laporan', 'report', 'rekap', 'ringkasan penjualan', 'kpi', 'summary penjualan', 'omzet'],
        'email_handling'       => ['email', 'e-mail', 'surel', 'balas', 'reply', 'inbox'],
        'calendar_scheduling'  => ['jadwal', 'meeting', 'rapat', 'agenda', 'kalender', 'calendar', 'undangan'],
        'document_preparation' => ['dokumen', 'surat', 'kontrak', 'invoice', 'penawaran', 'bast', 'memo', 'notulen', 'draft'],
        'followup'             => ['follow up', 'tindak lanjut', 'ingatkan', 'pengingat', 'belum selesai', 'overdue', 'terlambat'],
        'research'             => ['riset', 'research', 'cari informasi', 'analisa', 'analisis pasar', 'telusuri'],
    ];

    /** Kata yang menandakan tindakan keluar/tidak dapat dibatalkan. */
    private const HIGH_RISK_WORDS = ['kirim', 'send', 'blast', 'publish', 'umumkan', 'hapus', 'delete', 'bayar', 'transfer', 'undang'];

    public function name(): string
    {
        return 'scripted';
    }

    public function isConfigured(): bool
    {
        return true;
    }

    public function generate(LlmRequest $request): LlmResponse
    {
        return new LlmResponse(
            text: $this->reply($request),
            provider: $this->name(),
            model: 'heuristic-v1',
        );
    }

    public function structured(LlmRequest $request, array $schema): array
    {
        return match ($request->purpose) {
            'classify' => $this->classify($request),
            'plan'     => $this->plan($request),
            'reflect'  => $this->reflect($request),
            default    => ['text' => $this->reply($request)],
        };
    }

    public function stream(LlmRequest $request, callable $onChunk): LlmResponse
    {
        $response = $this->generate($request);
        $onChunk($response->text);

        return $response;
    }

    // ── Pemahaman task ────────────────────────────────────────────────────

    /** @return array<string, mixed> */
    private function classify(LlmRequest $request): array
    {
        $objective = $request->lastUserMessage();
        $haystack  = Str::lower($objective);

        $taskType = 'general';
        foreach (self::TASK_TYPES as $type => $words) {
            foreach ($words as $word) {
                if (str_contains($haystack, $word)) {
                    $taskType = $type;
                    break 2;
                }
            }
        }

        $risk = 'low';
        foreach (self::HIGH_RISK_WORDS as $word) {
            if (str_contains($haystack, $word)) {
                $risk = 'high';
                break;
            }
        }

        return [
            'task_type'       => $taskType,
            'title'           => Str::limit(trim(preg_replace('/\s+/', ' ', $objective) ?? $objective), 70, ''),
            'risk_level'      => $risk,
            'keywords'        => $this->keywords($objective),
            'expected_output' => $this->expectedOutput($taskType),
            'context_hints'   => $this->contextHints($objective),
            'clarifications'  => [],
        ];
    }

    /**
     * Menarik keterangan yang sudah tersurat di dalam kalimat perintah —
     * nama berkas dan alamat email. Pengguna chat menuliskannya di kalimat,
     * bukan di formulir, dan menebaknya belakangan jauh lebih berbahaya
     * daripada membacanya sejak awal.
     *
     * @return array<string, mixed>
     */
    private function contextHints(string $objective): array
    {
        $hints = [];

        preg_match_all('/[\w\-.]+\.(?:csv|tsv)\b/iu', $objective, $files);
        $datasets = array_values(array_unique($files[0] ?? []));

        if (isset($datasets[0])) {
            $hints['dataset'] = $datasets[0];
        }

        if (count($datasets) >= 2) {
            $hints['dataset_a'] = $datasets[0];
            $hints['dataset_b'] = $datasets[1];
        }

        preg_match_all('/[\w.\-+]+@[\w\-]+\.[\w.\-]+/u', $objective, $emails);

        if ($emails[0] !== []) {
            $hints['email_to'] = array_values(array_unique($emails[0]));
        }

        return $hints;
    }

    private function expectedOutput(string $taskType): string
    {
        return match ($taskType) {
            'report_generation'    => 'Dokumen laporan berisi ringkasan angka dan temuan utama.',
            'data_comparison'      => 'Tabel perbandingan beserta daftar selisih yang ditemukan.',
            'email_handling'       => 'Draf email siap kirim beserta penerima dan subjeknya.',
            'calendar_scheduling'  => 'Agenda tersimpan dengan waktu, peserta, dan judul yang jelas.',
            'document_preparation' => 'Dokumen final sesuai template yang diminta.',
            'followup'             => 'Daftar pekerjaan tertunggak beserta rencana tindak lanjutnya.',
            'research'             => 'Rangkuman temuan beserta sumber datanya.',
            default                => 'Ringkasan hasil pekerjaan beserta bukti pendukungnya.',
        };
    }

    // ── Perencanaan ───────────────────────────────────────────────────────

    /** @return array<string, mixed> */
    private function plan(LlmRequest $request): array
    {
        $meta      = $request->metadata;
        $taskType  = (string) ($meta['task_type'] ?? 'general');
        $context   = (array) ($meta['context'] ?? []);
        $available = array_values((array) ($meta['available_tools'] ?? []));

        $steps = match ($taskType) {
            'report_generation'    => $this->reportSteps($context),
            'data_comparison'      => $this->comparisonSteps($context),
            'email_handling'       => $this->emailSteps($context),
            'calendar_scheduling'  => $this->calendarSteps($context),
            'document_preparation' => $this->documentSteps($context),
            'followup'             => $this->followupSteps($context),
            default                => $this->generalSteps($context),
        };

        // Buang langkah yang toolnya tidak tersedia bagi agent ini, lalu
        // rapikan penomoran & ketergantungan antar langkah.
        $steps = array_values(array_filter(
            $steps,
            static fn (array $step) => $step['tool'] === null || in_array($step['tool'], $available, true),
        ));

        return [
            'strategy' => $this->strategyText($taskType),
            'steps'    => $this->renumber($steps),
        ];
    }

    /** @param array<string, mixed> $context */
    private function reportSteps(array $context): array
    {
        // Nama berkas tidak pernah dikarang: bila tidak disebut, tool yang
        // akan melaporkan berkas apa saja yang tersedia dan pekerjaan
        // diserahkan kepada manusia untuk memilih.
        $dataset = trim((string) ($context['dataset'] ?? ''));
        $metrics = (array) ($context['metrics'] ?? ['revenue', 'units_sold']);

        $steps = [[
            'id'        => 'step_1',
            'objective' => $dataset === ''
                ? 'Ambil data sumber penjualan'
                : "Ambil data sumber dari berkas {$dataset}",
            'tool'      => 'spreadsheet.read',
            'inputs'    => ['dataset' => $dataset, 'required_columns' => $metrics],
            'dependencies'     => [],
            'success_criteria' => ['output_not_empty', 'has:rows', 'min_rows:1'],
            'risk_level'       => 'low',
        ], [
            'id'        => 'step_2',
            'objective' => 'Hitung KPI dan kelompokkan angka penjualan',
            'tool'      => 'data.analyze',
            'inputs'    => [
                'source_step' => 'step_1',
                'group_by'    => (string) ($context['group_by'] ?? 'produk'),
                'metrics'     => $metrics,
            ],
            'dependencies'     => ['step_1'],
            'success_criteria' => ['has:totals', 'has:groups', 'numeric:totals'],
            'risk_level'       => 'low',
        ], [
            'id'        => 'step_3',
            'objective' => 'Susun dokumen laporan beserta ringkasan temuan',
            'tool'      => 'document.create',
            'inputs'    => [
                'title'       => (string) ($context['report_title'] ?? 'Laporan Penjualan'),
                'template'    => 'laporan',
                'source_step' => 'step_2',
            ],
            'dependencies'     => ['step_2'],
            'success_criteria' => ['has_file', 'contains:Total'],
            'risk_level'       => 'low',
        ]];

        if (! empty($context['email_to'])) {
            $steps[] = [
                'id'        => 'step_4',
                'objective' => 'Siapkan draf email pengantar laporan',
                'tool'      => 'email.draft',
                'inputs'    => [
                    'to'          => $context['email_to'],
                    'subject'     => (string) ($context['report_title'] ?? 'Laporan Penjualan'),
                    'source_step' => 'step_3',
                ],
                'dependencies'     => ['step_3'],
                'success_criteria' => ['has:body', 'has:to'],
                'risk_level'       => 'low',
            ];
            $steps[] = [
                'id'        => 'step_5',
                'objective' => 'Kirim laporan kepada penerima',
                'tool'      => 'email.send',
                'inputs'    => ['source_step' => 'step_4'],
                'dependencies'     => ['step_4'],
                'success_criteria' => ['has:message_id'],
                'risk_level'       => 'high',
            ];
        }

        return $steps;
    }

    /** @param array<string, mixed> $context */
    private function comparisonSteps(array $context): array
    {
        return [[
            'id'        => 'step_1',
            'objective' => 'Ambil data sumber pertama',
            'tool'      => 'spreadsheet.read',
            'inputs'    => ['dataset' => trim((string) ($context['dataset_a'] ?? $context['dataset'] ?? ''))],
            'dependencies'     => [],
            'success_criteria' => ['has:rows', 'min_rows:1'],
            'risk_level'       => 'low',
        ], [
            'id'        => 'step_2',
            'objective' => 'Ambil data sumber pembanding',
            'tool'      => 'spreadsheet.read',
            'inputs'    => ['dataset' => trim((string) ($context['dataset_b'] ?? ''))],
            'dependencies'     => [],
            'success_criteria' => ['has:rows', 'min_rows:1'],
            'risk_level'       => 'low',
        ], [
            'id'        => 'step_3',
            'objective' => 'Bandingkan kedua sumber dan catat selisihnya',
            'tool'      => 'data.compare',
            'inputs'    => [
                'left_step'  => 'step_1',
                'right_step' => 'step_2',
                'key'        => (string) ($context['key'] ?? 'id'),
                'compare'    => (array) ($context['compare'] ?? []),
            ],
            'dependencies'     => ['step_1', 'step_2'],
            'success_criteria' => ['has:differences', 'has:summary'],
            'risk_level'       => 'low',
        ], [
            'id'        => 'step_4',
            'objective' => 'Tuliskan hasil rekonsiliasi menjadi dokumen',
            'tool'      => 'document.create',
            'inputs'    => ['title' => (string) ($context['report_title'] ?? 'Hasil Rekonsiliasi'), 'template' => 'rekonsiliasi', 'source_step' => 'step_3'],
            'dependencies'     => ['step_3'],
            'success_criteria' => ['has_file'],
            'risk_level'       => 'low',
        ]];
    }

    /** @param array<string, mixed> $context */
    private function emailSteps(array $context): array
    {
        $steps = [[
            'id'        => 'step_1',
            'objective' => 'Susun draf email sesuai maksud pekerjaan',
            'tool'      => 'email.draft',
            'inputs'    => [
                'to'      => $context['email_to'] ?? null,
                'subject' => $context['subject'] ?? null,
                'points'  => (array) ($context['points'] ?? []),
            ],
            'dependencies'     => [],
            'success_criteria' => ['has:body', 'has:to'],
            'risk_level'       => 'low',
        ]];

        if (($context['send'] ?? true) !== false) {
            $steps[] = [
                'id'        => 'step_2',
                'objective' => 'Kirim email setelah disetujui',
                'tool'      => 'email.send',
                'inputs'    => ['source_step' => 'step_1'],
                'dependencies'     => ['step_1'],
                'success_criteria' => ['has:message_id'],
                'risk_level'       => 'high',
            ];
        }

        return $steps;
    }

    /** @param array<string, mixed> $context */
    private function calendarSteps(array $context): array
    {
        return [[
            'id'        => 'step_1',
            'objective' => 'Pastikan waktu acuan saat ini',
            'tool'      => 'clock.now',
            'inputs'    => [],
            'dependencies'     => [],
            'success_criteria' => ['has:iso'],
            'risk_level'       => 'low',
        ], [
            'id'        => 'step_2',
            'objective' => 'Buat agenda pada kalender',
            'tool'      => 'calendar.create_event',
            'inputs'    => [
                'title'     => $context['event_title'] ?? null,
                'starts_at' => $context['starts_at'] ?? null,
                'duration'  => (int) ($context['duration'] ?? 60),
                'attendees' => (array) ($context['attendees'] ?? []),
                'location'  => $context['location'] ?? null,
            ],
            'dependencies'     => ['step_1'],
            'success_criteria' => ['has:event_id'],
            'risk_level'       => 'high',
        ]];
    }

    /** @param array<string, mixed> $context */
    private function documentSteps(array $context): array
    {
        return [[
            'id'        => 'step_1',
            'objective' => 'Susun dokumen sesuai kebutuhan',
            'tool'      => 'document.create',
            'inputs'    => [
                'title'    => (string) ($context['document_title'] ?? 'Dokumen Kerja'),
                'template' => (string) ($context['template'] ?? 'umum'),
                'sections' => (array) ($context['sections'] ?? []),
            ],
            'dependencies'     => [],
            'success_criteria' => ['has_file'],
            'risk_level'       => 'low',
        ]];
    }

    /** @param array<string, mixed> $context */
    private function followupSteps(array $context): array
    {
        return [[
            'id'        => 'step_1',
            'objective' => 'Kumpulkan pekerjaan yang tertunggak',
            'tool'      => 'tasks.search',
            'inputs'    => [
                'status'  => (array) ($context['status'] ?? ['To Do', 'In Progress', 'Blocked']),
                'overdue' => (bool) ($context['overdue'] ?? true),
                'limit'   => 25,
            ],
            'dependencies'     => [],
            'success_criteria' => ['has:tasks'],
            'risk_level'       => 'low',
        ], [
            'id'        => 'step_2',
            'objective' => 'Rangkum daftar tunggakan dan rencana tindak lanjutnya',
            'tool'      => 'agent.note',
            'inputs'    => ['source_step' => 'step_1', 'topic' => 'Tindak lanjut pekerjaan tertunggak'],
            'dependencies'     => ['step_1'],
            'success_criteria' => ['output_not_empty'],
            'risk_level'       => 'low',
        ]];
    }

    /** @param array<string, mixed> $context */
    private function generalSteps(array $context): array
    {
        return [[
            'id'        => 'step_1',
            'objective' => 'Rangkum permintaan menjadi langkah kerja yang jelas',
            'tool'      => 'agent.note',
            'inputs'    => ['topic' => (string) ($context['topic'] ?? 'Ringkasan pekerjaan')],
            'dependencies'     => [],
            'success_criteria' => ['output_not_empty'],
            'risk_level'       => 'low',
        ]];
    }

    private function strategyText(string $taskType): string
    {
        return match ($taskType) {
            'report_generation'    => 'Ambil data mentah, hitung KPI, lalu tuangkan menjadi laporan yang dapat diperiksa ulang.',
            'data_comparison'      => 'Baca kedua sumber, sejajarkan berdasarkan kunci, lalu catat setiap selisih.',
            'email_handling'       => 'Susun draf lebih dulu, kirim hanya setelah manusia menyetujui.',
            'calendar_scheduling'  => 'Tetapkan waktu acuan, baru buat agenda agar tidak salah zona waktu.',
            'document_preparation' => 'Gunakan template yang sudah baku agar format konsisten.',
            'followup'             => 'Kumpulkan tunggakan dari sistem tugas, lalu susun rencana tindak lanjut.',
            default                => 'Pecah permintaan menjadi langkah kecil yang hasilnya dapat diperiksa.',
        };
    }

    /**
     * @param  array<int, array<string, mixed>>  $steps
     * @return array<int, array<string, mixed>>
     */
    private function renumber(array $steps): array
    {
        $map = [];
        foreach (array_values($steps) as $i => $step) {
            $map[$step['id']] = 'step_' . ($i + 1);
        }

        return array_values(array_map(static function (array $step) use ($map) {
            $step['id']           = $map[$step['id']];
            $step['dependencies'] = array_values(array_filter(array_map(
                static fn (string $dep) => $map[$dep] ?? null,
                (array) $step['dependencies'],
            )));

            foreach (['source_step', 'left_step', 'right_step'] as $ref) {
                if (isset($step['inputs'][$ref], $map[$step['inputs'][$ref]])) {
                    $step['inputs'][$ref] = $map[$step['inputs'][$ref]];
                }
            }

            return $step;
        }, $steps));
    }

    // ── Refleksi & percakapan ─────────────────────────────────────────────

    /**
     * Narasi refleksi disusun dari fakta eksekusi yang sudah dikumpulkan
     * Reflector (langkah gagal, pemulihan yang berhasil, hasil verifikasi).
     *
     * @return array<string, mixed>
     */
    private function reflect(LlmRequest $request): array
    {
        $facts     = $request->metadata;
        $outcome   = (string) ($facts['outcome'] ?? 'SUCCESS');
        $objective = (string) ($facts['objective'] ?? 'pekerjaan');
        $tools     = implode(' → ', (array) ($facts['tools'] ?? []));
        $failures  = (array) ($facts['failed_steps'] ?? []);
        $recovered = (array) ($facts['recoveries'] ?? []);

        $summary = match ($outcome) {
            'SUCCESS' => "Pekerjaan \"{$objective}\" selesai" . ($tools ? " melalui rangkaian {$tools}." : '.'),
            'PARTIAL' => "Pekerjaan \"{$objective}\" selesai sebagian; sebagian pemeriksaan belum terpenuhi.",
            default   => "Pekerjaan \"{$objective}\" gagal diselesaikan.",
        };

        if ($recovered !== []) {
            $summary .= ' Hambatan sempat muncul dan berhasil dipulihkan: ' . implode('; ', $recovered) . '.';
        } elseif ($failures !== []) {
            $summary .= ' Hambatan yang belum teratasi: ' . implode('; ', $failures) . '.';
        }

        return [
            'summary'           => $summary,
            'reusable_strategy' => (string) ($facts['strategy'] ?? ''),
            'notes'             => [],
        ];
    }

    private function reply(LlmRequest $request): string
    {
        $meta = $request->metadata;

        if (isset($meta['reply'])) {
            return (string) $meta['reply'];
        }

        return 'Baik, saya catat: ' . Str::limit($request->lastUserMessage(), 200);
    }

    /** @return array<int, string> */
    private function keywords(string $text): array
    {
        $stop = ['yang', 'untuk', 'dari', 'dan', 'dengan', 'pada', 'agar', 'saya', 'kamu', 'tolong', 'mohon', 'ini', 'itu', 'ke', 'di', 'the', 'a', 'to', 'for'];

        $words = preg_split('/[^\p{L}\p{N}_]+/u', Str::lower($text), -1, PREG_SPLIT_NO_EMPTY) ?: [];

        return array_values(array_unique(array_filter(
            $words,
            static fn (string $w) => mb_strlen($w) > 2 && ! in_array($w, $stop, true),
        )));
    }
}
