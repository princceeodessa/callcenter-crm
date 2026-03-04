<?php

namespace App\Http\Controllers;

use App\Jobs\TranscribeCallRecordingJob;
use App\Models\CallRecording;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class CallRecordingController extends Controller
{
    public function transcribe(Request $request, CallRecording $recording)
    {
        $user = Auth::user();
        abort_unless($recording->account_id === $user->account_id, 403);

        if (!$recording->recording_url) {
            return back()->withErrors(['transcribe' => 'Нет ссылки на запись звонка']);
        }

        if ($recording->transcript_status === 'processing') {
            return back()->with('status', 'Расшифровка уже выполняется');
        }

        $recording->transcript_status = 'queued';
        $recording->transcript_error = null;
        $recording->save();

        TranscribeCallRecordingJob::dispatch($recording->id);

        return back()->with('status', 'Расшифровка поставлена в очередь');
    }
}
