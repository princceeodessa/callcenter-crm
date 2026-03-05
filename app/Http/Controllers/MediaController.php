<?php

namespace App\Http\Controllers;

use App\Models\Conversation;
use App\Models\IntegrationConnection;
use App\Services\Integrations\TelegramApiClient;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;

class MediaController extends Controller
{
    /**
     * Proxy Telegram files without exposing bot token.
     * Caches the file in storage/app/public/telegram/.
     */
    public function telegram(Request $request, Conversation $conversation, string $fileId)
    {
        if ($conversation->account_id !== $request->user()->account_id) {
            abort(404);
        }

        $conn = IntegrationConnection::query()
            ->where('account_id', $conversation->account_id)
            ->where('provider', 'telegram')
            ->whereIn('status', ['active','error'])
            ->first();

        $token = trim((string)($conn?->settings['bot_token'] ?? ''));
        if ($token === '') {
            abort(404);
        }

        $tg = new TelegramApiClient($token);

        // Cache key (per account)
        $hash = sha1($conversation->account_id.'|'.$fileId);
        $baseDir = 'telegram/'.$conversation->account_id;

        // If cached file exists (any extension), return it.
        foreach (['jpg','jpeg','png','webp','mp4','mov','mkv','pdf','bin'] as $ext) {
            $rel = $baseDir.'/'.$hash.'.'.$ext;
            if (Storage::disk('public')->exists($rel)) {
                return response()->file(Storage::disk('public')->path($rel));
            }
        }

        $info = $tg->getFile($fileId);
        $filePath = data_get($info, 'result.file_path');
        if (!is_string($filePath) || $filePath === '') {
            abort(404);
        }

        $downloadUrl = $tg->fileUrl($filePath);
        $bin = Http::timeout(30)->get($downloadUrl);
        if (!$bin->successful()) {
            abort(502);
        }

        $ext = pathinfo($filePath, PATHINFO_EXTENSION);
        $ext = $ext !== '' ? strtolower($ext) : 'bin';
        $rel = $baseDir.'/'.$hash.'.'.$ext;
        Storage::disk('public')->put($rel, $bin->body());

        return response()->file(Storage::disk('public')->path($rel));
    }
}
