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
    $broadcastTemplatesJson = $broadcastTemplates;
    $broadcastRecipientsJson = $broadcastRecipients;
    $broadcastCountsJson = $todayBroadcastCounts;
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
    const counts = @json($broadcastCountsJson);
    const categoryButtons = Array.from(document.querySelectorAll('[data-broadcast-category]'));
    const categoryInput = document.getElementById('broadcastCategoryInput');
    const templateInput = document.getElementById('broadcastTemplateInput');
    const templateList = document.getElementById('broadcastTemplateList');
    const recipientList = document.getElementById('broadcastRecipientList');
    const recipientCounter = document.getElementById('broadcastRecipientCounter');
    const textArea = document.getElementById('broadcastText');
    const submitButton = document.getElementById('broadcastSubmitButton');
    const eligibleSummary = document.getElementById('broadcastEligibleSummary');
    const categoryNote = document.getElementById('broadcastCategoryNote');
    const targetModeInputs = Array.from(document.querySelectorAll('input[name="broadcast_target_mode"]'));

    if (!categoryButtons.length || !categoryInput || !templateInput || !templateList || !recipientList || !recipientCounter || !textArea || !submitButton || !eligibleSummary || !categoryNote) {
        return;
    }

    let selectedCategory = categoryInput.value || categoryButtons[0].dataset.broadcastCategory;
    let selectedTemplateKey = templateInput.value || '';

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

    function recipientsForSelectedCategory() {
        return Array.isArray(recipientsByCategory[selectedCategory]) ? recipientsByCategory[selectedCategory] : [];
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

    function renderRecipients() {
        const items = recipientsForSelectedCategory();
        const mode = selectedTargetMode();
        const dealCount = Number(counts[selectedCategory] || items.length || 0);
        const totalChatCount = chatCount(items, mode);

        recipientCounter.textContent = `${dealCount} сделок / ${totalChatCount} чатов`;
        eligibleSummary.textContent = `Получатели: ${dealCount} сделок / ${totalChatCount} чатов`;
        categoryNote.textContent = dealCount > 0
            ? `Категория «${categoryLabel(selectedCategory)}»: видно всех адресатов на сегодня. Режим сейчас ${mode === 'all' ? 'во все чаты сделки' : 'в один чат на сделку'}.`
            : `На сегодня нет открытых сделок категории «${categoryLabel(selectedCategory)}» с делами и доступными чатами VK/Avito.`;
        submitButton.disabled = dealCount <= 0;

        if (!items.length) {
            recipientList.innerHTML = '<div class="text-muted small">Список получателей пуст.</div>';
            return;
        }

        recipientList.innerHTML = items.map((item) => {
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

            return `
                <div class="border rounded-3 bg-white p-3">
                    <div class="d-flex align-items-start justify-content-between gap-2 flex-wrap">
                        <div>
                            <a class="fw-semibold text-decoration-none" href="${escapeHtml(item.url || '#')}">${escapeHtml(item.label || 'Сделка')}</a>
                            <div class="text-muted small mt-1">${escapeHtml(details || 'Без контакта')}</div>
                            <div class="text-muted small mt-1">${escapeHtml(taskTimes)}</div>
                        </div>
                        <div class="badge bg-light text-dark border">Основной чат: ${primaryLabel}</div>
                    </div>
                    <div class="d-flex gap-1 flex-wrap mt-2">${channelBadges}</div>
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
