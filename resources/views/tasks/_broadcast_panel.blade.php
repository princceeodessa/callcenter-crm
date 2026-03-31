@if($broadcastReport)
    <div class="alert alert-info shadow-sm mb-0">
        <div class="fw-semibold mb-1">Итог рассылки</div>
        <div class="small">
            Категория: <b>{{ $broadcastReport['category_label'] ?? '—' }}</b>
            • Режим: <b>{{ $broadcastReport['target_mode_label'] ?? '—' }}</b>
            • Дата: <b>{{ $broadcastReport['date_label'] ?? '—' }}</b>
            • Отправлено в чаты: <b>{{ $broadcastReport['sent_count'] ?? 0 }}</b>
            • Сделок затронуто: <b>{{ $broadcastReport['sent_deal_count'] ?? 0 }}</b>
            • Пропущено: <b>{{ $broadcastReport['skipped_count'] ?? 0 }}</b>
            • Ошибок: <b>{{ $broadcastReport['error_count'] ?? 0 }}</b>
        </div>
        <div class="small mt-1">
            VK: <b>{{ $broadcastReport['sent_by_channel']['vk'] ?? 0 }}</b>
            • Avito: <b>{{ $broadcastReport['sent_by_channel']['avito'] ?? 0 }}</b>
        </div>
        @if(!empty($broadcastReport['skipped_items'] ?? []))
            <div class="small mt-2">
                <div class="fw-semibold mb-1">Пропущено</div>
                <ul class="mb-0 ps-3">
                    @foreach($broadcastReport['skipped_items'] as $item)
                        <li>{{ $item }}</li>
                    @endforeach
                </ul>
            </div>
        @endif
        @if(!empty($broadcastReport['error_items'] ?? []))
            <div class="small mt-2">
                <div class="fw-semibold mb-1">Ошибки</div>
                <ul class="mb-0 ps-3">
                    @foreach($broadcastReport['error_items'] as $item)
                        <li>{{ $item }}</li>
                    @endforeach
                </ul>
            </div>
        @endif
    </div>
@endif

@if($broadcastPreviewError)
    <div class="alert alert-warning shadow-sm mb-0">
        {{ $broadcastPreviewError }}
    </div>
@endif

<div class="card shadow-sm">
    <div class="card-header d-flex align-items-center justify-content-between gap-2 flex-wrap">
        <div>
            <div class="fw-semibold">Рассылка по делам на сегодня</div>
            <div class="text-muted small">Берутся только открытые сделки выбранной категории, у которых на сегодня есть открытое дело и есть отправляемый чат VK или Avito с непустым `external_id`.</div>
        </div>
        <div class="text-muted small" id="broadcastEligibleSummary">Получатели: —</div>
    </div>
    <div class="card-body">
        <form method="POST" action="{{ route('deals.broadcast-today') }}" id="broadcastTodayForm">
            @csrf
            <input type="hidden" name="broadcast_category" id="broadcastCategoryInput" value="{{ $selectedBroadcastCategory }}">
            <input type="hidden" name="broadcast_template_key" id="broadcastTemplateInput" value="{{ $selectedBroadcastTemplate }}">

            <div class="mb-3">
                <div class="small text-muted mb-2">Категория продукта</div>
                <div class="d-flex gap-2 flex-wrap" id="broadcastCategoryButtons">
                    @foreach($productCategoryOptions as $categoryKey => $categoryLabel)
                        <button
                            type="button"
                            class="btn btn-sm btn-outline-primary broadcast-category-btn {{ $selectedBroadcastCategory === $categoryKey ? 'active' : '' }}"
                            data-broadcast-category="{{ $categoryKey }}"
                            data-broadcast-label="{{ $categoryLabel }}"
                        >
                            {{ $categoryLabel }}
                            <span class="badge text-bg-light ms-1">{{ (int) ($todayBroadcastCounts[$categoryKey] ?? 0) }}</span>
                        </button>
                    @endforeach
                </div>
            </div>

            <div class="mb-3">
                <div class="small text-muted mb-2">Режим отправки</div>
                <div class="d-flex gap-3 flex-wrap">
                    @foreach($broadcastTargetModeOptions as $modeKey => $modeLabel)
                        <label class="form-check form-check-inline m-0">
                            <input
                                class="form-check-input"
                                type="radio"
                                name="broadcast_target_mode"
                                value="{{ $modeKey }}"
                                @checked($selectedBroadcastTargetMode === $modeKey)
                            >
                            <span class="form-check-label">{{ $modeLabel }}</span>
                        </label>
                    @endforeach
                </div>
            </div>

            <div class="mb-3">
                <div class="small text-muted mb-2">Кому отправляешь</div>
                <div class="border rounded-3 bg-light p-3">
                    <div class="d-flex align-items-center justify-content-between gap-2 flex-wrap">
                        <div class="text-muted small" id="broadcastCategoryNote">Выберите категорию, чтобы увидеть адресатов на сегодня.</div>
                        <span class="badge text-bg-light" id="broadcastRecipientCounter">0</span>
                    </div>
                    <div class="d-flex align-items-center justify-content-between gap-2 flex-wrap mt-3" id="broadcastRecipientActions">
                        <div class="text-muted small" id="broadcastSelectionSummary">Можно снять галочку с тех, кому не нужно отправлять.</div>
                        <div class="d-flex gap-2 flex-wrap">
                            <button type="button" class="btn btn-sm btn-outline-secondary" id="broadcastSelectAllButton">Выбрать всех</button>
                            <button type="button" class="btn btn-sm btn-outline-secondary" id="broadcastClearAllButton">Снять всех</button>
                        </div>
                    </div>
                    <div class="broadcast-recipient-list d-flex flex-column gap-2 mt-3" id="broadcastRecipientList"></div>
                    @error('broadcast_deal_ids')
                        <div class="text-danger small mt-2">{{ $message }}</div>
                    @enderror
                </div>
            </div>

            <div class="mb-3">
                <div class="small text-muted mb-2">Шаблоны</div>
                <div class="row g-2" id="broadcastTemplateList"></div>
            </div>

            <div class="mb-3">
                <label for="broadcastText" class="form-label">Текст рассылки</label>
                <textarea
                    id="broadcastText"
                    name="broadcast_text"
                    class="form-control"
                    rows="8"
                    placeholder="Выберите шаблон или введите свой текст"
                    required
                >{{ $selectedBroadcastText }}</textarea>
                @error('broadcast_text')
                    <div class="text-danger small mt-1">{{ $message }}</div>
                @enderror
            </div>

            <div class="d-flex align-items-center justify-content-between gap-2 flex-wrap">
                <div class="text-muted small">Рассылка отправляется по сегодняшним делам независимо от фильтра даты в списке ниже.</div>
                <button class="btn btn-primary" id="broadcastSubmitButton" type="submit">
                    <i class="bi bi-send-fill me-1"></i>Отправить рассылку
                </button>
            </div>
        </form>
    </div>
</div>
