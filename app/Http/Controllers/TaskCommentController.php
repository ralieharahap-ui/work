<?php

namespace App\Http\Controllers;

use App\Models\Task;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class TaskCommentController extends Controller
{
    /** Interaksi antar user: setiap anggota organisasi dapat berkomentar di sebuah task. */
    public function store(Request $request, Task $task): RedirectResponse
    {
        abort_if($task->organization_id !== auth()->user()->organization_id, 403);

        $data = $request->validate(['body' => 'required|string|max:2000']);

        $task->comments()->create([
            'user_id' => auth()->id(),
            'body'    => $data['body'],
        ]);

        return back()->with('success', 'Komentar terkirim.');
    }
}
