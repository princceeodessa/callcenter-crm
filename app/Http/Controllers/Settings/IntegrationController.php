<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Models\IntegrationConnection;
use App\Models\IntegrationEvent;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

class IntegrationController extends Controller
{
    private const PROVIDERS = [
        'megafon_vats' => 'МегаФон Виртуальная АТС',
        'vk' => 'ВКонтакте',
        'avito' => 'Avito',
        'telegram' => 'Telegram',
    ];

    public function index()
    {
        $accountId = Auth::user()->account_id;

        $connections = IntegrationConnection::query()
            ->where('account_id', $accountId)
            ->get()
            ->keyBy('provider');

        // Ensure all providers exist in UI
        $providers = collect(self::PROVIDERS)->map(function ($title, $provider) use ($connections, $accountId) {
            $conn = $connections->get($provider);
            if (!$conn) {
                $conn = new IntegrationConnection([
                    'account_id' => $accountId,
                    'provider' => $provider,
                    'status' => 'disabled',
                    'settings' => [],
                ]);
            }

            $lastEvent = IntegrationEvent::query()
                ->where('account_id', $accountId)
                ->where('provider', $provider)
                ->orderByDesc('received_at')
                ->first();

            return [
                'provider' => $provider,
                'title' => $title,
                'connection' => $conn,
                'last_event_at' => $lastEvent?->received_at,
            ];
        })->values();

        return view('settings.integrations.index', compact('providers'));
    }

    public function show(string $provider)
    {
        $this->assertProvider($provider);
        $accountId = Auth::user()->account_id;

        $connection = IntegrationConnection::query()
            ->where('account_id', $accountId)
            ->where('provider', $provider)
            ->first();

        $events = IntegrationEvent::query()
            ->where('account_id', $accountId)
            ->where('provider', $provider)
            ->orderByDesc('received_at')
            ->limit(100)
            ->get();

        return view('settings.integrations.show', [
            'provider' => $provider,
            'providerTitle' => self::PROVIDERS[$provider],
            'connection' => $connection,
            'events' => $events,
        ]);
    }

    public function connect(Request $request, string $provider)
    {
        $this->assertProvider($provider);
        $accountId = Auth::user()->account_id;

        $data = match ($provider) {
            'megafon_vats' => $request->validate([
                'ats_api_base_url' => ['required', 'string', 'max:255'],
                'ats_api_key' => ['required', 'string', 'max:255'],
                'crm_webhook_token' => ['nullable', 'string', 'max:255'],
            ]),
            'telegram' => $request->validate([
                'bot_token' => ['required', 'string', 'max:255'],
            ]),
            'vk' => $request->validate([
                'group_id' => ['required', 'string', 'max:50'],
                'access_token' => ['required', 'string', 'max:255'],
            ]),
            'avito' => $request->validate([
                'access_token' => ['required', 'string', 'max:255'],
            ]),
            default => [],
        };

        $connection = IntegrationConnection::query()
            ->firstOrNew(['account_id' => $accountId, 'provider' => $provider]);

        $settings = $connection->settings ?? [];

        if ($provider === 'megafon_vats') {
            $settings['ats_api_base_url'] = trim($data['ats_api_base_url']);
            $settings['ats_api_key'] = trim($data['ats_api_key']);
            $settings['crm_webhook_token'] = trim($data['crm_webhook_token'] ?? '') ?: ($settings['crm_webhook_token'] ?? Str::random(40));
        } elseif ($provider === 'telegram') {
            $settings['bot_token'] = trim($data['bot_token']);
        } elseif (in_array($provider, ['vk', 'avito'], true)) {
            foreach ($data as $k => $v) {
                $settings[$k] = trim($v);
            }
        }

        $connection->fill([
            'account_id' => $accountId,
            'provider' => $provider,
            'status' => 'active',
            'settings' => $settings,
            'last_error' => null,
        ]);

        $connection->save();

        return redirect()->route('settings.integrations.index')->with('status', 'Интеграция сохранена.');
    }

    public function disconnect(string $provider)
    {
        $this->assertProvider($provider);
        $accountId = Auth::user()->account_id;

        IntegrationConnection::query()
            ->where('account_id', $accountId)
            ->where('provider', $provider)
            ->update(['status' => 'disabled']);

        return redirect()->route('settings.integrations.index')->with('status', 'Интеграция отключена.');
    }

    private function assertProvider(string $provider): void
    {
        if (!array_key_exists($provider, self::PROVIDERS)) {
            abort(404);
        }
    }
}
