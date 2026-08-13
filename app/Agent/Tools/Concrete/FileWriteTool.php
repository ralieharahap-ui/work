<?php

namespace App\Agent\Tools\Concrete;

use App\Agent\Data\ToolContext;
use App\Agent\Data\ToolDefinition;
use App\Agent\Data\ToolResult;
use App\Agent\Tools\BaseTool;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Menulis berkas teks — dikurung ketat di dalam ruang kerja task. Tidak ada
 * jalan menuju direktori lain, dan tidak ada eksekusi perintah shell.
 */
class FileWriteTool extends BaseTool
{
    private const MAX_BYTES = 2_000_000;

    public function describe(): ToolDefinition
    {
        return new ToolDefinition(
            name: 'file.write',
            title: 'Tulis berkas',
            description: 'Menyimpan teks ke berkas di dalam ruang kerja task agent.',
            inputSchema: [
                'filename' => ['type' => 'string', 'required' => true, 'description' => 'Nama berkas tanpa folder'],
                'content'  => ['type' => 'string', 'required' => true],
            ],
            outputKeys: ['path', 'bytes'],
            riskLevel: 'low',
            readOnly: false,
        );
    }

    public function execute(array $input, ToolContext $context): ToolResult
    {
        $filename = trim((string) $input['filename']);

        if ($filename === '' || Str::contains($filename, ['..', '/', '\\', "\0"])) {
            return ToolResult::failure("Nama berkas '{$filename}' tidak diizinkan.", 'validation');
        }

        $content = (string) $input['content'];

        if (strlen($content) > self::MAX_BYTES) {
            return ToolResult::failure('Isi berkas melebihi batas 2 MB.', 'validation');
        }

        $path = $context->workspacePath('files/' . $filename);
        Storage::disk('local')->put($path, $content);

        return ToolResult::success(
            ['path' => $path, 'filename' => $filename, 'bytes' => strlen($content)],
            "Berkas {$filename} tersimpan (" . strlen($content) . ' bita).',
            [['path' => $path, 'name' => $filename, 'mime' => 'text/plain']],
        );
    }
}
