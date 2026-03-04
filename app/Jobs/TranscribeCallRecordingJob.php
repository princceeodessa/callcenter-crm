<?php

namespace App\Jobs;

use App\Models\CallRecording;
use App\Services\Transcription\WhisperCliTranscriber;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class TranscribeCallRecordingJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(public int $callRecordingId)
    {
        $this->onQueue('transcription');
    }

    public function handle(WhisperCliTranscriber $transcriber): void
    {
        $rec = CallRecording::query()->find($this->callRecordingId);
        if (!$rec) {
            return;
        }

        if ($rec->transcript_status === 'done') {
            return;
        }

        $rec->transcript_status = 'processing';
        $rec->transcript_error = null;
        $rec->save();

        try {
            $localPath = $this->ensureLocalFile($rec);

            $result = $transcriber->transcribe($localPath);

            if (!($result['ok'] ?? false)) {
                $rec->transcript_status = 'failed';
                $rec->transcript_error = (string)($result['error'] ?? 'Unknown error');
                $rec->save();
                return;
            }

            $rec->transcript_status = 'done';
            $rec->transcript_text = (string)($result['text'] ?? '');
            $rec->transcribed_at = now();
            $rec->save();
        } catch (\Throwable $e) {
            $rec->transcript_status = 'failed';
            $rec->transcript_error = $e->getMessage();
            $rec->save();
        }
    }

    private function ensureLocalFile(CallRecording $rec): string
    {
        // If already downloaded and file exists
        if ($rec->local_path && Storage::disk('local')->exists($rec->local_path)) {
            return Storage::disk('local')->path($rec->local_path);
        }

        if (!$rec->recording_url) {
            throw new \RuntimeException('No recording_url to download');
        }

        $callidSafe = preg_replace('/[^A-Za-z0-9_-]+/', '_', $rec->callid);
        $fileName = $callidSafe ?: Str::random(16);
        $relative = 'recordings/'.$fileName.'.mp3';

        $resp = Http::timeout(120)
            ->withOptions(['verify' => false])
            ->get($rec->recording_url);

        if (!$resp->successful()) {
            throw new \RuntimeException('Failed to download recording: HTTP '.$resp->status());
        }

        Storage::disk('local')->put($relative, $resp->body());
        $rec->local_path = $relative;
        $rec->save();

        return Storage::disk('local')->path($relative);
    }
}
