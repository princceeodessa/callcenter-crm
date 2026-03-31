@extends('layouts.app')

@push('styles')
<style>
    .broadcast-category-btn.active {
        box-shadow: inset 0 0 0 1px rgba(255,255,255,.12), 0 10px 24px rgba(15,23,42,.18);
    }
    .broadcast-template-option {
        border: 1px solid rgba(15, 23, 42, .08);
        border-radius: 1rem;
        background: rgba(255,255,255,.75);
        padding: .9rem 1rem;
        text-align: left;
        transition: border-color .15s ease, transform .15s ease, box-shadow .15s ease;
    }
    .broadcast-template-option:hover {
        border-color: rgba(37, 99, 235, .45);
        transform: translateY(-1px);
    }
    .broadcast-template-option.active {
        border-color: rgba(37, 99, 235, .7);
        box-shadow: 0 14px 28px rgba(37, 99, 235, .12);
        background: rgba(37, 99, 235, .06);
    }
    .broadcast-template-title {
        font-weight: 700;
        margin-bottom: .35rem;
    }
    .broadcast-template-preview {
        color: #64748b;
        font-size: .9rem;
        line-height: 1.35;
    }
    .broadcast-recipient-list {
        max-height: 18rem;
        overflow: auto;
    }
    .broadcast-recipient-card {
        display: block;
        transition: border-color .15s ease, box-shadow .15s ease, background-color .15s ease;
    }
    .broadcast-recipient-card:hover {
        border-color: rgba(37, 99, 235, .35);
    }
    .broadcast-recipient-card.is-selected {
        border-color: rgba(37, 99, 235, .65);
        box-shadow: 0 10px 24px rgba(37, 99, 235, .08);
        background: rgba(37, 99, 235, .04) !important;
    }
</style>
@endpush

@php
    $productCategoryOptions = $productCategoryOptions ?? [];
    $broadcastTemplates = $broadcastTemplates ?? [];
    $broadcastRecipients = $broadcastRecipients ?? [];
    $todayBroadcastCounts = $todayBroadcastCounts ?? [];
    $broadcastTargetModeOptions = $broadcastTargetModeOptions ?? [];
    $firstCategoryWithRecipients = collect($todayBroadcastCounts)->filter(fn ($count) => (int) $count > 0)->keys()->first();
    $selectedBroadcastCategory = old('broadcast_category', $firstCategoryWithRecipients ?? array_key_first($broadcastTemplates) ?? array_key_first($productCategoryOptions));
    $selectedBroadcastTemplate = old('broadcast_template_key', '');
    $selectedBroadcastTargetMode = old('broadcast_target_mode', array_key_first($broadcastTargetModeOptions) ?: 'primary');
    $selectedBroadcastText = old('broadcast_text', '');
    $selectedBroadcastDealIds = collect(old('broadcast_deal_ids', []))
        ->map(static fn ($dealId) => (int) $dealId)
        ->filter(static fn ($dealId) => $dealId > 0)
        ->values()
        ->all();
    $broadcastTemplatesJson = $broadcastTemplates;
    $broadcastRecipientsJson = $broadcastRecipients;
    $broadcastCountsJson = $todayBroadcastCounts;
    $selectedBroadcastDealIdsJson = $selectedBroadcastDealIds;
    $broadcastReport = session('broadcast_report');
    $broadcastPreviewError = $broadcastPreviewError ?? null;

    $dealLabel = function ($deal) {
        $contact = trim((string) ($deal->contact?->name ?? ''));
        $phone = trim((string) ($deal->contact?->phone ?? ''));
        $title = trim((string) ($deal->title ?? ''));
        $parts = array_filter([$title !== '' ? $title : ('Сделка #'.$deal->id), $contact, $phone]);

        return implode(' | ', $parts);
    };

    $taskDealTitle = function ($task) {
        $deal = $task->deal;
        if (! $deal) {
            return 'Сделка не найдена';
        }

        $contact = trim((string) ($deal->contact?->name ?? ''));
        $phone = trim((string) ($deal->contact?->phone ?? ''));
        $title = trim((string) ($deal->title ?? ''));
        $parts = array_filter([$title !== '' ? $title : ('Сделка #'.$deal->id), $contact, $phone]);

        return implode(' | ', $parts);
    };

    $buildTasksUrl = function (array $changes = []) {
        $query = array_merge(request()->query(), $changes);

        foreach ($query as $key => $value) {
            if ($value === null || $value === '' || ($key === 'assigned_user_id' && (int) $value === 0)) {
                unset($query[$key]);
            }
        }

        $queryString = http_build_query($query);

        return route('tasks.index').($queryString !== '' ? '?'.$queryString : '');
    };

    $daySectionTitle = $isTodayFocusDate ? 'На сегодня' : 'На дату';
    $daySectionEmpty = $isTodayFocusDate
        ? 'На сегодня открытых дел нет.'
        : 'На выбранную дату открытых дел нет.';
    $previousSectionTitle = $isTodayFocusDate ? 'Просроченные' : 'Раньше выбранной даты';
    $previousSectionEmpty = $isTodayFocusDate ? 'Просроченных дел нет.' : 'До выбранной даты дел нет.';
    $nextSectionTitle = $isTodayFocusDate ? 'Ближайшие' : 'После выбранной даты';
    $nextSectionEmpty = $isTodayFocusDate ? 'Ближайших дел нет.' : 'После выбранной даты дел нет.';
@endphp

@section('content')
<div class="d-flex flex-column gap-3">
    <div class="d-flex align-items-start justify-content-between flex-wrap gap-3">
        <div>
            <h4 class="mb-1">Дела</h4>
            <div class="text-muted">{{ $isTodayFocusDate ? 'Сегодня' : 'Выбрана дата' }}: {{ $focusDateLabel }}</div>
        </div>
        <form class="d-flex gap-2 flex-wrap" method="GET" action="{{ route('tasks.index') }}">
            <select class="form-select form-select-sm" name="assigned_user_id" style="min-width: 220px;">
                <option value="0">Все сотрудники</option>
                <option value="-1" @selected($assignedUserId === -1)>Только "Всем"</option>
                @foreach($users as $worker)
                    <option value="{{ $worker->id }}" @selected($assignedUserId === (int) $worker->id)>{{ $worker->name }}</option>
                @endforeach
            </select>
            <input type="date" class="form-control form-control-sm" name="focus_date" value="{{ $focusDate }}">
            <input class="form-control form-control-sm" name="q" value="{{ $search }}" placeholder="Поиск по делу, клиенту, телефону, названию">
            <button class="btn btn-sm btn-primary">Найти</button>
            @if($search !== '' || $assignedUserId !== 0 || ! $isTodayFocusDate)
                <a class="btn btn-sm btn-outline-secondary" href="{{ route('tasks.index') }}">Сбросить</a>
            @endif
        </form>
    </div>

    @include('tasks._broadcast_panel')

    <div class="card shadow-sm">
        <div class="card-header fw-semibold">Новое дело</div>
        <div class="card-body">
            <form method="POST" action="{{ route('tasks.page.store') }}" class="row g-3">
                @csrf
                <div class="col-12 col-lg-5">
                    <label class="form-label">Сделка</label>
                    <select name="deal_id" class="form-select" required>
                        <option value="">Выберите сделку</option>
                        @foreach($deals as $deal)
                            <option value="{{ $deal->id }}" @selected((int) old('deal_id') === (int) $deal->id)>{{ $dealLabel($deal) }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-12 col-lg-3">
                    <label class="form-label">Кому назначить</label>
                    <select name="assigned_user_id" class="form-select">
                        <option value="0" @selected((string) old('assigned_user_id', '0') === '0')>Всем</option>
                        @foreach($users as $worker)
                            <option value="{{ $worker->id }}" @selected((int) old('assigned_user_id') === (int) $worker->id)>{{ $worker->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-12 col-lg-4">
                    <label class="form-label">Когда напомнить</label>
                    <input type="datetime-local" name="due_at" class="form-control" value="{{ old('due_at', now()->addHour()->format('Y-m-d\\TH:i')) }}" required>
                </div>
                <div class="col-12">
                    <label class="form-label">Название</label>
                    <input name="title" class="form-control" value="{{ old('title') }}" placeholder="Например: связаться с клиентом" required>
                </div>
                <div class="col-12">
                    <label class="form-label">Комментарий</label>
                    <textarea name="description" class="form-control" rows="3" placeholder="Комментарий по делу">{{ old('description') }}</textarea>
                </div>
                <div class="col-12">
                    <button class="btn btn-primary">Добавить дело</button>
                </div>
            </form>
        </div>
    </div>

    <div class="card shadow-sm">
        <div class="card-header d-flex justify-content-between align-items-center flex-wrap gap-2">
            <div class="d-flex flex-column">
                <span class="fw-semibold">{{ $daySectionTitle }}</span>
                <span class="text-muted small">{{ $focusDateLabel }}</span>
            </div>
            <span class="text-muted small">{{ $selectedTasks->count() }} шт.</span>
        </div>
        <div class="card-body">
            @forelse($selectedTasks as $task)
                <div class="border rounded p-3 mb-2">
                    <div class="d-flex justify-content-between align-items-start flex-wrap gap-2">
                        <div>
                            <div class="fw-semibold">{{ $task->title }}</div>
                            <div class="text-muted small mt-1">
                                @if($task->deal)
                                    <a href="{{ route('deals.show', $task->deal) }}" class="text-decoration-none">{{ $taskDealTitle($task) }}</a>
                                @else
                                    {{ $taskDealTitle($task) }}
                                @endif
                            </div>
                            <div class="text-muted small mt-1">
                                До {{ optional($task->due_at)->format('d.m.Y H:i') ?: 'без срока' }}
                                | Ответственный: {{ $task->assignee_label }}
                            </div>
                        </div>
                        <div class="d-flex gap-2 flex-wrap">
                            <a class="btn btn-sm btn-outline-primary" href="{{ $buildTasksUrl(['edit_task' => $task->id]) }}">Редактировать</a>
                            @if($task->deal)
                                <a class="btn btn-sm btn-outline-secondary" href="{{ route('deals.show', $task->deal) }}">Открыть сделку</a>
                            @endif
                            <form method="POST" action="{{ route('tasks.complete', $task) }}">
                                @csrf
                                <button class="btn btn-sm btn-success">Выполнено</button>
                            </form>
                        </div>
                    </div>
                    @if($task->description)
                        <div class="mt-2">{{ $task->description }}</div>
                    @endif

                    @if($editingTaskId === (int) $task->id)
                        @include('tasks._edit_form', [
                            'task' => $task,
                            'users' => $users,
                            'cancelUrl' => $buildTasksUrl(['edit_task' => null]),
                        ])
                    @endif
                </div>
            @empty
                <div class="text-muted">{{ $daySectionEmpty }}</div>
            @endforelse
        </div>
    </div>

    <div class="row g-3">
        <div class="col-12 col-xl-6">
            <div class="card shadow-sm h-100">
                <div class="card-header d-flex justify-content-between align-items-center flex-wrap gap-2">
                    <span class="fw-semibold">{{ $previousSectionTitle }}</span>
                    <span class="text-muted small">{{ $previousTasks->count() }} шт.</span>
                </div>
                <div class="card-body">
                    @forelse($previousTasks as $task)
                        <div class="border rounded p-3 mb-2">
                            <div class="d-flex justify-content-between align-items-start flex-wrap gap-2">
                                <div>
                                    <div class="fw-semibold">{{ $task->title }}</div>
                                    <div class="text-muted small mt-1">
                                        @if($task->deal)
                                            <a href="{{ route('deals.show', $task->deal) }}" class="text-decoration-none">{{ $taskDealTitle($task) }}</a>
                                        @else
                                            {{ $taskDealTitle($task) }}
                                        @endif
                                    </div>
                                    <div class="text-muted small mt-1">
                                        До {{ optional($task->due_at)->format('d.m.Y H:i') ?: 'без срока' }}
                                        | Ответственный: {{ $task->assignee_label }}
                                    </div>
                                </div>
                                <div class="d-flex gap-2 flex-wrap">
                                    <a class="btn btn-sm btn-outline-primary" href="{{ $buildTasksUrl(['edit_task' => $task->id]) }}">Редактировать</a>
                                    @if($task->deal)
                                        <a class="btn btn-sm btn-outline-secondary" href="{{ route('deals.show', $task->deal) }}">Открыть сделку</a>
                                    @endif
                                    <form method="POST" action="{{ route('tasks.complete', $task) }}">
                                        @csrf
                                        <button class="btn btn-sm btn-success">Выполнено</button>
                                    </form>
                                </div>
                            </div>
                            @if($task->description)
                                <div class="mt-2">{{ $task->description }}</div>
                            @endif

                            @if($editingTaskId === (int) $task->id)
                                @include('tasks._edit_form', [
                                    'task' => $task,
                                    'users' => $users,
                                    'cancelUrl' => $buildTasksUrl(['edit_task' => null]),
                                ])
                            @endif
                        </div>
                    @empty
                        <div class="text-muted">{{ $previousSectionEmpty }}</div>
                    @endforelse
                </div>
            </div>
        </div>

        <div class="col-12 col-xl-6">
            <div class="card shadow-sm h-100">
                <div class="card-header d-flex justify-content-between align-items-center flex-wrap gap-2">
                    <span class="fw-semibold">{{ $nextSectionTitle }}</span>
                    <span class="text-muted small">{{ $nextTasks->count() }} шт.</span>
                </div>
                <div class="card-body">
                    @forelse($nextTasks as $task)
                        <div class="border rounded p-3 mb-2">
                            <div class="d-flex justify-content-between align-items-start flex-wrap gap-2">
                                <div>
                                    <div class="fw-semibold">{{ $task->title }}</div>
                                    <div class="text-muted small mt-1">
                                        @if($task->deal)
                                            <a href="{{ route('deals.show', $task->deal) }}" class="text-decoration-none">{{ $taskDealTitle($task) }}</a>
                                        @else
                                            {{ $taskDealTitle($task) }}
                                        @endif
                                    </div>
                                    <div class="text-muted small mt-1">
                                        До {{ optional($task->due_at)->format('d.m.Y H:i') ?: 'без срока' }}
                                        | Ответственный: {{ $task->assignee_label }}
                                    </div>
                                </div>
                                <div class="d-flex gap-2 flex-wrap">
                                    <a class="btn btn-sm btn-outline-primary" href="{{ $buildTasksUrl(['edit_task' => $task->id]) }}">Редактировать</a>
                                    @if($task->deal)
                                        <a class="btn btn-sm btn-outline-secondary" href="{{ route('deals.show', $task->deal) }}">Открыть сделку</a>
                                    @endif
                                    <form method="POST" action="{{ route('tasks.complete', $task) }}">
                                        @csrf
                                        <button class="btn btn-sm btn-success">Выполнено</button>
                                    </form>
                                </div>
                            </div>
                            @if($task->description)
                                <div class="mt-2">{{ $task->description }}</div>
                            @endif

                            @if($editingTaskId === (int) $task->id)
                                @include('tasks._edit_form', [
                                    'task' => $task,
                                    'users' => $users,
                                    'cancelUrl' => $buildTasksUrl(['edit_task' => null]),
                                ])
                            @endif
                        </div>
                    @empty
                        <div class="text-muted">{{ $nextSectionEmpty }}</div>
                    @endforelse
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
(() => {
    const templates = @json($broadcastTemplatesJson);
    const recipientsByCategory = @json($broadcastRecipientsJson);
    const initialSelectedDealIds = @json($selectedBroadcastDealIdsJson);
    const categoryButtons = Array.from(document.querySelectorAll('[data-broadcast-category]'));
    const categoryInput = document.getElementById('broadcastCategoryInput');
    const templateInput = document.getElementById('broadcastTemplateInput');
    const templateList = document.getElementById('broadcastTemplateList');
    const recipientList = document.getElementById('broadcastRecipientList');
    const recipientCounter = document.getElementById('broadcastRecipientCounter');
    const recipientActions = document.getElementById('broadcastRecipientActions');
    const selectionSummary = document.getElementById('broadcastSelectionSummary');
    const textArea = document.getElementById('broadcastText');
    const submitButton = document.getElementById('broadcastSubmitButton');
    const eligibleSummary = document.getElementById('broadcastEligibleSummary');
    const categoryNote = document.getElementById('broadcastCategoryNote');
    const selectAllButton = document.getElementById('broadcastSelectAllButton');
    const clearAllButton = document.getElementById('broadcastClearAllButton');
    const targetModeInputs = Array.from(document.querySelectorAll('input[name="broadcast_target_mode"]'));

    if (
        !categoryButtons.length
        || !categoryInput
        || !templateInput
        || !templateList
        || !recipientList
        || !recipientCounter
        || !recipientActions
        || !selectionSummary
        || !textArea
        || !submitButton
        || !eligibleSummary
        || !categoryNote
        || !selectAllButton
        || !clearAllButton
    ) {
        return;
    }

    let selectedCategory = categoryInput.value || categoryButtons[0].dataset.broadcastCategory;
    let selectedTemplateKey = templateInput.value || '';
    const selectionSeedCategory = selectedCategory;
    const selectedDealsByCategory = {};

    function escapeHtml(value) {
        return String(value ?? '')
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;');
    }

    function categoryLabel(categoryKey) {
        const button = categoryButtons.find((item) => item.dataset.broadcastCategory === categoryKey);
        return button ? (button.dataset.broadcastLabel || categoryKey) : categoryKey;
    }

    function selectedTargetMode() {
        return targetModeInputs.find((input) => input.checked)?.value || 'primary';
    }

    function recipientsForCategory(categoryKey) {
        return Array.isArray(recipientsByCategory[categoryKey]) ? recipientsByCategory[categoryKey] : [];
    }

    function recipientsForSelectedCategory() {
        return recipientsForCategory(selectedCategory);
    }

    function normalizedDealIds(items, rawIds = []) {
        const availableIds = items
            .map((item) => Number(item.deal_id || 0))
            .filter((dealId) => Number.isInteger(dealId) && dealId > 0);
        const availableIdSet = new Set(availableIds);

        return Array.from(new Set(
            rawIds
                .map((dealId) => Number(dealId || 0))
                .filter((dealId) => Number.isInteger(dealId) && dealId > 0 && availableIdSet.has(dealId))
        ));
    }

    function ensureRecipientSelection(categoryKey) {
        const items = recipientsForCategory(categoryKey);

        if (Object.prototype.hasOwnProperty.call(selectedDealsByCategory, categoryKey)) {
            selectedDealsByCategory[categoryKey] = normalizedDealIds(items, selectedDealsByCategory[categoryKey]);
            return;
        }

        const seedIds = initialSelectedDealIds.length && categoryKey === selectionSeedCategory
            ? initialSelectedDealIds
            : items.map((item) => item.deal_id);

        selectedDealsByCategory[categoryKey] = normalizedDealIds(items, seedIds);
    }

    function selectedDealIds() {
        ensureRecipientSelection(selectedCategory);
        return selectedDealsByCategory[selectedCategory] || [];
    }

    function selectedRecipients(items) {
        const selectedIds = new Set(selectedDealIds());
        return items.filter((item) => selectedIds.has(Number(item.deal_id || 0)));
    }

    function chatCount(items, mode) {
        if (mode !== 'all') {
            return items.length;
        }

        return items.reduce((total, item) => total + Number(item.chat_count || 0), 0);
    }

    function updateCategoryButtons() {
        categoryButtons.forEach((button) => {
            const active = button.dataset.broadcastCategory === selectedCategory;
            button.classList.toggle('active', active);
            button.classList.toggle('btn-primary', active);
            button.classList.toggle('btn-outline-primary', !active);
        });
    }

    function updateRecipientSummary(items, selectedItems, mode) {
        const totalDealCount = items.length;
        const totalChatCount = chatCount(items, mode);
        const selectedDealCount = selectedItems.length;
        const selectedChatCount = chatCount(selectedItems, mode);
        const category = categoryLabel(selectedCategory);

        recipientCounter.textContent = `${selectedDealCount} сделок / ${selectedChatCount} чатов`;
        eligibleSummary.textContent = `Получатели: ${selectedDealCount} сделок / ${selectedChatCount} чатов`;
        selectionSummary.textContent = totalDealCount > 0
            ? `Выбрано ${selectedDealCount} из ${totalDealCount} сделок (${selectedChatCount} из ${totalChatCount} чатов).`
            : 'Можно снять галочку с тех, кому не нужно отправлять.';
        categoryNote.textContent = totalDealCount > 0
            ? `Категория «${category}»: снимите галочку у тех, кого нужно исключить из рассылки. Режим сейчас ${mode === 'all' ? 'во все чаты сделки' : 'в один чат на сделку'}.`
            : `На сегодня нет открытых сделок категории «${category}» с делами и доступными чатами VK/Avito.`;

        recipientActions.classList.toggle('d-none', totalDealCount <= 0);
        selectAllButton.disabled = totalDealCount <= 0 || selectedDealCount === totalDealCount;
        clearAllButton.disabled = totalDealCount <= 0 || selectedDealCount === 0;
        submitButton.disabled = selectedDealCount <= 0;
    }

    function renderRecipients() {
        const items = recipientsForSelectedCategory();
        const mode = selectedTargetMode();
        const selectedItems = selectedRecipients(items);
        const selectedIds = new Set(selectedDealIds());

        updateRecipientSummary(items, selectedItems, mode);

        if (!items.length) {
            recipientList.innerHTML = '<div class="text-muted small">Список получателей пуст.</div>';
            return;
        }

        recipientList.innerHTML = items.map((item) => {
            const dealId = Number(item.deal_id || 0);
            const isSelected = selectedIds.has(dealId);
            const details = [item.contact_name, item.phone].filter(Boolean).join(' • ');
            const taskTimes = Array.isArray(item.task_times) && item.task_times.length
                ? `Дело сегодня: ${item.task_times.join(', ')}`
                : 'Дело сегодня без времени';
            const channelBadges = Array.isArray(item.channels)
                ? item.channels.map((channel) => {
                    const count = Number(channel.count || 0);
                    const suffix = count > 1 ? ` ×${count}` : '';
                    return `<span class="badge rounded-pill bg-light text-dark border">${escapeHtml(channel.label || channel.channel || 'Чат')}${suffix}</span>`;
                }).join(' ')
                : '';
            const primaryLabel = escapeHtml(item.primary_chat_label || 'Чат');
            const modeNote = mode === 'all'
                ? `Будет отправлено во все чаты сделки: ${Number(item.chat_count || 0)}.`
                : 'Будет отправлено в один чат сделки.';

            return `
                <div class="broadcast-recipient-card border rounded-3 bg-white p-3 ${isSelected ? 'is-selected' : ''}" data-deal-id="${dealId}">
                    <div class="d-flex align-items-start gap-3">
                        <input
                            class="form-check-input mt-1 broadcast-recipient-checkbox"
                            type="checkbox"
                            name="broadcast_deal_ids[]"
                            value="${dealId}"
                            ${isSelected ? 'checked' : ''}
                        >
                        <div class="flex-grow-1">
                            <div class="d-flex align-items-start justify-content-between gap-2 flex-wrap">
                                <div>
                                    <a class="fw-semibold text-decoration-none" href="${escapeHtml(item.url || '#')}">${escapeHtml(item.label || 'Сделка')}</a>
                                    <div class="text-muted small mt-1">${escapeHtml(details || 'Без контакта')}</div>
                                    <div class="text-muted small mt-1">${escapeHtml(taskTimes)}</div>
                                    <div class="text-muted small mt-1">${escapeHtml(modeNote)}</div>
                                </div>
                                <div class="badge bg-light text-dark border">Основной чат: ${primaryLabel}</div>
                            </div>
                            <div class="d-flex gap-1 flex-wrap mt-2">${channelBadges}</div>
                        </div>
                    </div>
                </div>
            `;
        }).join('');
    }

    function renderTemplates() {
        const items = Array.isArray(templates[selectedCategory]) ? templates[selectedCategory] : [];
        if (!items.length) {
            templateList.innerHTML = '<div class="col-12"><div class="text-muted small">Для выбранной категории шаблонов пока нет.</div></div>';
            templateInput.value = '';
            return;
        }

        if (!items.some((item) => item.key === selectedTemplateKey)) {
            selectedTemplateKey = items[0].key;
            if (!textArea.value.trim()) {
                textArea.value = items[0].text || '';
            }
        }

        templateList.innerHTML = items.map((item) => {
            const active = item.key === selectedTemplateKey;
            return `
                <div class="col-lg-6">
                    <button type="button" class="w-100 broadcast-template-option ${active ? 'active' : ''}" data-template-key="${escapeHtml(item.key)}">
                        <div class="broadcast-template-title">${escapeHtml(item.title || 'Шаблон')}</div>
                        <div class="broadcast-template-preview">${escapeHtml(item.preview || '')}</div>
                    </button>
                </div>
            `;
        }).join('');

        templateInput.value = selectedTemplateKey;

        templateList.querySelectorAll('[data-template-key]').forEach((button) => {
            button.addEventListener('click', () => {
                const key = button.getAttribute('data-template-key') || '';
                const template = items.find((item) => item.key === key);
                selectedTemplateKey = key;
                templateInput.value = key;
                if (template) {
                    textArea.value = template.text || '';
                }
                renderTemplates();
            });
        });
    }

    categoryButtons.forEach((button) => {
        button.addEventListener('click', () => {
            selectedCategory = button.dataset.broadcastCategory || '';
            selectedTemplateKey = '';
            categoryInput.value = selectedCategory;
            textArea.value = '';
            updateCategoryButtons();
            renderRecipients();
            renderTemplates();
        });
    });

    recipientList.addEventListener('change', (event) => {
        const target = event.target;
        if (!(target instanceof HTMLInputElement) || !target.classList.contains('broadcast-recipient-checkbox')) {
            return;
        }

        ensureRecipientSelection(selectedCategory);

        const dealId = Number(target.value || 0);
        const updatedSelection = new Set(selectedDealsByCategory[selectedCategory] || []);

        if (target.checked) {
            updatedSelection.add(dealId);
        } else {
            updatedSelection.delete(dealId);
        }

        selectedDealsByCategory[selectedCategory] = Array.from(updatedSelection);
        renderRecipients();
    });

    selectAllButton.addEventListener('click', () => {
        const items = recipientsForSelectedCategory();
        selectedDealsByCategory[selectedCategory] = normalizedDealIds(items, items.map((item) => item.deal_id));
        renderRecipients();
    });

    clearAllButton.addEventListener('click', () => {
        selectedDealsByCategory[selectedCategory] = [];
        renderRecipients();
    });

    targetModeInputs.forEach((input) => {
        input.addEventListener('change', renderRecipients);
    });

    categoryInput.value = selectedCategory;
    updateCategoryButtons();
    renderRecipients();
    renderTemplates();
})();
</script>
@endpush
