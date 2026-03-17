<?php

namespace App\Http\Controllers;

use App\Models\Deal;
use App\Models\Contact;
use App\Models\PipelineStage;
use App\Models\DealActivity;
use App\Models\DealStageHistory;
use App\Models\CallRecording;
use App\Models\User;
use App\Services\Tasks\TaskWorkflowService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class DealController extends Controller
{
    public function index(Request $request)
    {
        $user = Auth::user();
        $q = $request->string('q')->toString();
        $status = $request->string('status')->toString(); // open|closed|all
        $source = trim($request->string('source')->toString());
        $sourceOptions = Deal::sourceFilterOptions();

        if (!in_array($status, ['open','closed','all'], true)) {
            $status = 'open';
        }
        if ($source !== '' && !array_key_exists($source, $sourceOptions)) {
            $source = '';
        }

        $dealsQuery = Deal::query()
            ->withClientAttentionMetrics()
            ->with(['contact','stage','responsible','conversations' => fn($q) => $q->orderByDesc('last_message_at')])
            ->withCount([
                'callRecordings as phone_call_recordings_count',
                'activities as phone_call_activities_count' => fn($q) => $q->where('type', 'call'),
                'activities as tilda_lead_form_activities_count' => fn($q) => $q
                    ->where('type', 'lead_form')
                    ->where('payload->provider', 'tilda'),
            ])
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
            });

        if ($status === 'closed') {
            $dealsQuery
                ->orderByDesc('closed_at')
                ->orderByDesc('id');
        } else {
            if ($status === 'all') {
                $dealsQuery->orderByRaw('closed_at IS NOT NULL');
            }

            $dealsQuery->orderByClientAttention();
        }

        if ($source !== '') {
            Deal::applySourceFilter($dealsQuery, $source);
        }

        $deals = $dealsQuery
            ->paginate(25)
            ->withQueryString();

        return view('deals.index', compact('deals','q','status','source','sourceOptions'));
    }

    public function kanban(Request $request)
    {
        $user = Auth::user();
        $canSeeKanbanIds = $user->role === 'admin';
        $showSpam = $request->boolean('show_spam');
        $q = trim($request->string('q')->toString());
        $focusDate = $this->resolveKanbanFocusDate($request->string('focus_date')->toString());
        $nonTargetPatterns = $this->nonTargetStagePatterns();

        $stageQuery = PipelineStage::query()
            ->where('account_id', $user->account_id)
            ->orderBy('sort');

        if (!$showSpam) {
            $stageQuery->where(function ($query) use ($nonTargetPatterns) {
                foreach ($nonTargetPatterns as $pattern) {
                    $query->whereRaw('LOWER(name) NOT LIKE ?', [$pattern]);
                }
            });
        }

        $stages = $stageQuery->get();
        $hiddenStageIds = [];
        if (!$showSpam) {
            $hiddenStageIds = PipelineStage::query()
                ->where('account_id', $user->account_id)
                ->where(function ($q) use ($nonTargetPatterns) {
                    foreach ($nonTargetPatterns as $index => $pattern) {
                        if ($index === 0) {
                            $q->whereRaw('LOWER(name) LIKE ?', [$pattern]);
                        } else {
                            $q->orWhereRaw('LOWER(name) LIKE ?', [$pattern]);
                        }
                    }
                })
                ->pluck('id')
                ->all();
        }

        $dealQuery = Deal::query()
            ->withClientAttentionMetrics()
            ->with([
                'contact',
                'responsible',
                'latestStageHistory.changedBy:id,name',
                'latestCallActivity',
                'conversations' => fn($q) => $q->orderByDesc('last_message_at'),
            ])
            ->withCount([
                'callRecordings as phone_call_recordings_count',
                'activities as phone_call_activities_count' => fn ($query) => $query->where('type', 'call'),
                'activities as tilda_lead_form_activities_count' => fn ($query) => $query->where('type', 'lead_form')->where('payload->provider', 'tilda'),
            ])
            ->where('account_id', $user->account_id)
            ->whereNull('closed_at')
            ->when($q !== '', function ($query) use ($q, $canSeeKanbanIds) {
                $query->where(function ($qq) use ($q, $canSeeKanbanIds) {
                    $qq->where('title', 'like', "%{$q}%")
                        ->orWhereHas('contact', fn($c) => $c->where('phone', 'like', "%{$q}%")
                            ->orWhere('name', 'like', "%{$q}%"))
                        ->orWhereHas('responsible', fn($u) => $u->where('name', 'like', "%{$q}%"))
                        ->orWhereHas('conversations', fn($c) => $c->where('external_id', 'like', "%{$q}%"));

                    if ($canSeeKanbanIds && ctype_digit($q)) {
                        $qq->orWhere('id', (int) $q);
                    }
                });
            })
            ->orderByClientAttention();

        if (!empty($hiddenStageIds)) {
            $dealQuery->whereNotIn('stage_id', $hiddenStageIds);
        }

        $dealsByStage = $dealQuery->get()->groupBy('stage_id');
        $dateFilteredStageIds = $stages
            ->filter(fn ($stage) => $this->isDateFilteredStage($stage))
            ->pluck('id')
            ->all();

        if ($focusDate !== null && !empty($dateFilteredStageIds)) {
            $dayStart = $focusDate->copy()->startOfDay();
            $dayEnd = $focusDate->copy()->endOfDay();

            $dayStageIdsByStage = DealStageHistory::query()
                ->where('account_id', $user->account_id)
                ->whereIn('to_stage_id', $dateFilteredStageIds)
                ->whereBetween('changed_at', [$dayStart, $dayEnd])
                ->get(['deal_id', 'to_stage_id'])
                ->groupBy('to_stage_id')
                ->map(fn ($rows) => $rows->pluck('deal_id')->map(fn ($id) => (int) $id)->all());

            foreach ($dateFilteredStageIds as $stageId) {
                $dayDealIds = $dayStageIdsByStage[$stageId] ?? [];
                $dealsByStage[$stageId] = ($dealsByStage[$stageId] ?? collect())
                    ->filter(function ($deal) use ($dayDealIds, $dayStart) {
                        $createdOnDay = $deal->created_at && $deal->created_at->isSameDay($dayStart);

                        return $createdOnDay || in_array((int) $deal->id, $dayDealIds, true);
                    })
                    ->values();
            }
        }

        return view('deals.kanban', compact('stages','dealsByStage','showSpam','q', 'dateFilteredStageIds', 'focusDate', 'canSeeKanbanIds'));
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

    public function store(Request $request, TaskWorkflowService $taskWorkflow)
    {
        $data = $request->validate([
            'title' => ['required','string','max:255'],
            'responsible_user_id' => ['required','integer','exists:users,id'],
            'amount' => ['required','numeric','min:0.01'],
            'contact_name' => ['nullable','string','max:255'],
            'contact_phone' => ['nullable','string','max:32'],
            'stage_id' => ['required','exists:pipeline_stages,id'],
            'deal_date' => ['required', 'date_format:Y-m-d'],
            'comment' => ['nullable','string','max:5000'],
            'create_task' => ['nullable', 'boolean'],
            'task_title' => ['nullable', 'string', 'max:255', 'required_if:create_task,1'],
            'task_description' => ['nullable', 'string'],
            'task_due_at' => ['nullable', 'date', 'required_if:create_task,1'],
            'task_assigned_user_id' => ['nullable', 'integer'],
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

        $createTask = $request->boolean('create_task');
        $taskAssigneeId = $data['task_assigned_user_id'] ?? $responsible->id;
        if ($createTask) {
            $taskWorkflow->resolveAssigneeId($user->account_id, $taskAssigneeId, 'task_assigned_user_id');
        }

        $dealCreatedAt = Carbon::createFromFormat('Y-m-d', $data['deal_date'], config('app.timezone'))
            ->setTimeFrom(now(config('app.timezone')));

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

        Deal::query()
            ->whereKey($deal->id)
            ->update([
                'created_at' => $dealCreatedAt,
                'updated_at' => $dealCreatedAt,
            ]);
        $deal->forceFill([
            'created_at' => $dealCreatedAt,
            'updated_at' => $dealCreatedAt,
        ]);

        $systemActivity = DealActivity::create([
            'account_id' => $user->account_id,
            'deal_id' => $deal->id,
            'author_user_id' => $user->id,
            'type' => 'system',
            'body' => $this->dealCreatedActivityBody(),
        ]);
        DealActivity::query()
            ->whereKey($systemActivity->id)
            ->update([
                'created_at' => $dealCreatedAt,
                'updated_at' => $dealCreatedAt,
            ]);

        $comment = trim((string) ($data['comment'] ?? ''));
        if ($comment !== '') {
            $commentActivity = DealActivity::create([
                'account_id' => $user->account_id,
                'deal_id' => $deal->id,
                'author_user_id' => $user->id,
                'type' => 'comment',
                'body' => $comment,
            ]);
            DealActivity::query()
                ->whereKey($commentActivity->id)
                ->update([
                    'created_at' => $dealCreatedAt,
                    'updated_at' => $dealCreatedAt,
                ]);
        }

        if ($createTask) {
            $taskWorkflow->createTask($deal, [
                'title' => $data['task_title'],
                'description' => $data['task_description'] ?? null,
                'due_at' => $data['task_due_at'],
                'assigned_user_id' => $taskAssigneeId,
                'created_at' => $dealCreatedAt,
            ], $user);
        }

        DealStageHistory::create([
            'account_id' => $user->account_id,
            'deal_id' => $deal->id,
            'from_stage_id' => null,
            'to_stage_id' => $stage->id,
            'changed_by_user_id' => $user->id,
            'changed_at' => $dealCreatedAt,
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
            'body' => $this->dealUpdatedActivityBody(),
            'payload' => [
                'before' => $old,
                'after' => [
                    'title' => $deal->title,
                    'responsible_user_id' => $deal->responsible_user_id,
                    'amount' => $deal->amount,
                ],
            ],
        ]);

        return back()->with('status', $this->dealUpdatedStatusMessage());
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
            'activities' => fn($q) => $q->with('author')->orderByDesc('id'),
            'conversations' => fn($q) => $q->with('lastMessage')->orderByDesc('last_message_at'),
            'callRecordings' => fn($q) => $q->orderByDesc('id'),
        ]);

        $conversationUnreadCount = (int) $deal->conversations->sum(fn ($conversation) => (int) ($conversation->unread_count ?? 0));
        if ($deal->is_unread && $conversationUnreadCount === 0) {
            $deal->update(['is_unread' => false]);
            $deal->forceFill(['is_unread' => false]);
        }

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
        $dealSourceLabel = $deal->lead_source_label;
        $dealSourceBadgeClass = $deal->lead_source_badge_class;
        $dealSourceIconHtml = $deal->lead_source_icon_html;
        $dealSourceChatUrl = $deal->lead_source_chat_url;
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
                    'lead_name' => $conversation->lead_name ?: $this->unknownLeadLabel(),
                    'subtitle' => $conversation->display_subtitle,
                    'body' => \Illuminate\Support\Str::limit($conversation->lastMessage?->body ?? $this->conversationUnavailableBody(), 80),
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
                    'lead_name' => $this->unknownLeadLabel(),
                    'subtitle' => $this->crmConversationSubtitle(),
                    'body' => $this->conversationUnavailableBody(),
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
            ->withCount([
                'callRecordings as phone_call_recordings_count',
                'activities as phone_call_activities_count' => fn($q) => $q->where('type', 'call'),
                'activities as tilda_lead_form_activities_count' => fn($q) => $q
                    ->where('type', 'lead_form')
                    ->where('payload->provider', 'tilda'),
            ])
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
            'body' => $this->dealClosedActivityBody($deal->closed_result, $deal->closed_reason),
            'payload' => [
                'closed_result' => $deal->closed_result,
                'closed_reason' => $deal->closed_reason,
            ],
        ]);

        return redirect()->route('deals.show', $deal)->with('status', $this->dealClosedStatusMessage());
    }

    public function changeStage(Request $request, Deal $deal)
    {
        $data = $request->validate([
            'stage_id' => ['required','exists:pipeline_stages,id']
        ]);

        $user = Auth::user();
        abort_unless($deal->account_id === $user->account_id, 403);

        if ($deal->closed_at) {
            return back()->withErrors(['stage_id' => $this->closedDealStageChangeError()]);
        }
        $to = PipelineStage::findOrFail($data['stage_id']);
        abort_unless($to->account_id === $user->account_id, 403);
        $fromId = $deal->stage_id;

        if ($to->pipeline_id !== $deal->pipeline_id) {
            abort(422, "\u{042D}\u{0442}\u{0430}\u{043F} \u{043E}\u{0442}\u{043D}\u{043E}\u{0441}\u{0438}\u{0442}\u{0441}\u{044F} \u{043A} \u{0434}\u{0440}\u{0443}\u{0433}\u{043E}\u{0439} \u{0432}\u{043E}\u{0440}\u{043E}\u{043D}\u{043A}\u{0435}");
        }

        $deal->stage_id = $to->id;
        $deal->save();

        DealActivity::create([
            'account_id' => $user->account_id,
            'deal_id' => $deal->id,
            'author_user_id' => $user->id,
            'type' => 'stage_changed',
            'body' => $this->stageChangedActivityBody(),
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
            return response()->json(['ok' => false, 'message' => "\u{0421}\u{0434}\u{0435}\u{043B}\u{043A}\u{0430} \u{0443}\u{0436}\u{0435} \u{0437}\u{0430}\u{043A}\u{0440}\u{044B}\u{0442}\u{0430}"], 422);
        }

        $to = PipelineStage::findOrFail($data['to_stage_id']);
        if ($to->account_id !== $user->account_id) {
            return response()->json(['ok' => false, 'message' => "\u{041D}\u{0435}\u{0442} \u{0434}\u{043E}\u{0441}\u{0442}\u{0443}\u{043F}\u{0430}"], 403);
        }
        if ($to->pipeline_id !== $deal->pipeline_id) {
            return response()->json(['ok' => false, 'message' => "\u{042D}\u{0442}\u{0430}\u{043F} \u{043E}\u{0442}\u{043D}\u{043E}\u{0441}\u{0438}\u{0442}\u{0441}\u{044F} \u{043A} \u{0434}\u{0440}\u{0443}\u{0433}\u{043E}\u{0439} \u{0432}\u{043E}\u{0440}\u{043E}\u{043D}\u{043A}\u{0435}"], 422);
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
            'body' => $this->stageChangedActivityBody(),
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

        return response()->json([
            'ok' => true,
            'last_moved_by_label' => $this->lastMovedByLabel($user->name),
        ]);
    }

    private function dealCreatedActivityBody(): string
    {
        return "\u{0421}\u{0434}\u{0435}\u{043B}\u{043A}\u{0430} \u{0441}\u{043E}\u{0437}\u{0434}\u{0430}\u{043D}\u{0430}.";
    }

    private function dealUpdatedActivityBody(): string
    {
        return "\u{0414}\u{0430}\u{043D}\u{043D}\u{044B}\u{0435} \u{0441}\u{0434}\u{0435}\u{043B}\u{043A}\u{0438} \u{043E}\u{0431}\u{043D}\u{043E}\u{0432}\u{043B}\u{0435}\u{043D}\u{044B}.";
    }

    private function dealUpdatedStatusMessage(): string
    {
        return "\u{0421}\u{0434}\u{0435}\u{043B}\u{043A}\u{0430} \u{043E}\u{0431}\u{043D}\u{043E}\u{0432}\u{043B}\u{0435}\u{043D}\u{0430}.";
    }

    private function dealClosedActivityBody(string $result, ?string $reason): string
    {
        $base = $result === 'won'
            ? "\u{0421}\u{0434}\u{0435}\u{043B}\u{043A}\u{0430} \u{0437}\u{0430}\u{043A}\u{0440}\u{044B}\u{0442}\u{0430} \u{0443}\u{0441}\u{043F}\u{0435}\u{0448}\u{043D}\u{043E}."
            : "\u{0421}\u{0434}\u{0435}\u{043B}\u{043A}\u{0430} \u{0437}\u{0430}\u{043A}\u{0440}\u{044B}\u{0442}\u{0430} \u{043A}\u{0430}\u{043A} \u{043D}\u{0435}\u{0443}\u{0441}\u{043F}\u{0435}\u{0448}\u{043D}\u{0430}\u{044F}.";

        $reason = trim((string) $reason);
        if ($reason === '') {
            return $base;
        }

        return $base.' '."\u{041F}\u{0440}\u{0438}\u{0447}\u{0438}\u{043D}\u{0430}: ".$reason;
    }

    private function dealClosedStatusMessage(): string
    {
        return "\u{0421}\u{0434}\u{0435}\u{043B}\u{043A}\u{0430} \u{0437}\u{0430}\u{043A}\u{0440}\u{044B}\u{0442}\u{0430}.";
    }

    private function closedDealStageChangeError(): string
    {
        return "\u{0417}\u{0430}\u{043A}\u{0440}\u{044B}\u{0442}\u{0443}\u{044E} \u{0441}\u{0434}\u{0435}\u{043B}\u{043A}\u{0443} \u{043D}\u{0435}\u{043B}\u{044C}\u{0437}\u{044F} \u{043F}\u{0435}\u{0440}\u{0435}\u{043C}\u{0435}\u{0449}\u{0430}\u{0442}\u{044C}.";
    }

    private function stageChangedActivityBody(): string
    {
        return "\u{0421}\u{0442}\u{0430}\u{0434}\u{0438}\u{044F} \u{0441}\u{0434}\u{0435}\u{043B}\u{043A}\u{0438} \u{0438}\u{0437}\u{043C}\u{0435}\u{043D}\u{0435}\u{043D}\u{0430}.";
    }

    private function lastMovedByLabel(string $userName): string
    {
        return "\u{041F}\u{043E}\u{0441}\u{043B}\u{0435}\u{0434}\u{043D}\u{0438}\u{0439} \u{043F}\u{0435}\u{0440}\u{0435}\u{043D}\u{043E}\u{0441}: ".$userName;
    }

    private function unknownLeadLabel(): string
    {
        return "\u{0411}\u{0435}\u{0437} \u{0438}\u{043C}\u{0435}\u{043D}\u{0438}";
    }

    private function crmConversationSubtitle(): string
    {
        return 'CRM';
    }

    private function conversationUnavailableBody(): string
    {
        return "\u{0421}\u{043E}\u{043E}\u{0431}\u{0449}\u{0435}\u{043D}\u{0438}\u{0435} \u{043D}\u{0435}\u{0434}\u{043E}\u{0441}\u{0442}\u{0443}\u{043F}\u{043D}\u{043E}.";
    }
    private function nonTargetStagePatterns(): array
    {
        return [
            "%\u{0441}\u{043F}\u{0430}\u{043C}%",
            '%spam%',
            "%\u{043D}\u{0435}\u{0446}\u{0435}\u{043B}\u{0435}\u{0432}%",
            '%non-target%',
        ];
    }
    private function resolveKanbanFocusDate(string $value): ?Carbon
    {
        $value = trim($value);

        if ($value === '') {
            return null;
        }

        try {
            return Carbon::createFromFormat('Y-m-d', $value)->startOfDay();
        } catch (\Throwable) {
            return null;
        }
    }

    private function isDateFilteredStage(PipelineStage $stage): bool
    {
        $name = mb_strtolower(trim((string) $stage->name));

        return str_contains($name, "\u{043A}\u{0432}\u{0430}\u{043B}")
            && str_contains($name, "\u{0437}\u{0430}\u{043C}\u{0435}\u{0440}");
    }
}
