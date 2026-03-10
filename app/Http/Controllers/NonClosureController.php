<?php

namespace App\Http\Controllers;

use App\Models\NonClosure;
use App\Models\User;
use App\Services\NonClosureImportService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class NonClosureController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();
        $accountId = $user->account_id;

        $statusFilter = (string)($request->query('status', 'all'));
        $measurerId = (int)($request->query('measurer_id') ?? 0);
        $responsibleId = (int)($request->query('responsible_id') ?? 0);
        $search = trim((string)$request->query('q', ''));

        $rows = NonClosure::query()
            ->where('account_id', $accountId)
            ->with(['measurerUser:id,name', 'responsibleUser:id,name', 'createdBy:id,name']);

        if ($statusFilter === 'pending') {
            $rows->where(function ($q) {
                $q->whereNull('result_status')->orWhere('result_status', '');
            });
        } elseif (in_array($statusFilter, ['concluded', 'not_concluded'], true)) {
            $rows->where('result_status', $statusFilter);
        }

        if ($measurerId > 0) {
            $rows->where('measurer_user_id', $measurerId);
        }
        if ($responsibleId > 0) {
            $rows->where('responsible_user_id', $responsibleId);
        }
        if ($search !== '') {
            $rows->where(function ($q) use ($search) {
                $q->where('address', 'like', "%{$search}%")
                  ->orWhere('reason', 'like', "%{$search}%")
                  ->orWhere('comment', 'like', "%{$search}%")
                  ->orWhere('special_calculation', 'like', "%{$search}%")
                  ->orWhere('measurer_name', 'like', "%{$search}%")
                  ->orWhere('responsible_name', 'like', "%{$search}%");
            });
        }

        $rows = $rows
            ->orderByRaw('CASE WHEN follow_up_date IS NULL THEN 1 ELSE 0 END')
            ->orderBy('follow_up_date')
            ->orderByDesc('entry_date')
            ->orderByDesc('id')
            ->paginate(50)
            ->withQueryString();

        $measurers = User::query()
            ->where('account_id', $accountId)
            ->where('is_active', true)
            ->whereIn('role', ['measurer', 'admin', 'main_operator', 'operator'])
            ->orderBy('name')
            ->get(['id', 'name', 'role']);

        $responsibles = User::query()
            ->where('account_id', $accountId)
            ->where('is_active', true)
            ->whereIn('role', ['operator', 'main_operator', 'admin'])
            ->orderBy('name')
            ->get(['id', 'name', 'role']);

        return view('nonclosures.index', [
            'rows' => $rows,
            'statusFilter' => $statusFilter,
            'measurerId' => $measurerId,
            'responsibleId' => $responsibleId,
            'search' => $search,
            'measurers' => $measurers,
            'responsibles' => $responsibles,
            'resultStatuses' => NonClosure::RESULT_STATUSES,
        ]);
    }

    public function store(Request $request)
    {
        $data = $this->validated($request, true);
        $user = $request->user();

        NonClosure::create($this->payloadFromRequest($data, $user->account_id, (int)$user->id, null));

        return back()->with('status', 'Запись добавлена.');
    }

    public function update(Request $request, NonClosure $nonclosure)
    {
        $this->authorizeRow($request, $nonclosure);
        $data = $this->validated($request, false);
        $user = $request->user();

        $nonclosure->fill($this->payloadFromRequest($data, $user->account_id, null, (int)$user->id));
        $nonclosure->save();

        return back()->with('status', 'Запись обновлена.');
    }

    public function import(Request $request, NonClosureImportService $service)
    {
        $request->validate([
            'file' => ['required', 'file', 'mimes:xlsx'],
        ]);

        try {
            $result = $service->importFromXlsx($request->file('file'), (int)$request->user()->account_id, (int)$request->user()->id);
        } catch (\Throwable $e) {
            return back()->withErrors(['file' => 'Не удалось импортировать файл: '.$e->getMessage()]);
        }

        return redirect()->route('nonclosures.index', ['status' => 'all'])->with('status', sprintf('Импорт завершён: добавлено %d, обновлено %d, всего обработано %d.', $result['imported'], $result['updated'], $result['total']));
    }

    private function authorizeRow(Request $request, NonClosure $row): void
    {
        if ((int)$row->account_id !== (int)$request->user()->account_id) {
            abort(404);
        }
    }

    private function validated(Request $request, bool $creating): array
    {
        return $request->validate([
            'entry_date' => [$creating ? 'required' : 'nullable', 'date'],
            'address' => [$creating ? 'required' : 'nullable', 'string', 'max:500'],
            'reason' => ['nullable', 'string'],
            'measurer_user_id' => ['nullable', 'integer'],
            'measurer_name' => ['nullable', 'string', 'max:120'],
            'responsible_user_id' => ['nullable', 'integer'],
            'responsible_name' => ['nullable', 'string', 'max:120'],
            'comment' => ['nullable', 'string'],
            'follow_up_date' => ['nullable', 'date'],
            'result_status' => ['nullable', Rule::in(['concluded', 'not_concluded'])],
            'special_calculation' => ['nullable', 'string'],
        ]);
    }

    private function payloadFromRequest(array $data, int $accountId, ?int $createdBy, ?int $updatedBy): array
    {
        $payload = [
            'account_id' => $accountId,
            'entry_date' => isset($data['entry_date']) && $data['entry_date'] !== null ? Carbon::parse($data['entry_date'])->startOfDay() : null,
            'address' => trim((string)($data['address'] ?? '')),
            'reason' => $this->nullIfBlank($data['reason'] ?? null),
            'measurer_user_id' => $data['measurer_user_id'] ?: null,
            'measurer_name' => $this->nullIfBlank($data['measurer_name'] ?? null),
            'responsible_user_id' => $data['responsible_user_id'] ?: null,
            'responsible_name' => $this->nullIfBlank($data['responsible_name'] ?? null),
            'comment' => $this->nullIfBlank($data['comment'] ?? null),
            'follow_up_date' => isset($data['follow_up_date']) && $data['follow_up_date'] !== null && $data['follow_up_date'] !== '' ? Carbon::parse($data['follow_up_date'])->startOfDay() : null,
            'result_status' => $this->nullIfBlank($data['result_status'] ?? null),
            'special_calculation' => $this->nullIfBlank($data['special_calculation'] ?? null),
        ];

        $payload['unique_hash'] = sha1(implode('|', [
            $accountId,
            trim((string)($payload['entry_date']?->format('Y-m-d') ?? '')),
            mb_strtolower(trim((string)$payload['address'])),
            mb_strtolower(trim((string)($payload['measurer_name'] ?? ''))),
        ]));

        if ($createdBy !== null) {
            $payload['created_by_user_id'] = $createdBy;
        }
        if ($updatedBy !== null) {
            $payload['updated_by_user_id'] = $updatedBy;
        }

        return $payload;
    }

    private function nullIfBlank(?string $value): ?string
    {
        $value = trim((string)$value);
        return $value === '' ? null : $value;
    }
}
