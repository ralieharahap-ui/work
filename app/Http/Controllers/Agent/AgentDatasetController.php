<?php

namespace App\Http\Controllers\Agent;

use App\Agent\Events\EventRecorder;
use App\Agent\Events\EventType;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Berkas data yang boleh dibaca agent (CSV/TSV).
 *
 * Disimpan di ruang kerja privat aplikasi, bukan di direktori publik: berkas
 * ini kerap memuat angka penjualan dan data pelanggan, jadi tidak boleh dapat
 * diunduh siapa pun yang menebak URL-nya.
 */
class AgentDatasetController extends Controller
{
    private const MAX_KB = 5120;

    public function store(Request $request, EventRecorder $events): RedirectResponse
    {
        $request->validate([
            'file' => ['required', 'file', 'max:' . self::MAX_KB, 'mimes:csv,txt,tsv'],
        ], [], ['file' => 'berkas data']);

        $file = $request->file('file');

        // Nama berkas dibersihkan total: hanya slug + ekstensi, sehingga tidak
        // ada jalan menulis ke luar folder data.
        $extension = Str::lower($file->getClientOriginalExtension() ?: 'csv');
        $extension = in_array($extension, ['csv', 'tsv', 'txt'], true) ? $extension : 'csv';
        $name      = Str::slug(pathinfo((string) $file->getClientOriginalName(), PATHINFO_FILENAME)) ?: 'data';
        $filename  = Str::limit($name, 60, '') . '.' . $extension;

        Storage::disk('local')->putFileAs($this->folder(), $file, $filename);

        $events->record(null, EventType::MESSAGE_RECEIVED, "Berkas data '{$filename}' diunggah.", [
            'filename' => $filename,
            'bytes'    => $file->getSize(),
        ], null, $request->user()->id, (string) $request->user()->organization_id);

        return back()->with('success',
            "Berkas {$filename} siap dipakai. Sebut namanya pada instruksi, mis. \"buat laporan dari berkas {$filename}\".");
    }

    public function destroy(Request $request, string $filename): RedirectResponse
    {
        abort_unless($request->user()->hasAnyRole(['super_admin', 'approval', 'reviewer']), 403);

        // Hanya nama berkas polos yang diterima; tidak ada penelusuran folder.
        abort_if(Str::contains($filename, ['/', '\\', '..', "\0"]), 400);

        $path = $this->folder() . '/' . $filename;

        abort_unless(Storage::disk('local')->exists($path), 404);

        Storage::disk('local')->delete($path);

        return back()->with('success', "Berkas {$filename} dihapus.");
    }

    /** @return array<int, array<string, mixed>> */
    public static function catalog(): array
    {
        $disk   = Storage::disk('local');
        $folder = trim((string) config('agent.workspace', 'agent'), '/') . '/datasets';

        return collect($disk->files($folder))
            ->map(fn (string $path) => [
                'name'        => basename($path),
                'bytes'       => $disk->size($path),
                'modified_at' => date(DATE_ATOM, $disk->lastModified($path)),
            ])
            ->sortBy('name')
            ->values()
            ->all();
    }

    private function folder(): string
    {
        return trim((string) config('agent.workspace', 'agent'), '/') . '/datasets';
    }
}
