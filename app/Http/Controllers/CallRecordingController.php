<?php

namespace App\Http\Controllers;

use App\Jobs\TranscribeCallRecordingJob;
use App\Models\CallRecording;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Throwable;

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

        $dispatchMode = (string) config('transcription.dispatch', 'after_response');

        try {
            match ($dispatchMode) {
                'sync' => TranscribeCallRecordingJob::dispatchSync($recording->id),
                'queue' => TranscribeCallRecordingJob::dispatch($recording->id),
                default => TranscribeCallRecordingJob::dispatchAfterResponse($recording->id),
            };
        } catch (Throwable $e) {
            report($e);

            $recording->transcript_status = 'failed';
            $recording->transcript_error = 'Не удалось запустить расшифровку: '.$e->getMessage();
            $recording->save();

            return back()->withErrors([
                'transcribe' => 'Не удалось запустить расшифровку: '.$e->getMessage(),
            ]);
        }

        if ($dispatchMode === 'sync') {
            $status = (string) optional($recording->fresh())->transcript_status;

            return match ($status) {
                'done' => back()->with('status', 'Расшифровка выполнена'),
                'failed' => back()->withErrors([
                    'transcribe' => optional($recording->fresh())->transcript_error ?: 'Не удалось выполнить расшифровку',
                ]),
                default => back()->with('status', 'Расшифровка запущена'),
            };
        }

        $message = $dispatchMode === 'queue'
            ? 'Расшифровка поставлена в очередь'
            : 'Расшифровка запущена и будет сохранена автоматически';

        return back()->with('status', $message);
    }
}
