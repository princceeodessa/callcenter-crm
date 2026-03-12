<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Models\PipelineStage;
use App\Models\User;
use App\Services\BitrixLeadImportService;
use Illuminate\Http\Request;

class BitrixImportController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();

        $stages = PipelineStage::query()
            ->where('account_id', $user->account_id)
            ->orderBy('sort')
            ->get();

        $users = User::query()
            ->where('account_id', $user->account_id)
            ->where('is_active', true)
            ->orderBy('name')
            ->get();

        return view('settings.imports.bitrix', [
            'stages' => $stages,
            'users' => $users,
        ]);
    }

    public function import(Request $request, BitrixLeadImportService $service)
    {
        $data = $request->validate([
            'file' => ['required', 'file', 'max:20480'],
            'default_stage_id' => ['required', 'integer', 'exists:pipeline_stages,id'],
            'default_responsible_user_id' => ['required', 'integer', 'exists:users,id'],
        ]);

        $user = $request->user();

        $stage = PipelineStage::query()
            ->where('account_id', $user->account_id)
            ->findOrFail((int) $data['default_stage_id']);

        $responsible = User::query()
            ->where('account_id', $user->account_id)
            ->where('is_active', true)
            ->findOrFail((int) $data['default_responsible_user_id']);

        try {
            $result = $service->importFromUploadedFile(
                $request->file('file'),
                (int) $user->account_id,
                (int) $user->id,
                (int) $stage->id,
                (int) $responsible->id
            );
        } catch (\Throwable $e) {
            return back()
                ->withInput()
                ->withErrors(['file' => 'Не удалось импортировать лиды: '.$e->getMessage()]);
        }

        $status = sprintf(
            'Импорт завершён: создано %d, дублей пропущено %d, пустых строк %d, всего обработано %d.',
            $result['imported'],
            $result['duplicates'],
            $result['blank_rows'],
            $result['total']
        );

        if ($result['matched_stages'] > 0) {
            $status .= sprintf(' Стадия по статусу Bitrix определена у %d лидов.', $result['matched_stages']);
        }

        if ($result['matched_responsibles'] > 0) {
            $status .= sprintf(' Ответственный из файла сопоставлен у %d лидов.', $result['matched_responsibles']);
        }

        $redirect = redirect()
            ->route('settings.imports.bitrix.index')
            ->with('status', $status);

        if ($result['failed'] > 0) {
            $details = implode(' ', $result['failed_messages']);
            $redirect->withErrors([
                'file' => 'Не удалось импортировать строк: '.$result['failed'].'. '.$details,
            ]);
        }

        return $redirect;
    }
}