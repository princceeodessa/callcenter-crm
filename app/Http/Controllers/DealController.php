<?php

namespace App\Http\Controllers;

use App\Models\Deal;
use App\Models\Contact;
use App\Models\PipelineStage;
use App\Models\DealActivity;
use App\Models\DealStageHistory;
use App\Models\CallRecording;
use App\Models\User;
use App\Services\Chat\ChatSendService;
use App\Services\Tasks\TaskWorkflowService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

class DealController extends Controller
{
    public function index(Request $request)
    {
        $user = Auth::user();
        $q = $request->string('q')->toString();
        $status = $request->string('status')->toString(); // open|closed|all
        $autoExpandSearch = $request->boolean('auto_expand_search');
        $source = trim($request->string('source')->toString());
        $productCategory = trim($request->string('product_category')->toString());
        $sourceOptions = Deal::sourceFilterOptions();
        $productCategoryOptions = Deal::productCategoryOptions();

        if (!in_array($status, ['open','closed','all'], true)) {
            $status = 'open';
        }
        if ($q !== '' && $status === 'open' && $autoExpandSearch) {
            $status = 'all';
        }
        if ($source !== '' && !array_key_exists($source, $sourceOptions)) {
            $source = '';
        }
        if ($productCategory !== '' && !Deal::isValidProductCategory($productCategory)) {
            $productCategory = '';
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
        if ($productCategory !== '') {
            $dealsQuery->where('product_category', $productCategory);
        }

        $deals = $dealsQuery
            ->paginate(25)
            ->withQueryString();

        $broadcastTemplates = $this->broadcastTemplates();
        $todayBroadcastCounts = $this->todayBroadcastCounts($user->account_id);
        $broadcastTargetModeOptions = $this->broadcastTargetModeOptions();

        return view('deals.index', compact(
            'deals',
            'q',
            'status',
            'source',
            'sourceOptions',
            'productCategory',
            'productCategoryOptions',
            'broadcastTemplates',
            'todayBroadcastCounts',
            'broadcastTargetModeOptions',
        ));
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

        $productCategoryOptions = Deal::productCategoryOptions();

        return view('deals.create', compact('stages','users', 'productCategoryOptions'));
    }

    public function store(Request $request, TaskWorkflowService $taskWorkflow)
    {
        $data = $request->validate([
            'title' => ['required','string','max:255'],
            'responsible_user_id' => ['required','integer','exists:users,id'],
            'amount' => ['nullable','numeric','min:0.01'],
            'product_category' => ['required', Rule::in(array_keys(Deal::productCategoryOptions()))],
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
            'amount' => $data['amount'] ?? null,
            'currency' => 'RUB',
            'product_category' => $data['product_category'],
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
            'product_category' => ['required', Rule::in(array_keys(Deal::productCategoryOptions()))],
        ]);

        $responsible = User::query()
            ->where('account_id', $user->account_id)
            ->where('is_active', 1)
            ->findOrFail((int)$data['responsible_user_id']);

        $old = [
            'title' => $deal->title,
            'responsible_user_id' => $deal->responsible_user_id,
            'amount' => $deal->amount,
            'product_category' => $deal->product_category,
        ];

        $deal->title = $data['title'];
        $deal->title_is_custom = 1;
        $deal->responsible_user_id = $responsible->id;
        $deal->amount = $data['amount'];
        $deal->product_category = $data['product_category'];
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
                    'product_category' => $deal->product_category,
                ],
            ],
        ]);

        return back()->with('status', $this->dealUpdatedStatusMessage());
    }

    public function show(Deal $deal, \App\Services\Ceiling\CeilingProjectCalculator $ceilingCalculator)
    {
        $user = Auth::user();
        abort_unless($deal->account_id === $user->account_id, 403);

        $deal->load([
            'contact',
            'stage',
            'responsible',
            'ceilingProject.rooms',
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
        $productCategoryOptions = Deal::productCategoryOptions();

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

        $ceilingProjectSummary = null;
        if ($user->role === 'admin' && $deal->ceilingProject) {
            $ceilingProjectSummary = $ceilingCalculator->calculateProject($deal->ceilingProject);
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
            'ceilingProjectSummary',
            'productCategoryOptions',
        ));
    }

    public function broadcastToday(Request $request, ChatSendService $chatSendService)
    {
        $user = Auth::user();
        $productCategoryOptions = Deal::productCategoryOptions();
        $targetModeOptions = $this->broadcastTargetModeOptions();

        $data = $request->validate([
            'broadcast_category' => ['required', Rule::in(array_keys($productCategoryOptions))],
            'broadcast_template_key' => ['nullable', 'string', 'max:100'],
            'broadcast_target_mode' => ['required', Rule::in(array_keys($targetModeOptions))],
            'broadcast_text' => ['required', 'string', 'max:4000'],
        ]);

        $text = trim((string) $data['broadcast_text']);
        if ($text === '') {
            return back()
                ->withErrors(['broadcast_text' => 'Введите текст рассылки.'])
                ->withInput();
        }

        $deals = $this->eligibleBroadcastDealsQuery($user->account_id, $data['broadcast_category'])
            ->with([
                'contact',
                'conversations' => fn ($query) => $query
                    ->whereIn('channel', ['vk', 'avito'])
                    ->orderByDesc('last_message_at')
                    ->orderByDesc('id'),
            ])
            ->get();

        $targetMode = $data['broadcast_target_mode'];
        $targetModeLabel = $targetModeOptions[$targetMode] ?? $targetMode;
        $sentCount = 0;
        $sentDealCount = 0;
        $skippedItems = [];
        $errorItems = [];
        $sentByChannel = [
            'vk' => 0,
            'avito' => 0,
        ];

        foreach ($deals as $deal) {
            $conversations = $deal->conversations->filter(function ($item) {
                return trim((string) $item->external_id) !== '';
            })->values();

            if ($conversations->isEmpty()) {
                $skippedItems[] = $this->broadcastDealLabel($deal).': нет чата VK/Avito для отправки.';
                continue;
            }

            if ($targetMode !== 'all') {
                $conversations = $conversations->take(1)->values();
            }

            $dealSent = false;

            foreach ($conversations as $conversation) {
                try {
                    $chatSendService->sendText($conversation, (int) $user->id, $text);
                    $sentCount++;
                    $dealSent = true;
                    if (array_key_exists($conversation->channel, $sentByChannel)) {
                        $sentByChannel[$conversation->channel]++;
                    }
                } catch (\Throwable $e) {
                    $errorItems[] = $this->broadcastDealLabel($deal).' / '.($conversation->source_label ?? $conversation->channel).': '.$e->getMessage();
                }
            }

            if ($dealSent) {
                $sentDealCount++;
            }
        }

        $skippedCount = count($skippedItems);
        $errorCount = count($errorItems);
        $categoryLabel = $productCategoryOptions[$data['broadcast_category']] ?? $data['broadcast_category'];

        $report = [
            'category_label' => $categoryLabel,
            'date_label' => now(config('app.timezone'))->format('d.m.Y'),
            'target_mode' => $targetMode,
            'target_mode_label' => $targetModeLabel,
            'sent_count' => $sentCount,
            'sent_deal_count' => $sentDealCount,
            'skipped_count' => $skippedCount,
            'error_count' => $errorCount,
            'sent_by_channel' => $sentByChannel,
            'skipped_items' => array_slice($skippedItems, 0, 8),
            'error_items' => array_slice($errorItems, 0, 8),
        ];

        $statusMessage = 'Рассылка по категории «'.$categoryLabel.'», режим «'.$targetModeLabel.'»: отправлено в '.$sentCount.' чатов по '.$sentDealCount.' сделкам, пропущено '.$skippedCount.', ошибок '.$errorCount.'.';

        return back()
            ->with('status', $statusMessage)
            ->with('broadcast_report', $report)
            ->withInput();
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

    private function todayBroadcastCounts(int $accountId): array
    {
        $counts = array_fill_keys(array_keys(Deal::productCategoryOptions()), 0);
        [$startAt, $endAt] = $this->todayTaskWindow();

        $rawCounts = Deal::query()
            ->selectRaw('product_category, COUNT(*) as aggregate')
            ->where('account_id', $accountId)
            ->whereNull('closed_at')
            ->whereIn('product_category', array_keys($counts))
            ->whereHas('conversations', function ($query) {
                $query->whereIn('channel', ['vk', 'avito']);
            })
            ->whereHas('tasks', function ($query) use ($startAt, $endAt) {
                $query
                    ->where('status', 'open')
                    ->whereNotNull('due_at')
                    ->where('due_at', '>=', $startAt)
                    ->where('due_at', '<', $endAt);
            })
            ->groupBy('product_category')
            ->pluck('aggregate', 'product_category')
            ->all();

        foreach ($rawCounts as $category => $value) {
            if (array_key_exists($category, $counts)) {
                $counts[$category] = (int) $value;
            }
        }

        return $counts;
    }

    private function eligibleBroadcastDealsQuery(int $accountId, string $category)
    {
        [$startAt, $endAt] = $this->todayTaskWindow();

        return Deal::query()
            ->where('account_id', $accountId)
            ->whereNull('closed_at')
            ->where('product_category', $category)
            ->whereHas('conversations', function ($query) {
                $query->whereIn('channel', ['vk', 'avito']);
            })
            ->whereHas('tasks', function ($query) use ($startAt, $endAt) {
                $query
                    ->where('status', 'open')
                    ->whereNotNull('due_at')
                    ->where('due_at', '>=', $startAt)
                    ->where('due_at', '<', $endAt);
            });
    }

    private function todayTaskWindow(): array
    {
        $startAt = now(config('app.timezone'))->startOfDay();
        $endAt = $startAt->copy()->addDay();

        return [$startAt, $endAt];
    }

    private function broadcastDealLabel(Deal $deal): string
    {
        $lead = trim((string) ($deal->lead_display_name ?? ''));
        $title = trim((string) ($deal->title ?? ''));

        return $lead !== '' ? $lead.' (#'.$deal->id.')' : ($title !== '' ? $title.' (#'.$deal->id.')' : 'Сделка #'.$deal->id);
    }

    private function broadcastTemplates(): array
    {
        return [
            'ceiling' => [
                $this->makeBroadcastTemplate(
                    'ceiling_1',
                    'Шаблон 1',
                    "Добрый день! 🌸😊\nПодскажите, пожалуйста, выезд мастера на замеры актуален для Вас?"
                ),
                $this->makeBroadcastTemplate(
                    'ceiling_2',
                    'Шаблон 2',
                    "Добрый день! Ранее интересовались стоимостью натяжных потолков. Запись на замеры актуальна еще для вас?🙌😊"
                ),
                $this->makeBroadcastTemplate(
                    'ceiling_3',
                    'Шаблон 3',
                    "Добрый день!\nПодскажите, готовы ли принять нашего специалиста на бесплатный замер и консультацию?\nГотовы выехать к вам в ближайшие дни 😇"
                ),
                $this->makeBroadcastTemplate(
                    'ceiling_4',
                    'Шаблон 4',
                    "Добрый день ❤️\nУспевайте воспользоваться сразу 🔥ДВУМЯ ВЫГОДНЫМИ ПРЕДЛОЖЕНИЯМИ🔥\nКаждое 2 и 3 помещение, в подарок идет полотно, а при заключении договора на потолки, будет дополнительная скидка на ВСЮ светотехнику от 20% до 50%🥰❤️\nПодберем для Вас время на бесплатный замер?"
                ),
                $this->makeBroadcastTemplate(
                    'ceiling_5',
                    'Шаблон 5',
                    "Добрый день ✨🙌🏻 Напоминаем что до АКТУАЛЬНАЯ ДАТА у нас проходит АКЦИЯ 2 и 3 потолок в подарок 🎁 плюс скидка на светотехнику!😉 просчет с учетом акций делает специалист по замерам, давайте подберем для вас время?📝"
                ),
            ],
            'air_conditioner' => [
                $this->makeBroadcastTemplate(
                    'air_conditioner_1',
                    'Шаблон 1',
                    "Добрый день! Ранее интересовались стоимостью кондиционеров, подскажите, уже готовы принять специалиста для выбора кондиционера и просчет стоимости?📝❤️"
                ),
                $this->makeBroadcastTemplate(
                    'air_conditioner_2',
                    'Шаблон 2',
                    "Добрый день! Ранее интересовались стоимостью установки кондиционера, подскажите, уже готовы принять специалиста для просчета стоимости?📝❤️"
                ),
                $this->makeBroadcastTemplate(
                    'air_conditioner_3',
                    'Шаблон 3',
                    "Добрый день! 🌸😊\nПодскажите, пожалуйста, выезд мастера на замеры актуален для Вас? Просто почему интересуемся уже начинается сезон кондиционеров 🔥🔥🔥 и запись на монтажи уже на 10 дней вперед, поэтому если планируете установку в ближайшее время, лучше уже пригласить специалиста на просчет и консультацию стоимости и закрепить за собой цену😉🙌"
                ),
                $this->makeBroadcastTemplate(
                    'air_conditioner_4',
                    'Шаблон 4',
                    "Добрый день! Ранее интересовались стоимостью установки кондиционера. Подскажите, когда было бы удобно принять специалиста на консультацию и просчет точной стоимости? 📒💛"
                ),
            ],
            'soundproofing' => [
                $this->makeBroadcastTemplate(
                    'soundproofing_1',
                    'Шаблон 1',
                    "Добрый день! Ранее интересовались стоимостью шумоизоляции. Запись на замеры актуальна еще для вас? Замеры и консультация проходят бесплатно 🌸📝🙌"
                ),
                $this->makeBroadcastTemplate(
                    'soundproofing_2',
                    'Шаблон 2',
                    "Добрый день ☀️ Установка шумоизоляции актуальна еще для вас? Давайте подберем время на консультацию и просчет стоимости?📝"
                ),
            ],
        ];
    }

    private function makeBroadcastTemplate(string $key, string $title, string $text): array
    {
        return [
            'key' => $key,
            'title' => $title,
            'text' => $text,
            'preview' => Str::limit(preg_replace('/\s+/u', ' ', trim($text)) ?: $text, 120),
        ];
    }

    private function broadcastTargetModeOptions(): array
    {
        return [
            'primary' => 'Один чат на сделку',
            'all' => 'Все чаты сделки',
        ];
    }
}
