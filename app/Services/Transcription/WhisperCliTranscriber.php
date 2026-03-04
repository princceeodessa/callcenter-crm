<?php

namespace App\Services\Transcription;

use Symfony\Component\Process\Process;

class WhisperCliTranscriber
{
    /**
     * Runs local whisper CLI (python + transcribe.py) and returns the transcript text.
     *
     * Expected script output: JSON with key "text".
     */
    public function transcribe(string $audioPath): array
    {
        $python = config('transcription.whisper_cli.python');
        $script = config('transcription.whisper_cli.script');
        $model = config('transcription.whisper_cli.model', 'small');
        $language = config('transcription.whisper_cli.language', 'ru');
        $timeout = (int) config('transcription.whisper_cli.timeout_seconds', 300);

        $process = new Process([
            $python,
            $script,
            $audioPath,
            '--model', (string) $model,
            '--language', (string) $language,
        ]);

        $process->setTimeout($timeout);
        $process->run();

        if (!$process->isSuccessful()) {
            return [
                'ok' => false,
                'text' => null,
                'raw' => $process->getOutput(),
                'error' => trim($process->getErrorOutput()) ?: 'Whisper process failed',
            ];
        }

        $out = trim($process->getOutput());
        $json = json_decode($out, true);

        if (!is_array($json)) {
            // If script returned plain text
            return [
                'ok' => true,
                'text' => $out,
                'raw' => $out,
                'error' => null,
            ];
        }

        $text = $json['text'] ?? ($json['transcript'] ?? null);
        if (!is_string($text)) {
            $text = null;
        }

        return [
            'ok' => true,
            'text' => $text,
            'raw' => $json,
            'error' => null,
        ];
    }
}
