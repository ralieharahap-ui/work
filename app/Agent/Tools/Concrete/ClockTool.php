<?php

namespace App\Agent\Tools\Concrete;

use App\Agent\Data\ToolContext;
use App\Agent\Data\ToolDefinition;
use App\Agent\Data\ToolResult;
use App\Agent\Tools\BaseTool;
use Illuminate\Support\Carbon;

/** Waktu acuan — dipakai sebelum menyusun agenda agar tanggal tidak melenceng. */
class ClockTool extends BaseTool
{
    public function describe(): ToolDefinition
    {
        return new ToolDefinition(
            name: 'clock.now',
            title: 'Waktu sekarang',
            description: 'Mengembalikan tanggal & waktu server beserta zona waktunya.',
            inputSchema: ['timezone' => ['type' => 'string', 'description' => 'Zona waktu IANA, mis. Asia/Jakarta']],
            outputKeys: ['iso', 'date', 'time', 'timezone', 'day_name'],
        );
    }

    public function execute(array $input, ToolContext $context): ToolResult
    {
        $timezone = $input['timezone'] ?? config('app.timezone', 'UTC');
        $now      = Carbon::now($timezone)->locale('id');

        return ToolResult::success([
            'iso'       => $now->toIso8601String(),
            'date'      => $now->toDateString(),
            'time'      => $now->format('H:i'),
            'timezone'  => (string) $timezone,
            'day_name'  => $now->translatedFormat('l'),
        ], 'Waktu acuan: ' . $now->translatedFormat('l, d F Y H:i') . " ({$timezone}).");
    }
}
