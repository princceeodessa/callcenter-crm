<?php

namespace App\Services\Ceiling;

use App\Models\CeilingProject;
use RuntimeException;
use Symfony\Component\Process\Process;

class CeilingSketchRecognitionService
{
    public function recognize(CeilingProject $project, string $imagePath): array
    {
        if (!is_file($imagePath)) {
            throw new RuntimeException('Файл эскиза не найден.');
        }

        $pythonBinary = base_path('.ceiling-ocr-venv/Scripts/python.exe');
        $scriptPath = base_path('scripts/recognize_ceiling_sketch.py');

        if (!is_file($pythonBinary)) {
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
}
