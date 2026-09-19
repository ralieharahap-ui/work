<?php

namespace App\Http\Controllers;

use App\Models\Document;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class DocumentCommentController extends Controller
{
    /**
     * Komentar reviewer atas sebuah dokumen. Terbuka untuk Reviewer ke atas
     * (reviewer / approval / super_admin); pembuat dokumen tetap bisa membacanya
     * di halaman detail dan rekapnya muncul di Dokumentasi.
     */
    public function store(Request $request, Document $document): RedirectResponse
    {
        abort_unless($document->organization_id === auth()->user()->organization_id, 403);
        abort_unless(
            auth()->user()->hasAnyRole(['reviewer', 'approval', 'super_admin']),
            403,
            'Hanya Reviewer ke atas yang dapat memberi komentar.'
        );

        $data = $request->validate(['body' => 'required|string|max:2000']);

        $document->comments()->create([
            'user_id' => auth()->id(),
            'kind'    => 'comment',
            'body'    => $data['body'],
        ]);

        return back()->with('success', 'Komentar terkirim.');
    }
}
