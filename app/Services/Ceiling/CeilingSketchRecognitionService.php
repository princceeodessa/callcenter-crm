<?php

namespace App\Services\Ceiling;

use App\Models\CeilingProject;
use RuntimeException;
use Symfony\Component\Process\ExecutableFinder;
use Symfony\Component\Process\Process;

class CeilingSketchRecognitionService
{
    public function recognize(CeilingProject $project, string $imagePath): array
    {
        if (!is_file($imagePath)) {
            throw new RuntimeException('Файл эскиза не найден.');
        }

        $pythonBinary = $this->resolvePythonBinary();
        $scriptPath = base_path('scripts/recognize_ceiling_sketch.py');

        if (false && !is_file($pythonBinary)) {
            throw new RuntimeException('OCR-окружение не найдено: отсутствует python.exe в .ceiling-ocr-venv.');
        }

        if (!is_file($scriptPath)) {
            throw new RuntimeException('Скрипт распознавания не найден.');
        }

        $process = new Process([
            $pythonBinary,
            $scriptPath,
            '--image',
            $imagePath,
            '--project-id',
            (string) $project->id,
        ], base_path());

        $process->setTimeout(180);
        $process->run();

        $payload = json_decode($process->getOutput(), true);

        if (!$process->isSuccessful()) {
            if (is_array($payload) && isset($payload['message'])) {
                throw new RuntimeException(trim((string) $payload['message']));
            }

            throw new RuntimeException(trim($process->getErrorOutput()) ?: 'Не удалось распознать эскиз.');
        }

        if (!is_array($payload)) {
            throw new RuntimeException('Скрипт распознавания вернул некорректный ответ.');
        }

        if (!($payload['success'] ?? false)) {
            $message = trim((string) ($payload['message'] ?? 'Не удалось распознать эскиз.'));
            throw new RuntimeException($message !== '' ? $message : 'Не удалось распознать эскиз.');
        }

        return $payload;
    }

    private function resolvePythonBinary(): string
    {
        $configured = trim((string) env('CEILING_OCR_PYTHON', ''));
        $candidates = array_filter([
            $configured !== '' ? $configured : null,
            base_path('.ceiling-ocr-venv/Scripts/python.exe'),
            base_path('.ceiling-ocr-venv/bin/python'),
        ]);

        foreach ($candidates as $candidate) {
            if (is_string($candidate) && $candidate !== '' && is_file($candidate)) {
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

        throw new RuntimeException('Не найден Python для OCR. Укажите CEILING_OCR_PYTHON или установите python/python3 и пакет rapidocr-onnxruntime.');
    }
}
