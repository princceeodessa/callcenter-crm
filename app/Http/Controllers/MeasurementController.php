<?php

namespace App\Http\Controllers;

use App\Models\Measurement;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\Request;

class MeasurementController extends Controller
{
    public const STATUSES = [
        'planned' => 'Запланирован',
        'confirmed' => 'Подтверждён',
        'done' => 'Успешный замер',
        'refused_after_measurement' => 'Отказ после замера',
        'cancelled' => 'Отменён',
    ];

    private const STATUS_COLORS = [
        'planned' => ['#0d6efd', '#0d6efd'],
        'confirmed' => ['#0dcaf0', '#0dcaf0'],
        'done' => ['#198754', '#198754'],
        'refused_after_measurement' => ['#dc3545', '#dc3545'],
        'cancelled' => ['#6c757d', '#6c757d'],
    ];

    public function index(Request $request)
    {
        $accountId = $request->user()->account_id;

        $measurers = User::query()
            ->where('account_id', $accountId)
            ->where('is_active', true)
            ->whereIn('role', ['measurer', 'admin', 'main_operator', 'operator'])
            ->orderByRaw("FIELD(role,'measurer','operator','main_operator','admin')")
            ->orderBy('name')
            ->get(['id','name','role']);

        $selectedUserId = (int)($request->query('u') ?? 0);
        if ($request->user()->role === 'measurer' && $selectedUserId === 0) {
            $selectedUserId = (int)$request->user()->id;
        }

        return view('calendar.index', [
            'measurers' => $measurers,
            'selectedUserId' => $selectedUserId,
            'statuses' => self::STATUSES,
        ]);
    }

    public function events(Request $request)
    {
        $accountId = $request->user()->account_id;

        $start = $request->query('start');
        $end = $request->query('end');
        $userId = (int)($request->query('user_id') ?? 0);

        $startAt = $start ? Carbon::parse($start) : now()->startOfMonth();
        $endAt = $end ? Carbon::parse($end) : now()->endOfMonth();

        $q = Measurement::query()
            ->where('account_id', $accountId)
            ->whereBetween('scheduled_at', [$startAt, $endAt])
            ->with(['assignedUser:id,name']);

        if ($userId > 0) {
            $q->where('assigned_user_id', $userId);
        }

        $items = $q->orderBy('scheduled_at')->get();

        $events = $items->map(function (Measurement $m) {
            $end = $m->scheduled_at?->copy()->addMinutes(max(5, (int)$m->duration_minutes)) ?? null;
            $mainTitle = $m->phone ?: ('Замер #'.$m->id);
            if ($m->assignedUser?->name) {
                $mainTitle = $m->assignedUser->name.': '.$mainTitle;
            }
            [$bg, $border] = self::STATUS_COLORS[$m->status] ?? ['#0d6efd', '#0d6efd'];

            return [
                'id' => $m->id,
                'title' => $mainTitle,
                'start' => optional($m->scheduled_at)->toIso8601String(),
                'end' => $end?->toIso8601String(),
                'backgroundColor' => $bg,
                'borderColor' => $border,
                'extendedProps' => [
                    'address' => $m->address,
                    'phone' => $m->phone,
                    'status' => $m->status,
                    'status_label' => self::STATUSES[$m->status] ?? $m->status,
                    'duration_minutes' => (int)$m->duration_minutes,
                    'assigned_user_id' => $m->assigned_user_id,
                    'assigned_user_name' => $m->assignedUser?->name,
                    'callcenter_comment' => $m->callcenter_comment,
                    'measurer_comment' => $m->measurer_comment,
                ],
            ];
        });

        return response()->json($events);
    }

    public function store(Request $request)
    {
        $accountId = $request->user()->account_id;
        $role = (string)$request->user()->role;

        $data = $request->validate([
            'scheduled_at' => ['required', 'date'],
            'duration_minutes' => ['nullable', 'integer', 'min:5', 'max:600'],
            'address' => ['required', 'string', 'max:500'],
            'phone' => ['nullable', 'string', 'max:32'],
            'status' => ['nullable', 'string', 'in:planned,confirmed,done,refused_after_measurement,cancelled'],
            'assigned_user_id' => ['nullable', 'integer'],
            'callcenter_comment' => ['nullable', 'string'],
            'measurer_comment' => ['nullable', 'string'],
        ]);

        $assigned = $data['assigned_user_id'] ?? null;
        if ($role === 'measurer') {
            $assigned = (int)$request->user()->id;
        }

        $m = Measurement::create([
            'account_id' => $accountId,
            'scheduled_at' => Carbon::parse($data['scheduled_at']),
            'duration_minutes' => (int)($data['duration_minutes'] ?? 60),
            'address' => trim((string)$data['address']),
            'phone' => isset($data['phone']) ? trim((string)$data['phone']) : null,
            'status' => trim((string)($data['status'] ?? 'planned')),
            'assigned_user_id' => $assigned ?: null,
            'created_by_user_id' => (int)$request->user()->id,
            'callcenter_comment' => $data['callcenter_comment'] ?? null,
            'measurer_comment' => $data['measurer_comment'] ?? null,
        ]);

        return response()->json(['ok' => true, 'id' => $m->id]);
    }

    public function update(Request $request, Measurement $measurement)
    {
        $this->authorizeMeasurement($request, $measurement);
        $role = (string)$request->user()->role;

        $data = $request->validate([
            'scheduled_at' => ['nullable', 'date'],
            'duration_minutes' => ['nullable', 'integer', 'min:5', 'max:600'],
            'address' => ['nullable', 'string', 'max:500'],
            'phone' => ['nullable', 'string', 'max:32'],
            'status' => ['nullable', 'string', 'in:planned,confirmed,done,refused_after_measurement,cancelled'],
            'assigned_user_id' => ['nullable', 'integer'],
            'callcenter_comment' => ['nullable', 'string'],
            'measurer_comment' => ['nullable', 'string'],
        ]);

        if ($role === 'measurer') {
            $allowed = array_intersect_key($data, array_flip(['status', 'measurer_comment', 'assigned_user_id']));
            if (array_key_exists('assigned_user_id', $allowed)) {
                $allowed['assigned_user_id'] = (int)$request->user()->id;
            }
            $data = $allowed;
        }

        if (isset($data['scheduled_at'])) {
            $data['scheduled_at'] = Carbon::parse($data['scheduled_at']);
        }
        foreach (['address', 'phone', 'status'] as $field) {
            if (isset($data[$field])) {
                $data[$field] = trim((string)$data[$field]);
            }
        }

        $measurement->update($data);

        return response()->json(['ok' => true]);
    }

    public function claim(Request $request, Measurement $measurement)
    {
        $this->authorizeMeasurement($request, $measurement);

        if ($request->user()->role !== 'measurer') {
            abort(403);
        }

        $measurement->update([
            'assigned_user_id' => (int)$request->user()->id,
        ]);

        return response()->json(['ok' => true]);
    }

    private function authorizeMeasurement(Request $request, Measurement $measurement): void
    {
        if ($measurement->account_id !== $request->user()->account_id) {
            abort(404);
        }

        if ($request->user()->role === 'measurer') {
            if ($measurement->assigned_user_id !== null && (int)$measurement->assigned_user_id !== (int)$request->user()->id) {
                abort(403);
            }
        }
    }
}
