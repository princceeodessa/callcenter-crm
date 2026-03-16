<?php

$defaultPython = PHP_OS_FAMILY === 'Windows'
    ? (file_exists(base_path('.whisper-venv/Scripts/python.exe'))
        ? base_path('.whisper-venv/Scripts/python.exe')
        : 'py')
    : '/opt/whisper/.venv/bin/python';

$defaultScript = file_exists(base_path('tools/transcribe.py'))
    ? base_path('tools/transcribe.py')
    : '/opt/whisper/transcribe.py';

$defaultDispatch = env('APP_ENV', 'production') === 'local'
    ? 'after_response'
    : 'queue';

return [
    // Driver: 'whisper_cli' (local whisper) or 'none'
    'driver' => env('TRANSCRIPTION_DRIVER', 'whisper_cli'),

    // Dispatch mode: 'after_response' for local setups without a queue worker,
    // 'queue' for background execution, or 'sync' to run in the request.
    'dispatch' => env('TRANSCRIPTION_DISPATCH', $defaultDispatch),

    'queue' => [
        'connection' => env('TRANSCRIPTION_QUEUE_CONNECTION', env('QUEUE_CONNECTION', 'sync')),
        'name' => env('TRANSCRIPTION_QUEUE', 'transcription'),
    ],

    'whisper_cli' => [
        'python' => env('WHISPER_PYTHON', $defaultPython),
        'script' => env('WHISPER_SCRIPT', $defaultScript),
        'model' => env('WHISPER_MODEL', 'small'),
        'model_dir' => env('WHISPER_MODEL_DIR', storage_path('app/whisper-models')),
        'language' => env('WHISPER_LANGUAGE', 'ru'),
        'device' => env('WHISPER_DEVICE', 'cpu'),
        'compute_type' => env('WHISPER_COMPUTE_TYPE', 'int8'),
        'cpu_threads' => (int) env('WHISPER_CPU_THREADS', 0),
        'num_workers' => (int) env('WHISPER_NUM_WORKERS', 1),
        'beam_size' => (int) env('WHISPER_BEAM_SIZE', 5),
        'timeout_seconds' => (int) env('WHISPER_TIMEOUT', 300),
    ],
];
