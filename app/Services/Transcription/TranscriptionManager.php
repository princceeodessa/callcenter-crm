<?php

namespace App\Services\Transcription;

class TranscriptionManager
{
    public function __construct(
        private readonly WhisperCliTranscriber $whisperCli,
    ) {
    }

    public function transcribe(string $audioPath): array
    {
        $driver = (string) config('transcription.driver', 'whisper_cli');

        return match ($driver) {
            'whisper_cli' => $this->whisperCli->transcribe($audioPath),
            'none' => [
                'ok' => false,
                'text' => null,
                'raw' => null,
                'error' => 'Transcription driver is disabled (TRANSCRIPTION_DRIVER=none)',
            ],
            default => [
                'ok' => false,
                'text' => null,
                'raw' => null,
                'error' => 'Unknown transcription driver: '.$driver,
            ],
        };
    }
}
