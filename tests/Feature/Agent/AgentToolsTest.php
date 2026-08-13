<?php

namespace Tests\Feature\Agent;

use App\Agent\Data\ToolContext;
use App\Agent\Runtime\AgentTaskService;
use App\Agent\Tools\ToolRegistry;
use App\Models\AgentTask;
use App\Models\Task;
use InvalidArgumentException;
use Illuminate\Support\Facades\Storage;

/** Tool: validasi input, sandbox berkas, dan kegunaan nyatanya. */
class AgentToolsTest extends AgentTestCase
{
    public function test_tool_menolak_input_yang_tidak_memenuhi_skema(): void
    {
        $tool = app(ToolRegistry::class)->get('document.create');

        $this->expectException(InvalidArgumentException::class);
        $tool->validate([]); // 'title' wajib diisi
    }

    public function test_berkas_sumber_yang_tidak_disebut_tidak_pernah_ditebak(): void
    {
        $tool   = app(ToolRegistry::class)->get('spreadsheet.read');
        $result = $tool->execute($tool->validate([]), $this->context());

        $this->assertFalse($result->ok);
        $this->assertSame('missing_data', $result->errorClass);

        // Kegagalannya informatif: manusia diberi tahu pilihan yang ada.
        $this->assertContains('penjualan-2026-07.csv', $result->data['available_datasets']);
        $this->assertContains('penjualan-2026-08.csv', $result->data['available_datasets']);
    }

    public function test_pembacaan_berkas_dikurung_di_dalam_ruang_kerja(): void
    {
        Storage::disk('local')->put('rahasia.csv', "a,b\n1,2");

        $tool    = app(ToolRegistry::class)->get('spreadsheet.read');
        $context = $this->context();

        $result = $tool->execute($tool->validate(['dataset' => '../../rahasia.csv']), $context);

        $this->assertFalse($result->ok);
        $this->assertSame('validation', $result->errorClass);
    }

    public function test_penulisan_berkas_tidak_bisa_menembus_direktori_lain(): void
    {
        $tool    = app(ToolRegistry::class)->get('file.write');
        $context = $this->context();

        $result = $tool->execute(
            $tool->validate(['filename' => '../../../.env', 'content' => 'APP_KEY=bocor']),
            $context,
        );

        $this->assertFalse($result->ok);
        $this->assertFalse(Storage::disk('local')->exists('.env'));
    }

    public function test_tool_tugas_membaca_data_aplikasi_yang_sesungguhnya(): void
    {
        Task::create([
            'organization_id' => $this->organization->id,
            'title'           => 'Tagih pembayaran PLTU Bangka',
            'status'          => 'In Progress',
            'priority'        => 'High',
            'category'        => 'Operasional',
            'deadline'        => now()->subDays(3),
            'pic_id'          => $this->user->id,
            'created_by'      => $this->user->id,
        ]);

        $tool   = app(ToolRegistry::class)->get('tasks.search');
        $result = $tool->execute($tool->validate(['overdue' => true, 'limit' => 10]), $this->context());

        $this->assertTrue($result->ok);
        $this->assertSame(1, $result->output['count']);
        $this->assertSame('Tagih pembayaran PLTU Bangka', $result->output['tasks'][0]['title']);
        $this->assertTrue($result->output['tasks'][0]['overdue']);
    }

    public function test_pekerjaan_rekonsiliasi_menemukan_selisih_antar_sumber(): void
    {
        $this->dataset('piutang-sistem.csv', ['id,pelanggan,nilai', 'INV-001,PLTU A,125000000', 'INV-002,PLTU B,98000000']);
        $this->dataset('piutang-bank.csv',   ['id,pelanggan,nilai', 'INV-001,PLTU A,125000000', 'INV-002,PLTU B,95000000']);

        $task = app(AgentTaskService::class)->create($this->user, 'Bandingkan piutang sistem dengan mutasi bank', [
            'context' => [
                'dataset_a' => 'piutang-sistem.csv',
                'dataset_b' => 'piutang-bank.csv',
                'key'       => 'id',
                'compare'   => ['nilai'],
            ],
        ]);

        $task = $this->runToCompletion($task);

        $this->assertSame(AgentTask::COMPLETED, $task->status);
        $this->assertSame('data_comparison', $task->task_type);

        $compare = $task->steps()->where('tool', 'data.compare')->firstOrFail();
        $this->assertSame(1, $compare->output['summary']['difference_count']);
        $this->assertSame('INV-002', $compare->output['differences'][0]['key']);
        $this->assertEquals(-3000000, $compare->output['differences'][0]['delta']);

        // Hasilnya dituangkan ke dokumen yang benar-benar tersimpan.
        $this->assertNotEmpty($task->deliverables);
        $this->assertStringContainsString(
            'INV-002',
            Storage::disk('local')->get($task->deliverables[0]['path']),
        );
    }

    public function test_seluruh_tool_mendeklarasikan_dirinya_dengan_lengkap(): void
    {
        foreach (app(ToolRegistry::class)->all() as $name => $tool) {
            $definition = $tool->describe();

            $this->assertSame($name, $definition->name);
            $this->assertNotEmpty($definition->title, "Tool {$name} tanpa judul.");
            $this->assertNotEmpty($definition->description, "Tool {$name} tanpa deskripsi.");
            $this->assertContains($definition->riskLevel, ['low', 'medium', 'high']);

            // Tool yang mengubah keadaan di luar aplikasi wajib berisiko tinggi.
            if (! $definition->readOnly && $definition->integration && $definition->integration !== 'whatsapp') {
                $this->assertNotSame('low', $definition->riskLevel, "Tool {$name} berdampak keluar tetapi berisiko rendah.");
            }
        }
    }

    private function context(): ToolContext
    {
        $service = app(AgentTaskService::class);
        $task    = $service->create($this->user, 'konteks uji tool', ['idempotency_key' => 'uji-konteks-' . uniqid()]);

        return new ToolContext(task: $task, agent: $task->agent, user: $this->user);
    }
}
