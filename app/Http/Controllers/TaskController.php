<?php

namespace App\Http\Controllers;

use App\Models\Deal;
use App\Models\Task;
use App\Models\DealActivity;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class TaskController extends Controller
{
    public function store(Request $request, Deal $deal)
    {
        $data = $request->validate([
            'title' => ['required','string','max:255'],
            'description' => ['nullable','string'],
            'due_at' => ['nullable','date'],
        ]);

        $user = Auth::user();

        $task = Task::create([
            'account_id' => $user->account_id,
            'deal_id' => $deal->id,
            'assigned_user_id' => $user->id,
            'title' => $data['title'],
            'description' => $data['description'] ?? null,
            'status' => 'open',
            'due_at' => $data['due_at'] ?? null,
        ]);

        DealActivity::create([
            'account_id' => $user->account_id,
            'deal_id' => $deal->id,
            'author_user_id' => $user->id,
            'type' => 'task_created',
            'body' => 'Создано дело: '.$task->title,
            'payload' => ['task_id' => $task->id],
        ]);

        return back();
    }

    public function complete(Request $request, Task $task)
    {
        $user = Auth::user();

        $task->status = 'done';
        $task->completed_at = now();
        $task->save();

        DealActivity::create([
            'account_id' => $user->account_id,
            'deal_id' => $task->deal_id,
            'author_user_id' => $user->id,
            'type' => 'task_done',
            'body' => 'Дело выполнено: '.$task->title,
            'payload' => ['task_id' => $task->id],
        ]);

        return back();
    }
}
