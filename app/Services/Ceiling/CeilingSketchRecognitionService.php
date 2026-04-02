<?php

namespace App\Services\Ceiling;

use App\Models\CeilingProject;
use RuntimeException;
use Symfony\Component\Process\ExecutableFinder;
use Symfony\Component\Process\Process;

class CeilingSketchRecognitionService
{
    public function inspect(CeilingProject $project, string $imagePath): array
    {
        return $this->runScript($project, $imagePath, 'inspect');
    }

    public function recognize(CeilingProject $project, string $imagePath, ?array $crop = null): array
    {
        $payload = $this->runScript($project, $imagePath, 'recognize', $crop);

        if (!($payload['success'] ?? false)) {
            $message = trim((string) ($payload['message'] ?? 'Не удалось распознать эскиз.'));
            throw new RuntimeException($message !== '' ? $message : 'Не удалось распознать эскиз.');
        }

        return $payload;
    }

    private function runScript(CeilingProject $project, string $imagePath, string $mode, ?array $crop = null): array
    {
        if (!is_file($imagePath)) {
            throw new RuntimeException('Файл эскиза не найден.');
        }

        $pythonBinary = $this->resolvePythonBinary();
        $scriptPath = base_path('scripts/recognize_ceiling_sketch.py');

        if (!is_file($scriptPath)) {
            throw new RuntimeException('Скрипт распознавания не найден.');
        }

        $command = [
            $pythonBinary,
            $scriptPath,
            '--image',
            $imagePath,
            '--mode',
            $mode,
            '--project-id',
            (string) $project->id,
        ];

        if (is_array($crop) && $crop !== []) {
            $command[] = '--crop';
            $command[] = json_encode($crop, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        }

        $process = new Process($command, base_path(), $this->pythonProcessEnvironment());
        $process->setTimeout(180);
        $process->run();

        $payload = json_decode($process->getOutput(), true);

        if (!$process->isSuccessful()) {
            if (is_array($payload) && isset($payload['message'])) {
                throw new RuntimeException(trim((string) $payload['message']));
            }

            throw new RuntimeException(trim($process->getErrorOutput()) ?: 'Не удалось выполнить OCR.');
        }

        if (!is_array($payload)) {
            throw new RuntimeException('Скрипт распознавания вернул некорректный ответ.');
        }

        return $payload;
    }

    /**
     * На некоторых серверах Python падает ещё до запуска скрипта,
     * если не может получить служебные байты для hash randomization.
     * Фиксированный seed убирает эту проблему.
     *
     * @return array<string, string>
     */
    private function pythonProcessEnvironment(): array
    {
        return [
            'PYTHONHASHSEED' => trim((string) env('CEILING_OCR_PYTHONHASHSEED', '0')) ?: '0',
            'PYTHONUTF8' => '1',
            'PYTHONIOENCODING' => 'UTF-8',
            'PYTHONDONTWRITEBYTECODE' => '1',
        ];
    }

    private function resolvePythonBinary(): string
    {
        $configured = trim((string) env('CEILING_OCR_PYTHON', ''));
        $candidates = array_filter([
            $configured !== '' ? $configured : null,
            base_path('.ceiling-ocr-venv/bin/python'),
            base_path('.ceiling-ocr-venv/Scripts/python.exe'),
        ]);

        foreach ($candidates as $candidate) {
            if (is_string($candidate) && $candidate !== '' && $this->isUsablePythonBinary($candidate)) {
                return $candidate;
            }
        }

        $finder = new ExecutableFinder();

        foreach (['python', 'python3', 'py'] as $name) {
            $found = $finder->find($name);

            if (is_string($found) && $found !== '') {
                return $found;
            }
        }

        throw new RuntimeException('Не найден Python для OCR. Укажите CEILING_OCR_PYTHON или установите python/python3 с пакетом rapidocr-onnxruntime.');
    }

    private function isUsablePythonBinary(string $candidate): bool
    {
        if (!is_file($candidate)) {
            return false;
        }

        if (PHP_OS_FAMILY === 'Windows') {
            return true;
        }

        if (str_ends_with(strtolower($candidate), '.exe')) {
            return false;
        }

        return is_executable($candidate);
    }
}
