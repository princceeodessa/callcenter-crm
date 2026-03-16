<?php

namespace Tests\Unit;

use App\Services\Transcription\WhisperCliTranscriber;
use Illuminate\Contracts\Console\Kernel;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;

class WhisperCliTranscriberTest extends TestCase
{
    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();

        $app = require dirname(__DIR__, 2).'/bootstrap/app.php';
        $app->make(Kernel::class)->bootstrap();
    }

    public function test_it_falls_back_to_local_whisper_python_when_config_points_to_linux_path(): void
    {
        $service = new WhisperCliTranscriber();

        $resolved = $this->invokePrivate($service, 'resolvePythonExecutable', '/opt/whisper/.venv/bin/python');

        $expected = realpath(base_path('.whisper-venv/Scripts/python.exe')) ?: base_path('.whisper-venv/Scripts/python.exe');

        $this->assertSame($expected, $resolved);
    }

    public function test_it_falls_back_to_local_transcribe_script_when_config_points_to_linux_path(): void
    {
        $service = new WhisperCliTranscriber();

        $resolved = $this->invokePrivate($service, 'resolveScriptFile', '/opt/whisper/transcribe.py');

        $expected = realpath(base_path('tools/transcribe.py')) ?: base_path('tools/transcribe.py');

        $this->assertSame($expected, $resolved);
    }

    private function invokePrivate(object $subject, string $method, mixed ...$args): mixed
    {
        $reflection = new ReflectionMethod($subject, $method);
        $reflection->setAccessible(true);

        return $reflection->invoke($subject, ...$args);
    }
}
