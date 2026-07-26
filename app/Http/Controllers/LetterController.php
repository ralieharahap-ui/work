<?php

namespace App\Http\Controllers;

use App\Models\Letter;
use App\Models\LetterApproval;
use App\Models\Division;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class LetterController extends Controller
{
    public function index(): Response
    {
        $letters = Letter::with(['creator', 'division'])
            ->where('organization_id', auth()->user()->organization_id)
            ->when(request('status'), fn($q, $s) => $q->where('status', $s))
            ->when(request('direction'), fn($q, $d) => $q->where('direction', $d))
            ->latest()
            ->paginate(20);

        return Inertia::render('Letters/Index', compact('letters'));
    }

    public function create(): Response
    {
        return Inertia::render('Letters/Form', [
            'divisions' => Division::where('organization_id', auth()->user()->organization_id)->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'division_id'   => 'required|uuid|exists:divisions,id',
            'type'          => 'required|in:official,memo,decree',
            'direction'     => 'required|in:internal,external',
            'subject'       => 'required|string|max:255',
            'body'          => 'required|string',
            'to_recipient'  => 'nullable|string|max:255',
        ]);

        $prefix = now()->format('Y/m');
        $count  = Letter::where('organization_id', auth()->user()->organization_id)
            ->whereMonth('created_at', now()->month)->count() + 1;

        Letter::create([
            ...$data,
            'organization_id' => auth()->user()->organization_id,
            'created_by'      => auth()->id(),
            'letter_no'       => "GEP/{$prefix}/" . str_pad($count, 3, '0', STR_PAD_LEFT),
            'status'          => 'draft',
        ]);

        return redirect()->route('letters.index')->with('success', 'Surat berhasil dibuat.');
    }

    public function show(Letter $letter): Response
    {
        return Inertia::render('Letters/Show', [
            'letter' => $letter->load(['creator', 'approver', 'division', 'approvals.user']),
        ]);
    }

    public function update(Request $request, Letter $letter): RedirectResponse
    {
        abort_if($letter->status !== 'draft', 403, 'Surat tidak dapat diedit.');
        $letter->update($request->validate([
            'subject'      => 'required|string|max:255',
            'body'         => 'required|string',
            'to_recipient' => 'nullable|string|max:255',
        ]));
        return back()->with('success', 'Surat diperbarui.');
    }

    public function submit(Letter $letter): RedirectResponse
    {
        abort_if($letter->status !== 'draft', 403);
        abort_if($letter->created_by !== auth()->id(), 403);

        DB::transaction(function () use ($letter) {
            $letter->update(['status' => 'review']);
            LetterApproval::create(['letter_id' => $letter->id, 'user_id' => auth()->id(), 'action' => 'submitted', 'acted_at' => now()]);
        });

        return back()->with('success', 'Surat dikirim untuk review.');
    }

    public function approve(Request $request, Letter $letter): RedirectResponse
    {
        abort_unless(auth()->user()->canApprove('letters'), 403);
        DB::transaction(function () use ($request, $letter) {
            $letter->update(['status' => 'approved', 'approved_by' => auth()->id(), 'approved_at' => now()]);
            LetterApproval::create(['letter_id' => $letter->id, 'user_id' => auth()->id(), 'action' => 'approved', 'notes' => $request->notes, 'acted_at' => now()]);
        });
        return back()->with('success', 'Surat disetujui.');
    }

    public function reject(Request $request, Letter $letter): RedirectResponse
    {
        abort_unless(auth()->user()->canApprove('letters'), 403);
        $request->validate(['notes' => 'required|string']);
        DB::transaction(function () use ($request, $letter) {
            $letter->update(['status' => 'draft']);
            LetterApproval::create(['letter_id' => $letter->id, 'user_id' => auth()->id(), 'action' => 'rejected', 'notes' => $request->notes, 'acted_at' => now()]);
        });
        return back()->with('success', 'Surat dikembalikan ke drafter.');
    }

    public function destroy(Letter $letter): RedirectResponse
    {
        abort_if($letter->status !== 'draft', 403, 'Hanya surat draft yang dapat dihapus.');
        $letter->delete();
        return redirect()->route('letters.index')->with('success', 'Surat dihapus.');
    }
}
