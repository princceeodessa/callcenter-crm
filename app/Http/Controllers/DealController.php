<?php

namespace App\Http\Controllers;

use App\Models\Deal;
use App\Models\Contact;
use App\Models\PipelineStage;
use App\Models\DealActivity;
use App\Models\DealStageHistory;
use App\Models\CallRecording;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class DealController extends Controller
{
    public function index(Request $request)
    {
        $user = Auth::user();
        $q = $request->string('q')->toString();
        $status = $request->string('status')->toString(); // open|closed|all

        if (!in_array($status, ['open','closed','all'], true)) {
            $status = 'open';
        }

        $deals = Deal::query()
            ->with(['contact','stage','responsible','conversations' => fn($q) => $q->orderByDesc('last_message_at')])
            ->where('account_id', $user->account_id)
            ->when($status === 'open', fn($qq) => $qq->whereNull('closed_at'))
            ->when($status === 'closed', fn($qq) => $qq->whereNotNull('closed_at'))
            ->when($q, function ($query) use ($q) {
                // IMPORTANT: wrap OR conditions to avoid leaking rows from other accounts.
                $query->where(function ($qq) use ($q) {
                    $qq->where('title', 'like', "%{$q}%")
                        ->orWhereHas('contact', fn($c) => $c->where('phone','like',"%{$q}%")
                            ->orWhere('name','like',"%{$q}%"));
                });
            })
            ->orderByDesc('id')
            ->paginate(25)
            ->withQueryString();

        return view('deals.index', compact('deals','q','status'));
    }

    public function kanban(Request $request)
    {
        $user = Auth::user();
        $showSpam = $request->boolean('show_spam');

        $stageQuery = PipelineStage::query()
            ->where('account_id', $user->account_id)
            ->orderBy('sort');

        if (!$showSpam) {
            $stageQuery->whereRaw('LOWER(name) NOT LIKE ?', ['%спам%'])
                ->whereRaw('LOWER(name) NOT LIKE ?', ['%spam%']);
        }

        $stages = $stageQuery->get();
        $hiddenStageIds = [];
        if (!$showSpam) {
            $hiddenStageIds = PipelineStage::query()
                ->where('account_id', $user->account_id)
                ->where(function ($q) {
                    $q->whereRaw('LOWER(name) LIKE ?', ['%спам%'])
                        ->orWhereRaw('LOWER(name) LIKE ?', ['%spam%']);
                })
                ->pluck('id')
                ->all();
        }

        $dealQuery = Deal::query()
            ->with(['contact','responsible','conversations' => fn($q) => $q->orderByDesc('last_message_at')])
            ->where('account_id', $user->account_id)
            ->whereNull('closed_at')
            ->orderByDesc('updated_at');

        if (!empty($hiddenStageIds)) {
            $dealQuery->whereNotIn('stage_id', $hiddenStageIds);
        }

        $dealsByStage = $dealQuery->get()->groupBy('stage_id');

        return view('deals.kanban', compact('stages','dealsByStage','showSpam'));
    }

    public function create()
    {
        $user = Auth::user();
        $stages = PipelineStage::query()
            ->where('account_id', $user->account_id)
            ->orderBy('sort')
            ->get();

        $users = User::query()
            ->where('account_id', $user->account_id)
            ->where('is_active', 1)
            ->orderBy('name')
            ->get();

        return view('deals.create', compact('stages','users'));
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'title' => ['required','string','max:255'],
            'responsible_user_id' => ['required','integer','exists:users,id'],
            'amount' => ['required','numeric','min:0.01'],
            'contact_name' => ['nullable','string','max:255'],
            'contact_phone' => ['nullable','string','max:32'],
            'stage_id' => ['required','exists:pipeline_stages,id'],
        ]);

        $user = Auth::user();

        $contact = null;
        if (!empty($data['contact_name']) || !empty($data['contact_phone'])) {
            $contact = Contact::firstOrCreate(
                ['account_id' => $user->account_id, 'phone' => $data['contact_phone']],
                ['name' => $data['contact_name']]
            );
        }

        $stage = PipelineStage::query()
            ->where('account_id', $user->account_id)
            ->findOrFail($data['stage_id']);

        $responsible = User::query()
            ->where('account_id', $user->account_id)
            ->where('is_active', 1)
            ->findOrFail((int)$data['responsible_user_id']);

        $deal = Deal::create([
            'account_id' => $user->account_id,
            'pipeline_id' => $stage->pipeline_id,
            'stage_id' => $stage->id,
            'title' => $data['title'],
            'title_is_custom' => 1,
            'contact_id' => $contact?->id,
            'responsible_user_id' => $responsible->id,
            'amount' => $data['amount'],
            'currency' => 'RUB',
        ]);

        DealActivity::create([
            'account_id' => $user->account_id,
            'deal_id' => $deal->id,
            'author_user_id' => $user->id,
            'type' => 'system',
            'body' => 'Сделка создана вручную',
        ]);

        DealStageHistory::create([
            'account_id' => $user->account_id,
            'deal_id' => $deal->id,
            'from_stage_id' => null,
            'to_stage_id' => $stage->id,
            'changed_by_user_id' => $user->id,
            'changed_at' => now(),
        ]);

        return redirect()->route('deals.show', $deal);
    }

    public function update(Request $request, Deal $deal)
    {
        $user = Auth::user();
        abort_unless($deal->account_id === $user->account_id, 403);

        $data = $request->validate([
            'title' => ['required','string','max:255'],
            'responsible_user_id' => ['required','integer','exists:users,id'],
            'amount' => ['required','numeric','min:0.01'],
        ]);

        $responsible = User::query()
            ->where('account_id', $user->account_id)
            ->where('is_active', 1)
            ->findOrFail((int)$data['responsible_user_id']);

        $old = [
            'title' => $deal->title,
            'responsible_user_id' => $deal->responsible_user_id,
            'amount' => $deal->amount,
        ];

        $deal->title = $data['title'];
        $deal->title_is_custom = 1;
        $deal->responsible_user_id = $responsible->id;
        $deal->amount = $data['amount'];
        $deal->save();

        DealActivity::create([
            'account_id' => $user->account_id,
            'deal_id' => $deal->id,
            'author_user_id' => $user->id,
            'type' => 'deal_updated',
            'body' => 'Данные сделки обновлены',
            'payload' => [
                'before' => $old,
                'after' => [
                    'title' => $deal->title,
                    'responsible_user_id' => $deal->responsible_user_id,
                    'amount' => $deal->amount,
                ],
            ],
        ]);

        return back()->with('status', 'Сделка обновлена');
    }

    public function show(Deal $deal)
    {
        $user = Auth::user();
        abort_unless($deal->account_id === $user->account_id, 403);

        $deal->load([
            'contact',
            'stage',
            'responsible',
            'tasks' => fn($q) => $q->with('assignedTo')->orderBy('status')->orderBy('due_at'),
            'activities' => fn($q) => $q->orderByDesc('id'),
            'conversations' => fn($q) => $q->with('lastMessage')->orderByDesc('last_message_at'),
            'callRecordings' => fn($q) => $q->orderByDesc('id'),
        ]);
        $stages = PipelineStage::query()
            ->where('account_id', $user->account_id)
            ->orderBy('sort')
            ->get();

        $users = User::query()
            ->where('account_id', $user->account_id)
            ->where('is_active', 1)
            ->orderBy('name')
            ->get();

        // If we have call activities with recording_url but no call_recordings row yet, create it.
        foreach ($deal->activities as $a) {
            $payload = is_array($a->payload ?? null) ? $a->payload : null;
            if (!$payload) continue;
            $callid = $payload['callid'] ?? null;
            $recUrl = $payload['recording_url'] ?? null;
            if (!is_string($callid) || $callid === '') continue;
            if (!is_string($recUrl) || $recUrl === '') continue;

            CallRecording::query()->firstOrCreate(
                ['account_id' => $deal->account_id, 'callid' => $callid],
                ['deal_id' => $deal->id, 'recording_url' => $recUrl]
            );
        }

        $deal->load(['callRecordings' => fn($q) => $q->orderByDesc('id')]);

        $recordingsByCallid = $deal->callRecordings->keyBy('callid');

        try {
            $primaryConversation = $deal->primaryConversation();
        } catch (\Throwable) {
            $primaryConversation = null;
        }

        $dealLeadDisplayName = trim((string) ($deal->contact?->name ?? ''));
        if ($dealLeadDisplayName === '') {
            $dealLeadDisplayName = trim((string) ($primaryConversation?->lead_name ?? ''));
        }
        $dealSourceLabel = trim((string) ($primaryConversation?->source_label ?? 'CRM')) ?: 'CRM';
        $dealSourceBadgeClass = trim((string) ($primaryConversation?->source_badge_class ?? 'source-badge source-badge-default')) ?: 'source-badge source-badge-default';
        $dealSourceIconHtml = (string) ($primaryConversation?->source_icon_html ?? '<span class="source-icon source-icon-default"><i class="bi bi-chat-dots-fill"></i></span>');
        $dealSourceChatUrl = $primaryConversation?->chat_url;
        $dealTitle = $deal->title_is_custom ? $deal->title : ($dealLeadDisplayName !== '' ? $dealLeadDisplayName : $deal->title);

        $dealConversations = $deal->conversations->map(function ($conversation) {
            try {
                return [
                    'url' => route('chats.show', $conversation),
                    'surface_class' => $conversation->source_surface_class,
                    'badge_class' => $conversation->source_badge_class,
                    'source_label' => $conversation->source_label,
                    'source_icon_html' => $conversation->source_icon_html,
                    'chat_url' => $conversation->chat_url,
                    'lead_name' => $conversation->lead_name ?: 'Диалог',
                    'subtitle' => $conversation->display_subtitle,
                    'body' => \Illuminate\Support\Str::limit($conversation->lastMessage?->body ?? '—', 80),
                    'last_message_at' => $conversation->last_message_at?->format('d.m H:i') ?? '',
                    'unread_count' => (int) ($conversation->unread_count ?? 0),
                ];
            } catch (\Throwable) {
                return [
                    'url' => route('chats.show', $conversation),
                    'surface_class' => 'source-surface source-surface-default',
                    'badge_class' => 'source-badge source-badge-default',
                    'source_label' => 'CRM',
                    'source_icon_html' => '<span class="source-icon source-icon-default"><i class="bi bi-chat-dots-fill"></i></span>',
                    'chat_url' => null,
                    'lead_name' => 'Диалог',
                    'subtitle' => 'Источник: CRM',
                    'body' => '—',
                    'last_message_at' => '',
                    'unread_count' => 0,
                ];
            }
        });

        return view('deals.show', compact(
            'deal',
            'stages',
            'recordingsByCallid',
            'users',
            'dealLeadDisplayName',
            'dealSourceLabel',
            'dealSourceBadgeClass',
            'dealSourceIconHtml',
            'dealSourceChatUrl',
            'dealTitle',
            'dealConversations',
        ));
    }

    public function closed(Request $request)
    {
        $user = Auth::user();
        $q = $request->string('q')->toString();
        $result = $request->string('result')->toString(); // won|lost|all
        $month = $request->string('month')->toString(); // YYYY-MM

        if (!in_array($result, ['won','lost','all'], true)) {
            $result = 'all';
        }
        if (!preg_match('/^\d{4}-\d{2}$/', $month)) {
            $month = now()->format('Y-m');
        }
        [$y, $m] = explode('-', $month);
        $from = now()->setDate((int)$y, (int)$m, 1)->startOfDay();
        $to = (clone $from)->addMonth()->startOfDay();

        $deals = Deal::query()
            ->where('account_id', $user->account_id)
            ->whereNotNull('closed_at')
            ->whereBetween('closed_at', [$from, $to])
            ->when($result !== 'all', fn($qq) => $qq->where('closed_result', $result))
            ->when($q, function ($query) use ($q) {
                // IMPORTANT: wrap OR conditions to avoid leaking rows from other accounts.
                $query->where(function ($qq) use ($q) {
                    $qq->where('title','like',"%{$q}%")
                        ->orWhereHas('contact', fn($c) => $c->where('phone','like',"%{$q}%")
                            ->orWhere('name','like',"%{$q}%"));
                });
            })
            ->with(['contact','responsible','stage','conversations' => fn($q) => $q->orderByDesc('last_message_at')])
            ->orderByDesc('closed_at')
            ->paginate(25)
            ->withQueryString();

        return view('deals.closed', compact('deals','q','result','month'));
    }

    public function close(Request $request, Deal $deal)
    {
        $user = Auth::user();
        abort_unless($deal->account_id === $user->account_id, 403);

        $data = $request->validate([
            'result' => ['required','in:won,lost'],
            'reason' => ['nullable','string','max:255'],
        ]);

        $deal->closed_at = now();
        $deal->closed_result = $data['result'];
        $deal->closed_reason = $data['reason'] ?? null;
        $deal->closed_by_user_id = $user->id;

        // Move to final stage (if exists) to keep pipeline consistent
        $finalStage = PipelineStage::query()
            ->where('pipeline_id', $deal->pipeline_id)
            ->where('is_final', 1)
            ->orderBy('sort')
            ->first();
        if ($finalStage) {
            $deal->stage_id = $finalStage->id;
        }

        $deal->save();

        DealActivity::create([
            'account_id' => $user->account_id,
            'deal_id' => $deal->id,
            'author_user_id' => $user->id,
            'type' => 'deal_closed',
            'body' => $data['result'] === 'won'
                ? ('Сделка закрыта успешно'.($deal->closed_reason ? (': '.$deal->closed_reason) : ''))
                : ('Сделка закрыта с отказом'.($deal->closed_reason ? (': '.$deal->closed_reason) : '')),
            'payload' => [
                'closed_result' => $deal->closed_result,
                'closed_reason' => $deal->closed_reason,
            ],
        ]);

        return redirect()->route('deals.show', $deal)->with('status', 'Сделка закрыта');
    }

    public function changeStage(Request $request, Deal $deal)
    {
        $data = $request->validate([
            'stage_id' => ['required','exists:pipeline_stages,id']
        ]);

        $user = Auth::user();
        abort_unless($deal->account_id === $user->account_id, 403);

        if ($deal->closed_at) {
            return back()->withErrors(['stage_id' => 'Нельзя менять стадию у закрытой сделки']);
        }
        $to = PipelineStage::findOrFail($data['stage_id']);
        abort_unless($to->account_id === $user->account_id, 403);
        $fromId = $deal->stage_id;

        if ($to->pipeline_id !== $deal->pipeline_id) {
            abort(422, 'Stage pipeline mismatch');
        }

        $deal->stage_id = $to->id;
        $deal->save();

        DealActivity::create([
            'account_id' => $user->account_id,
            'deal_id' => $deal->id,
            'author_user_id' => $user->id,
            'type' => 'stage_changed',
            'body' => 'Стадия изменена',
            'payload' => ['from_stage_id' => $fromId, 'to_stage_id' => $to->id],
        ]);

        DealStageHistory::create([
            'account_id' => $user->account_id,
            'deal_id' => $deal->id,
            'from_stage_id' => $fromId,
            'to_stage_id' => $to->id,
            'changed_by_user_id' => $user->id,
            'changed_at' => now(),
        ]);

        return back();
    }

    /** Kanban drag&drop move. */
    public function move(Request $request, Deal $deal)
    {
        $data = $request->validate([
            'to_stage_id' => ['required', 'integer', 'exists:pipeline_stages,id'],
        ]);

        $user = Auth::user();
        if ($deal->account_id !== $user->account_id) {
            abort(403);
        }

        if ($deal->closed_at) {
            return response()->json(['ok' => false, 'message' => 'Deal is closed'], 422);
        }

        $to = PipelineStage::findOrFail($data['to_stage_id']);
        if ($to->account_id !== $user->account_id) {
            return response()->json(['ok' => false, 'message' => 'Forbidden'], 403);
        }
        if ($to->pipeline_id !== $deal->pipeline_id) {
            return response()->json(['ok' => false, 'message' => 'Stage pipeline mismatch'], 422);
        }

        $fromId = $deal->stage_id;
        if ((int)$fromId === (int)$to->id) {
            return response()->json(['ok' => true]);
        }

        $deal->stage_id = $to->id;
        $deal->save();

        DealActivity::create([
            'account_id' => $user->account_id,
            'deal_id' => $deal->id,
            'author_user_id' => $user->id,
            'type' => 'stage_changed',
            'body' => 'Стадия изменена (перетаскиванием)',
            'payload' => ['from_stage_id' => $fromId, 'to_stage_id' => $to->id],
        ]);

        DealStageHistory::create([
            'account_id' => $user->account_id,
            'deal_id' => $deal->id,
            'from_stage_id' => $fromId,
            'to_stage_id' => $to->id,
            'changed_by_user_id' => $user->id,
            'changed_at' => now(),
        ]);

        return response()->json(['ok' => true]);
    }
}