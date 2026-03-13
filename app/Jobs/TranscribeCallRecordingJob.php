<?php

namespace App\Jobs;

use App\Models\CallRecording;
use App\Services\Transcription\TranscriptionManager;
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

    public int $timeout;

    public function __construct(public int $callRecordingId)
    {
        $this->timeout = (int) config('transcription.whisper_cli.timeout_seconds', 300) + 120;

        $this->onConnection((string) config('transcription.queue.connection', config('queue.default', 'sync')));
        $this->onQueue((string) config('transcription.queue.name', 'transcription'));
    }

    public function handle(TranscriptionManager $transcriber): void
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
                $rec->transcript_error = (string) ($result['error'] ?? 'Unknown error');
                $rec->save();
                return;
            }

            $rec->transcript_status = 'done';
            $rec->transcript_text = (string) ($result['text'] ?? '');
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
        if ($rec->local_path && Storage::disk('local')->exists($rec->local_path)) {
            return Storage::disk('local')->path($rec->local_path);
        }

        if (!$rec->recording_url) {
            throw new \RuntimeException('No recording_url to download');
        }

        $callidSafe = preg_replace('/[^A-Za-z0-9_-]+/', '_', $rec->callid);
        $fileName = $callidSafe ?: Str::random(16);

        $resp = Http::timeout(120)
            ->withOptions(['verify' => false])
            ->get($rec->recording_url);

        if (!$resp->successful()) {
            throw new \RuntimeException('Failed to download recording: HTTP '.$resp->status());
        }

        $contentType = strtolower(trim(strtok((string) $resp->header('Content-Type'), ';') ?: ''));
        $urlPath = (string) parse_url($rec->recording_url, PHP_URL_PATH);
        $urlExtension = strtolower(pathinfo($urlPath, PATHINFO_EXTENSION));

        $extension = match ($contentType) {
            'audio/wav', 'audio/x-wav', 'audio/wave' => 'wav',
            'audio/ogg', 'application/ogg', 'audio/opus' => 'ogg',
            'audio/mp4', 'audio/x-m4a', 'audio/aac' => 'm4a',
            'audio/webm' => 'webm',
            default => in_array($urlExtension, ['mp3', 'wav', 'ogg', 'opus', 'm4a', 'aac', 'webm'], true)
                ? $urlExtension
                : 'mp3',
        };

        $relative = 'recordings/'.$fileName.'.'.$extension;

        Storage::disk('local')->put($relative, $resp->body());
        $rec->local_path = $relative;
        $rec->save();

        return Storage::disk('local')->path($rec->local_path);
    }
}
