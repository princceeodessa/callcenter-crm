@extends('layouts.app')

@push('styles')
<style>
  .docs-page{display:flex;flex-direction:column;gap:1.25rem}
  .docs-surface{border:1px solid var(--crm-border);border-radius:1.4rem;background:var(--crm-surface-strong);box-shadow:var(--crm-shadow)}
  .docs-panel{padding:1.15rem 1.2rem}
  .docs-muted{color:var(--crm-muted)}
  .docs-inline{display:flex;flex-wrap:wrap;gap:.55rem}
  .docs-badge{display:inline-flex;align-items:center;gap:.4rem;padding:.34rem .78rem;border-radius:999px;background:rgba(79,70,229,.10);color:var(--crm-accent);font-size:.75rem;font-weight:700}
  .docs-tag{display:inline-flex;align-items:center;padding:.28rem .68rem;border-radius:999px;background:rgba(15,23,42,.06);font-size:.76rem}
  .docs-hero{display:flex;justify-content:space-between;gap:1rem;align-items:flex-start;flex-wrap:wrap}
  .docs-title{max-width:920px}
  .docs-title h2{margin-bottom:.35rem}
  .docs-title p{margin-bottom:0;max-width:860px}
  .docs-stats{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:.85rem;margin-top:1rem}
  .docs-stat{padding:1rem 1.05rem;border:1px solid var(--crm-border);border-radius:1.05rem;background:linear-gradient(180deg,rgba(255,255,255,.78),rgba(255,255,255,.58))}
  .docs-stat-value{font-size:1.25rem;font-weight:700;line-height:1.1}
  .docs-layout{display:grid;grid-template-columns:330px minmax(0,1fr);gap:1rem;align-items:start}
  .docs-rail{display:flex;flex-direction:column;gap:1rem;position:sticky;top:78px}
  .docs-section-title{display:flex;justify-content:space-between;gap:.75rem;align-items:center;margin-bottom:.9rem}
  .docs-book-list{display:flex;flex-direction:column;gap:.75rem;max-height:72vh;overflow:auto;padding-right:.1rem}
  .docs-book-card{display:flex;flex-direction:column;gap:.7rem;padding:1rem;border:1px solid var(--crm-border);border-radius:1.15rem;background:linear-gradient(160deg,rgba(79,70,229,.06),rgba(255,255,255,.92));text-decoration:none;color:inherit;transition:.18s ease}
  .docs-book-card:hover{color:inherit;border-color:color-mix(in srgb,var(--crm-accent) 38%, var(--crm-border));transform:translateY(-1px)}
  .docs-book-card.active{border-color:color-mix(in srgb,var(--crm-accent) 58%, var(--crm-border));box-shadow:0 14px 28px rgba(79,70,229,.14)}
  .docs-book-meta{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:.65rem}
  .docs-book-meta-item{padding:.7rem .75rem;border:1px solid var(--crm-border);border-radius:.9rem;background:rgba(255,255,255,.55)}
  .docs-book-meta-item .small{display:block;margin-bottom:.2rem}
  .docs-main{display:flex;flex-direction:column;gap:1rem}
  .docs-toolbar{display:flex;justify-content:space-between;gap:1rem;align-items:flex-start;flex-wrap:wrap}
  .docs-toolbar-actions{display:flex;gap:.55rem;flex-wrap:wrap}
  .docs-workspace{display:grid;grid-template-columns:minmax(0,1.2fr) minmax(320px,.8fr);gap:1rem}
  .docs-editor{min-height:250px;padding:1rem 1.05rem;border:1px solid var(--crm-border);border-radius:1rem;background:var(--crm-surface-strong);overflow:auto;line-height:1.65}
  .docs-toolbar-mini{display:flex;flex-wrap:wrap;gap:.45rem;margin-bottom:.75rem}
  .docs-access{display:flex;flex-direction:column;gap:1rem}
  .docs-box{padding:1rem;border:1px solid var(--crm-border);border-radius:1rem;background:rgba(255,255,255,.55)}
  .docs-sheet-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(280px,1fr));gap:1rem}
  .docs-sheet-card{display:flex;flex-direction:column;gap:.8rem;padding:1rem;border:1px solid var(--crm-border);border-radius:1.15rem;background:var(--crm-surface-strong);box-shadow:0 10px 24px rgba(15,23,42,.06);text-decoration:none;color:inherit;transition:.16s ease}
  .docs-sheet-card:hover{color:inherit;border-color:color-mix(in srgb,var(--crm-accent) 38%, var(--crm-border));transform:translateY(-1px)}
  .docs-sheet-card p{margin:0}
  .docs-card-footer{margin-top:auto;display:flex;justify-content:space-between;gap:.75rem;align-items:center}
  .docs-preview{display:-webkit-box;-webkit-line-clamp:3;-webkit-box-orient:vertical;overflow:hidden;min-height:4.2em;line-height:1.45}
  .docs-empty{padding:2rem 1rem;border:1px dashed var(--crm-border);border-radius:1rem;text-align:center;color:var(--crm-muted)}
  .docs-filter-form .form-control,.docs-filter-form .form-select{background:var(--crm-surface-strong)}
  .docs-categories{display:flex;flex-wrap:wrap;gap:.55rem}
  details.docs-details{border:1px solid var(--crm-border);border-radius:1rem;background:rgba(255,255,255,.52)}
  details.docs-details summary{list-style:none;cursor:pointer;padding:1rem 1.05rem;font-weight:700;display:flex;justify-content:space-between;gap:1rem;align-items:center}
  details.docs-details summary::-webkit-details-marker{display:none}
  details.docs-details[open] summary{border-bottom:1px solid var(--crm-border)}
  .docs-details-body{padding:1rem 1.05rem}
  @media (max-width:1400px){
    .docs-layout{grid-template-columns:300px minmax(0,1fr)}
    .docs-workspace{grid-template-columns:1fr}
  }
  @media (max-width:1199px){
    .docs-stats{grid-template-columns:repeat(2,minmax(0,1fr))}
    .docs-layout{grid-template-columns:1fr}
    .docs-rail{position:static}
  }
  @media (max-width:767px){
    .docs-stats{grid-template-columns:1fr}
    .docs-book-meta{grid-template-columns:1fr}
  }
</style>
@endpush

@section('content')
@php
  $scopeLabels = [
      'my' => 'Мои',
      'shared' => 'Доступные',
      'all' => 'Все',
  ];
  $totalBooks = $workbooks->count();
  $totalSheets = $workbooks->sum(fn ($workbook) => (int) ($workbook->sheets_count ?? 0));
  $activeWorkbookRows = (int) ($selectedWorkbookSummary['row_count'] ?? 0);
  $selectedWorkbookTitle = $selectedWorkbook?->title ?? 'Книга не выбрана';
  $selectedCategoryCounts = (array) ($selectedWorkbookSummary['category_counts'] ?? []);
@endphp

<div class="docs-page">
  <section class="docs-surface docs-panel">
    <div class="docs-hero">
      <div class="docs-title">
        <div class="docs-inline mb-2">
          <span class="docs-badge"><i class="bi bi-collection"></i> Документы</span>
          <span class="docs-tag">Каталог книг и таблиц</span>
        </div>
        <h2>Документы и таблицы</h2>
        <p class="docs-muted">
          Здесь сотрудники работают с Excel-книгами как с отдельными каталогами: видят доступные таблицы, открывают их в полном размере,
          ставят статусы по строкам и создают задачи с напоминаниями.
        </p>
      </div>
      <div class="docs-toolbar-actions">
        @foreach($scopeLabels as $scopeKey => $scopeLabel)
          <a
            class="btn {{ $viewScope === $scopeKey ? 'btn-primary' : 'btn-outline-secondary' }}"
            href="{{ route('nonclosures.index', array_filter([
              'scope' => $scopeKey,
              'owner_id' => $ownerFilterId ?: null,
              'workbook' => $selectedWorkbook?->id,
            ])) }}">
            {{ $scopeLabel }}
          </a>
        @endforeach
      </div>
    </div>

    <div class="docs-stats">
      <div class="docs-stat">
        <div class="docs-muted small mb-2">Книг видно</div>
        <div class="docs-stat-value">{{ $totalBooks }}</div>
      </div>
      <div class="docs-stat">
        <div class="docs-muted small mb-2">Таблиц доступно</div>
        <div class="docs-stat-value">{{ $totalSheets }}</div>
      </div>
      <div class="docs-stat">
        <div class="docs-muted small mb-2">Строк в книге</div>
        <div class="docs-stat-value">{{ $activeWorkbookRows }}</div>
      </div>
      <div class="docs-stat">
        <div class="docs-muted small mb-2">Активная книга</div>
        <div class="docs-stat-value" style="font-size:1rem">{{ $selectedWorkbookTitle }}</div>
      </div>
    </div>
  </section>

  <div class="docs-layout">
    <aside class="docs-rail">
      @if($canContributeDocuments)
        <details class="docs-details" open>
          <summary>
            <span>Рабочий документ</span>
            <span class="docs-muted small">Заметки команды</span>
          </summary>
          <div class="docs-details-body">
            <form method="POST" action="{{ route('nonclosures.workspace.update') }}" id="workspaceForm">
              @csrf
              @method('PATCH')
              <input type="hidden" name="scope" value="{{ $viewScope }}">
              <input type="hidden" name="owner_id" value="{{ $ownerFilterId ?: '' }}">
              <input type="hidden" name="workbook" value="{{ $selectedWorkbook?->id }}">

              <div class="mb-3">
                <label class="form-label">Название документа</label>
                <input type="text" class="form-control" name="title" value="{{ old('title', $workspace->title) }}" maxlength="255" required>
              </div>

              <div class="docs-toolbar-mini">
                <button type="button" class="btn btn-sm btn-outline-secondary" data-doc-command="bold"><strong>B</strong></button>
                <button type="button" class="btn btn-sm btn-outline-secondary" data-doc-command="italic"><em>I</em></button>
                <button type="button" class="btn btn-sm btn-outline-secondary" data-doc-command="underline"><u>U</u></button>
                <button type="button" class="btn btn-sm btn-outline-secondary" data-doc-command="insertUnorderedList"><i class="bi bi-list-ul"></i></button>
                <button type="button" class="btn btn-sm btn-outline-secondary" data-doc-command="insertOrderedList"><i class="bi bi-list-ol"></i></button>
                <button type="button" class="btn btn-sm btn-outline-secondary" data-doc-command="removeFormat"><i class="bi bi-eraser"></i></button>
              </div>

              <div id="workspaceEditor" class="docs-editor" contenteditable="true">{!! old('document_html', $workspace->document_html ?? '') !!}</div>
              <textarea name="document_html" id="workspaceDocumentHtml" class="d-none">{{ old('document_html', $workspace->document_html) }}</textarea>

              <div class="d-flex justify-content-end mt-3">
                <button class="btn btn-primary" type="submit">Сохранить документ</button>
              </div>
            </form>
          </div>
        </details>
      @endif

      <section class="docs-surface docs-panel">
        <div class="docs-section-title">
          <div>
            <h5 class="mb-1">Книги</h5>
            <div class="docs-muted small">Слева всегда виден весь каталог, который доступен текущему пользователю.</div>
          </div>
          <span class="docs-badge">{{ $totalBooks }}</span>
        </div>

        @if($canManageDocuments)
          <form method="GET" action="{{ route('nonclosures.index') }}" class="docs-filter-form d-flex flex-column gap-3 mb-3">
            <input type="hidden" name="scope" value="{{ $viewScope }}">
            <div>
              <label class="form-label">Владелец книги</label>
              <select class="form-select" name="owner_id">
                <option value="0">Все владельцы</option>
                @foreach($activeUsers as $activeUser)
                  @if(($ownerStats[$activeUser->id] ?? 0) > 0)
                    <option value="{{ $activeUser->id }}" @selected($ownerFilterId === (int) $activeUser->id)>{{ $activeUser->name }} • {{ $ownerStats[$activeUser->id] }} книг</option>
                  @endif
                @endforeach
              </select>
            </div>
            <div class="d-flex gap-2">
              <button class="btn btn-primary flex-grow-1" type="submit">Применить</button>
              <a class="btn btn-outline-secondary" href="{{ route('nonclosures.index', ['scope' => $viewScope]) }}">Сбросить</a>
            </div>
          </form>
        @endif

        <div class="docs-book-list">
          @forelse($workbooks as $workbook)
            <a
              href="{{ route('nonclosures.index', array_filter([
                'scope' => $viewScope,
                'owner_id' => $ownerFilterId ?: null,
                'workbook' => $workbook->id,
              ])) }}"
              class="docs-book-card {{ $selectedWorkbook?->id === $workbook->id ? 'active' : '' }}">
              <div class="d-flex justify-content-between gap-3 align-items-start">
                <div>
                  <div class="fw-semibold">{{ $workbook->title }}</div>
                  <div class="docs-muted small mt-1">{{ $workbook->source_name ?: 'Загружено вручную' }}</div>
                </div>
                <span class="docs-badge">{{ (int) ($workbook->sheets_count ?? 0) }}</span>
              </div>
              <div class="docs-book-meta">
                <div class="docs-book-meta-item">
                  <span class="small docs-muted">Владелец</span>
                  <strong>{{ $workbook->owner?->name ?? 'Не назначен' }}</strong>
                </div>
                <div class="docs-book-meta-item">
                  <span class="small docs-muted">Импорт</span>
                  <strong>{{ optional($workbook->imported_at)->format('d.m.Y H:i') ?: 'без даты' }}</strong>
                </div>
              </div>
            </a>
          @empty
            <div class="docs-empty">Пока нет книг для выбранного фильтра.</div>
          @endforelse
        </div>
      </section>
    </aside>

    <section class="docs-main">
      @if($canContributeDocuments)
        <section class="docs-surface docs-panel">
          <div class="docs-toolbar">
            <div>
              <h5 class="mb-1">Импорт книги Excel</h5>
              <div class="docs-muted small">Загрузите `.xlsx`, и система разложит его по отдельным листам. Новая книга автоматически назначается вам как владельцу.</div>
            </div>
          </div>
          <form method="POST" action="{{ route('nonclosures.workbooks.import') }}" enctype="multipart/form-data" class="row g-3 mt-1">
            @csrf
            <div class="col-lg-4">
              <label class="form-label">Название книги</label>
              <input type="text" class="form-control" name="title" placeholder="Например, Контрагенты Мажор">
            </div>
            <div class="col-lg-5">
              <label class="form-label">Файл .xlsx</label>
              <input type="file" class="form-control" name="file" accept=".xlsx" required>
            </div>
            <div class="col-lg-3 d-flex align-items-end">
              <button class="btn btn-primary w-100" type="submit">Загрузить книгу</button>
            </div>
          </form>
        </section>
      @endif

      <section class="docs-surface docs-panel">
        <div class="docs-toolbar mb-3">
          <div>
            <div class="docs-inline mb-2">
              <span class="docs-badge"><i class="bi bi-book"></i> {{ $selectedWorkbookTitle }}</span>
              @if($selectedWorkbook)
                <span class="docs-tag">{{ $selectedWorkbook->owner?->name ?? 'Без владельца' }}</span>
                <span class="docs-tag">{{ $selectedWorkbook->source_name ?: 'Без исходного файла' }}</span>
              @endif
            </div>
            <h4 class="mb-1">Таблицы книги</h4>
            <div class="docs-muted small">
              Выберите нужную таблицу карточкой. Она откроется отдельно, на полном экране, с поиском, редактированием строк и задачами.
            </div>
          </div>
          @if($selectedWorkbook)
            <span class="docs-badge">{{ $sheets->count() }} таблиц</span>
          @endif
        </div>

        @if($selectedWorkbook)
          <div class="docs-workspace mb-3">
            <div class="docs-box">
              <div class="small docs-muted mb-2">Сводка по доступным таблицам книги</div>
              <div class="docs-inline mb-3">
                <span class="docs-tag">Листов: {{ $selectedWorkbookSummary['sheet_count'] ?? 0 }}</span>
                <span class="docs-tag">Строк: {{ $selectedWorkbookSummary['row_count'] ?? 0 }}</span>
              </div>
              <div class="docs-categories">
                @forelse($selectedCategoryCounts as $category => $count)
                  <span class="docs-tag">{{ $sheetCategories[$category] ?? $category }}: {{ $count }}</span>
                @empty
                  <span class="docs-muted small">Категории появятся после импорта листов.</span>
                @endforelse
              </div>
            </div>

            <div class="docs-access">
              <div class="docs-box">
                <div class="small docs-muted mb-2">Владелец книги</div>
                <div class="fw-semibold">{{ $selectedWorkbook->owner?->name ?? 'Не назначен' }}</div>
              </div>

              @if($canManageDocuments)
                <details class="docs-details" open>
                  <summary>
                    <span>Настройки доступа</span>
                    <span class="docs-muted small">Только для администратора и main operator</span>
                  </summary>
                  <div class="docs-details-body">
                    <form method="POST" action="{{ route('nonclosures.workbooks.access.update', $selectedWorkbook) }}" class="d-flex flex-column gap-3">
                      @csrf
                      @method('PATCH')
                      <input type="hidden" name="scope" value="{{ $viewScope }}">
                      <input type="hidden" name="owner_id" value="{{ $ownerFilterId ?: '' }}">
                      <input type="hidden" name="workbook" value="{{ $selectedWorkbook->id }}">
                      <div>
                        <label class="form-label">Владелец книги</label>
                        <select class="form-select" name="owner_user_id">
                          <option value="">Не назначен</option>
                          @foreach($activeUsers as $activeUser)
                            <option value="{{ $activeUser->id }}" @selected((int) $selectedWorkbook->owner_user_id === (int) $activeUser->id)>{{ $activeUser->name }} • {{ $activeUser->role }}</option>
                          @endforeach
                        </select>
                      </div>
                      <button class="btn btn-outline-primary align-self-start" type="submit">Сохранить владельца</button>
                    </form>
                  </div>
                </details>
              @endif
            </div>
          </div>

          <div class="docs-sheet-grid">
            @forelse($sheets as $sheet)
              <a
                href="{{ route('nonclosures.sheets.show', array_merge(
                  ['sheet' => $sheet->id],
                  array_filter([
                    'scope' => $viewScope,
                    'owner_id' => $ownerFilterId ?: null,
                    'workbook' => $selectedWorkbook->id,
                  ])
                )) }}"
                class="docs-sheet-card">
                <div class="d-flex justify-content-between gap-3 align-items-start">
                  <div>
                    <div class="fw-semibold">{{ $sheet->name }}</div>
                    <div class="docs-muted small mt-1">{{ $sheetCategories[$sheet->category] ?? $sheet->category }}</div>
                  </div>
                  <span class="docs-badge">{{ $sheet->row_count }} строк</span>
                </div>

                <div class="docs-inline">
                  <span class="docs-tag">{{ $sheet->column_count }} колонок</span>
                  <span class="docs-tag">Доступов: {{ $sheet->shared_users_count + ($sheet->owner_user_id ? 1 : 0) }}</span>
                </div>

                <p class="docs-preview">{{ $sheet->preview_text ?: 'Предпросмотр появится после импорта и обработки первых строк листа.' }}</p>

                <div class="docs-card-footer">
                  <div class="docs-muted small">
                    {{ $sheet->owner?->name ?? 'Без владельца' }}
                  </div>
                  <span class="btn btn-sm btn-outline-primary">Открыть таблицу</span>
                </div>
              </a>
            @empty
              <div class="docs-empty">В этой книге пока нет таблиц, доступных текущему пользователю.</div>
            @endforelse
          </div>
        @else
          <div class="docs-empty">
            Выберите книгу слева или загрузите новую Excel-книгу, чтобы открыть её таблицы.
          </div>
        @endif
      </section>
    </section>
  </div>
</div>

@if($canContributeDocuments)
<script>
(() => {
    const form = document.getElementById('workspaceForm');
    const editor = document.getElementById('workspaceEditor');
    const hiddenInput = document.getElementById('workspaceDocumentHtml');

    if (!form || !editor || !hiddenInput) {
        return;
    }

    document.querySelectorAll('[data-doc-command]').forEach((button) => {
        button.addEventListener('click', () => {
            const command = button.dataset.docCommand;
            const value = button.dataset.docValue || null;

            if (command === 'createLink') {
                const url = window.prompt('Введите ссылку');
                if (!url) {
                    return;
                }
                document.execCommand(command, false, url);
                return;
            }

            document.execCommand(command, false, value);
        });
    });

    form.addEventListener('submit', () => {
        hiddenInput.value = editor.innerHTML;
    });
})();
</script>
@endif
@endsection
