<?php

return [
    // Driver: 'whisper_cli' (local whisper) or 'none'
    'driver' => env('TRANSCRIPTION_DRIVER', 'whisper_cli'),

    'whisper_cli' => [
        // Example:
        //  WHISPER_PYTHON=/opt/whisper/.venv/bin/python
        //  WHISPER_SCRIPT=/opt/whisper/transcribe.py
        //  WHISPER_MODEL=small
        //  WHISPER_LANGUAGE=ru
        'python' => env('WHISPER_PYTHON', '/opt/whisper/.venv/bin/python'),
        'script' => env('WHISPER_SCRIPT', '/opt/whisper/transcribe.py'),
        'model' => env('WHISPER_MODEL', 'small'),
        'language' => env('WHISPER_LANGUAGE', 'ru'),
        'timeout_seconds' => (int) env('WHISPER_TIMEOUT', 300),
    ],
];
