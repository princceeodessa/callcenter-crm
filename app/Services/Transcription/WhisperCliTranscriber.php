<?php

namespace App\Services\Transcription;

use Symfony\Component\Process\Process;

class WhisperCliTranscriber
{
    public function transcribe(string $audioPath): array
    {
        if (!is_file($audioPath)) {
            return $this->failed('Audio file not found: '.$audioPath);
        }

        $python = config('transcription.whisper_cli.python');
        $script = config('transcription.whisper_cli.script');
        $model = config('transcription.whisper_cli.model', 'small');
        $modelDir = config('transcription.whisper_cli.model_dir');
        $language = config('transcription.whisper_cli.language', 'ru');
        $timeout = (int) config('transcription.whisper_cli.timeout_seconds', 300);

        if (!is_string($python) || trim($python) === '') {
            return $this->failed('WHISPER_PYTHON is not configured');
        }

        if (!is_string($script) || trim($script) === '') {
            return $this->failed('WHISPER_SCRIPT is not configured');
        }

        if (!is_file($script)) {
            return $this->failed('Whisper script not found: '.$script);
        }

        $command = [
            $python,
            $script,
            $audioPath,
            '--model',
            (string) $model,
            '--language',
            (string) $language,
        ];

        if (is_string($modelDir) && trim($modelDir) !== '') {
            $command[] = '--model-dir';
            $command[] = $modelDir;
        }

        $process = new Process($command, base_path());
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

    private function failed(string $error): array
    {
        return [
            'ok' => false,
            'text' => null,
            'raw' => null,
            'error' => $error,
        ];
    }
}
