<?php

namespace App\Services\Transcription;

use Symfony\Component\Process\ExecutableFinder;
use Symfony\Component\Process\Process;

class WhisperCliTranscriber
{
    public function transcribe(string $audioPath): array
    {
        if (!is_file($audioPath)) {
            return $this->failed('Audio file not found: '.$audioPath);
        }

        $python = $this->resolvePythonExecutable(config('transcription.whisper_cli.python'));
        $script = $this->resolveScriptFile(config('transcription.whisper_cli.script'));
        $model = config('transcription.whisper_cli.model', 'small');
        $modelDir = config('transcription.whisper_cli.model_dir');
        $language = config('transcription.whisper_cli.language', 'ru');
        $timeout = (int) config('transcription.whisper_cli.timeout_seconds', 300);

        if ($python === null) {
            return $this->failed(
                'Whisper Python executable not found. Checked WHISPER_PYTHON, local .whisper-venv, and PATH commands: py, python, python3'
            );
        }

        if ($script === null) {
            return $this->failed(
                'Whisper script not found. Checked WHISPER_SCRIPT and '.base_path('tools/transcribe.py')
            );
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
                'raw' => [
                    'stdout' => $process->getOutput(),
                    'stderr' => $process->getErrorOutput(),
                    'command' => $process->getCommandLine(),
                ],
                'error' => $this->extractProcessError($process),
            ];
        }

        $out = trim($process->getOutput());
        $json = $this->decodeJsonPayload($out);

        if ($json === null) {
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

    private function resolvePythonExecutable(mixed $configured): ?string
    {
        $finder = new ExecutableFinder();
        $candidates = [];

        if (is_string($configured) && trim($configured) !== '') {
            $candidates[] = trim($configured);
        }

        $candidates[] = base_path('.whisper-venv/Scripts/python.exe');
        $candidates[] = base_path('.whisper-venv/bin/python');
        $candidates[] = 'py';
        $candidates[] = 'python';
        $candidates[] = 'python3';

        foreach (array_values(array_unique($candidates)) as $candidate) {
            $resolved = $this->resolveExecutableCandidate($candidate, $finder);
            if ($resolved !== null) {
                return $resolved;
            }
        }

        return null;
    }

    private function resolveScriptFile(mixed $configured): ?string
    {
        $candidates = [];

        if (is_string($configured) && trim($configured) !== '') {
            $candidates[] = trim($configured);
        }

        $candidates[] = base_path('tools/transcribe.py');

        foreach (array_values(array_unique($candidates)) as $candidate) {
            $resolved = $this->resolveFileCandidate($candidate);
            if ($resolved !== null) {
                return $resolved;
            }
        }

        return null;
    }

    private function resolveExecutableCandidate(string $candidate, ExecutableFinder $finder): ?string
    {
        $file = $this->resolveFileCandidate($candidate);
        if ($file !== null) {
            return $file;
        }

        if ($this->looksLikePath($candidate)) {
            return null;
        }

        return $finder->find($candidate);
    }

    private function resolveFileCandidate(string $candidate): ?string
    {
        $candidate = trim($candidate);
        if ($candidate === '') {
            return null;
        }

        if (is_file($candidate)) {
            return realpath($candidate) ?: $candidate;
        }

        if ($this->isAbsolutePath($candidate)) {
            return null;
        }

        $relativeToBase = base_path($candidate);

        if (is_file($relativeToBase)) {
            return realpath($relativeToBase) ?: $relativeToBase;
        }

        return null;
    }

    private function extractProcessError(Process $process): string
    {
        foreach ([trim($process->getErrorOutput()), trim($process->getOutput())] as $payload) {
            $json = $this->decodeJsonPayload($payload);
            if ($json === null) {
                continue;
            }

            $message = $json['error'] ?? $json['message'] ?? null;
            if (is_string($message) && trim($message) !== '') {
                return trim($message);
            }
        }

        return trim($process->getErrorOutput())
            ?: trim($process->getOutput())
            ?: 'Whisper process failed';
    }

    private function decodeJsonPayload(string $payload): ?array
    {
        $payload = trim($payload);
        if ($payload === '') {
            return null;
        }

        $decoded = json_decode($payload, true);

        return is_array($decoded) ? $decoded : null;
    }

    private function looksLikePath(string $candidate): bool
    {
        return str_contains($candidate, '\\')
            || str_contains($candidate, '/')
            || str_contains($candidate, ':');
    }

    private function isAbsolutePath(string $path): bool
    {
        return preg_match('/^(?:[A-Za-z]:[\\\\\/]|[\\\\\/]{2}|[\\\\\/])/', $path) === 1;
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
