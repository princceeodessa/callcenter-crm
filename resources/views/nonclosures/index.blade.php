@extends('layouts.app')

@push('styles')
<style>
  .docs-page{display:flex;flex-direction:column;gap:1.25rem}
  .docs-hero{display:grid;grid-template-columns:minmax(0,1.35fr) minmax(320px,.9fr);gap:1rem}
  .docs-surface{border:1px solid var(--crm-border);border-radius:1.25rem;background:var(--crm-surface-strong);box-shadow:var(--crm-shadow)}
  .docs-panel{padding:1.1rem 1.15rem}
  .docs-muted{color:var(--crm-muted)}
  .docs-inline{display:flex;flex-wrap:wrap;gap:.5rem}
  .docs-stats{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:.8rem}
  .docs-stat{padding:1rem;border:1px solid var(--crm-border);border-radius:1rem;background:linear-gradient(180deg,rgba(255,255,255,.72),rgba(255,255,255,.42))}
  .docs-stat-value{font-size:1.35rem;font-weight:700;line-height:1}
  .docs-editor{min-height:340px;padding:1rem 1.1rem;border:1px solid var(--crm-border);border-radius:1rem;background:var(--crm-surface-strong);overflow:auto;line-height:1.65}
  .docs-toolbar{display:flex;flex-wrap:wrap;gap:.45rem}
  .docs-grid{display:grid;grid-template-columns:repeat(12,minmax(0,1fr));gap:1rem}
  .docs-sidebar{display:flex;flex-direction:column;gap:1rem}
  .docs-book-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(240px,1fr));gap:1rem}
  .docs-book-card{display:flex;flex-direction:column;gap:.9rem;padding:1rem;border:1px solid var(--crm-border);border-radius:1.2rem;background:linear-gradient(160deg,rgba(79,70,229,.07),rgba(255,255,255,.9));text-decoration:none;color:inherit;transition:.18s ease}
  .docs-book-card:hover{transform:translateY(-2px);color:inherit;border-color:color-mix(in srgb,var(--crm-accent) 35%, var(--crm-border))}
  .docs-book-card.active{border-color:color-mix(in srgb,var(--crm-accent) 48%, var(--crm-border));box-shadow:0 14px 32px rgba(79,70,229,.14)}
  .docs-card-head{display:flex;justify-content:space-between;gap:.75rem;align-items:flex-start}
  .docs-badge{display:inline-flex;align-items:center;gap:.35rem;padding:.28rem .65rem;border-radius:999px;background:rgba(79,70,229,.10);color:var(--crm-accent);font-size:.74rem;font-weight:700}
  .docs-tag{display:inline-flex;align-items:center;padding:.26rem .6rem;border-radius:999px;background:rgba(15,23,42,.06);font-size:.73rem}
  .docs-sheet-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(280px,1fr));gap:1rem}
  .docs-sheet-card{display:flex;flex-direction:column;gap:.8rem;padding:1rem;border:1px solid var(--crm-border);border-radius:1.15rem;background:var(--crm-surface-strong);box-shadow:0 12px 28px rgba(15,23,42,.06);text-decoration:none;color:inherit;transition:.16s ease}
  .docs-sheet-card:hover{transform:translateY(-2px);color:inherit;border-color:color-mix(in srgb,var(--crm-accent) 40%, var(--crm-border))}
  .docs-card-footer{margin-top:auto;display:flex;justify-content:space-between;gap:.75rem;align-items:center}
  .docs-preview{display:-webkit-box;-webkit-line-clamp:3;-webkit-box-orient:vertical;overflow:hidden;min-height:3.9em}
  .docs-list{display:flex;flex-direction:column;gap:.7rem}
  .docs-access-box{padding:.85rem .95rem;border:1px solid var(--crm-border);border-radius:1rem;background:rgba(255,255,255,.55)}
  .docs-empty{padding:2rem 1rem;border:1px dashed var(--crm-border);border-radius:1rem;text-align:center;color:var(--crm-muted)}
  .docs-filter-form .form-select,
  .docs-filter-form .form-control{background:var(--crm-surface-strong)}
  @media (max-width:1199px){
    .docs-hero{grid-template-columns:1fr}
    .docs-stats{grid-template-columns:repeat(2,minmax(0,1fr))}
  }
  @media (max-width:767px){
    .docs-stats{grid-template-columns:1fr}
  }
</style>
@endpush

@section('content')
@php
  $scopeLabels = ['my' => 'Мои', 'shared' => 'Доступные', 'all' => 'Все'];
  $totalSheets = $workbooks->sum('sheets_count');
  $totalBooks = $workbooks->count();
  $selectedCategoryCounts = (array) ($selectedWorkbookSummary['category_counts'] ?? []);
  $categoryIcons = [
      'directory' => 'bi-journal-text',
      'summary' => 'bi-bar-chart-line',
      'analytics' => 'bi-graph-up-arrow',
      'products' => 'bi-grid-3x3-gap',
      'sales' => 'bi-bag-check',
      'other' => 'bi-table',
  ];
@endphp

<div class="docs-page">
  <div class="docs-surface docs-panel">
    <div class="d-flex flex-column flex-xl-row justify-content-between gap-3 align-items-xl-center">
      <div>
        <div class="docs-inline mb-2">
          <span class="docs-badge"><i class="bi bi-collection"></i> Документы</span>
          <span class="docs-tag">Каталог таблиц</span>
        </div>
        <h2 class="mb-1">Документы и таблицы</h2>
        <div class="docs-muted">
          Здесь хранятся книги Excel, владельцы таблиц и общий рабочий документ. На главной странице только каталог,
          а каждая таблица открывается отдельно, в полном размере.
        </div>
      </div>
      <div class="docs-inline">
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

    <div class="docs-stats mt-4">
      <div class="docs-stat">
        <div class="docs-muted small mb-2">Рабочий документ</div>
        <div class="docs-stat-value">{{ $workspace->title }}</div>
      </div>
      <div class="docs-stat">
        <div class="docs-muted small mb-2">Книги</div>
        <div class="docs-stat-value">{{ $totalBooks }}</div>
      </div>
      <div class="docs-stat">
        <div class="docs-muted small mb-2">Таблицы</div>
        <div class="docs-stat-value">{{ $totalSheets }}</div>
      </div>
      <div class="docs-stat">
        <div class="docs-muted small mb-2">Активная книга</div>
        <div class="docs-stat-value" style="font-size:1rem">{{ $selectedWorkbook?->title ?? 'Не выбрана' }}</div>
      </div>
    </div>
  </div>

  <div class="docs-hero">
    <div class="docs-surface docs-panel">
      <div class="d-flex justify-content-between align-items-start gap-3 flex-wrap mb-3">
        <div>
          <h4 class="mb-1">Общий документ</h4>
          <div class="docs-muted small">
            Для правил, заметок по контрагентам, регламентов и общей структуры работы с таблицами.
          </div>
        </div>
        <span class="docs-badge">Workspace</span>
      </div>

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

        <div class="docs-toolbar mb-3">
          <button type="button" class="btn btn-sm btn-outline-secondary" data-doc-command="bold"><strong>B</strong></button>
          <button type="button" class="btn btn-sm btn-outline-secondary" data-doc-command="italic"><em>I</em></button>
          <button type="button" class="btn btn-sm btn-outline-secondary" data-doc-command="underline"><u>U</u></button>
          <button type="button" class="btn btn-sm btn-outline-secondary" data-doc-command="formatBlock" data-doc-value="h3">H3</button>
          <button type="button" class="btn btn-sm btn-outline-secondary" data-doc-command="insertUnorderedList"><i class="bi bi-list-ul"></i></button>
          <button type="button" class="btn btn-sm btn-outline-secondary" data-doc-command="insertOrderedList"><i class="bi bi-list-ol"></i></button>
          <button type="button" class="btn btn-sm btn-outline-secondary" data-doc-command="createLink"><i class="bi bi-link-45deg"></i></button>
          <button type="button" class="btn btn-sm btn-outline-secondary" data-doc-command="removeFormat"><i class="bi bi-eraser"></i></button>
        </div>

        <div id="workspaceEditor" class="docs-editor" contenteditable="true">{!! old('document_html', $workspace->document_html ?? '') !!}</div>
        <textarea name="document_html" id="workspaceDocumentHtml" class="d-none">{{ old('document_html', $workspace->document_html) }}</textarea>

        <div class="mt-3 d-flex justify-content-end">
          <button class="btn btn-primary" type="submit">Сохранить документ</button>
        </div>
      </form>
    </div>

    <div class="docs-sidebar">
      <div class="docs-surface docs-panel">
        <h4 class="mb-1">Фильтры и доступ</h4>
        <div class="docs-muted small mb-3">Сначала выбираете набор книг, потом открываете нужную таблицу отдельной страницей.</div>

        @if($canManageDocuments)
          <form method="GET" action="{{ route('nonclosures.index') }}" class="docs-filter-form d-flex flex-column gap-3">
            <input type="hidden" name="scope" value="{{ $viewScope }}">
            <div>
              <label class="form-label">Владелец книги</label>
              <select class="form-select" name="owner_id">
                <option value="0">Все владельцы</option>
                @foreach($activeUsers as $user)
                  @if(($ownerStats[$user->id] ?? 0) > 0)
                    <option value="{{ $user->id }}" @selected($ownerFilterId === (int) $user->id)>{{ $user->name }} · {{ $ownerStats[$user->id] }} книг</option>
                  @endif
                @endforeach
              </select>
            </div>
            <div class="d-flex gap-2">
              <button class="btn btn-primary flex-grow-1" type="submit">Применить</button>
              <a class="btn btn-outline-secondary" href="{{ route('nonclosures.index', ['scope' => $viewScope]) }}">Сбросить</a>
            </div>
          </form>
        @else
          <div class="docs-access-box">
            <div class="small docs-muted">Режим</div>
            <div class="fw-semibold">Каталог доступных таблиц</div>
          </div>
        @endif
      </div>

      @if($canManageDocuments)
        <div class="docs-surface docs-panel">
          <h4 class="mb-1">Импорт книги Excel</h4>
          <div class="docs-muted small mb-3">
            Загружайте большие книги вроде «Контрагенты Мажор» целиком. Система сама разобьёт их по листам.
          </div>
          <form method="POST" action="{{ route('nonclosures.workbooks.import') }}" enctype="multipart/form-data" class="d-flex flex-column gap-3">
            @csrf
            <div>
              <label class="form-label">Название книги</label>
              <input type="text" class="form-control" name="title" placeholder="Например, Контрагенты Мажор">
            </div>
            <div>
              <label class="form-label">Файл .xlsx</label>
              <input type="file" class="form-control" name="file" accept=".xlsx" required>
            </div>
            <button class="btn btn-primary align-self-start" type="submit">Импортировать книгу</button>
          </form>
        </div>
      @endif
    </div>
  </div>

  <div class="docs-surface docs-panel">
    <div class="d-flex justify-content-between align-items-start gap-3 flex-wrap mb-3">
      <div>
        <h4 class="mb-1">Книги</h4>
        <div class="docs-muted small">Каталог импортированных книг. Выберите книгу, чтобы увидеть её таблицы-карточки.</div>
      </div>
      @if($selectedWorkbook)
        <span class="docs-badge"><i class="bi bi-book"></i> {{ $selectedWorkbook->title }}</span>
      @endif
    </div>

    <div class="docs-book-grid">
      @forelse($workbooks as $workbook)
        <a
          href="{{ route('nonclosures.index', array_filter([
            'scope' => $viewScope,
            'owner_id' => $ownerFilterId ?: null,
            'workbook' => $workbook->id,
          ])) }}"
          class="docs-book-card {{ $selectedWorkbook?->id === $workbook->id ? 'active' : '' }}">
          <div class="docs-card-head">
            <div>
              <div class="fw-semibold">{{ $workbook->title }}</div>
              <div class="small docs-muted mt-1">{{ $workbook->source_name ?: 'Загружено вручную' }}</div>
            </div>
            <span class="docs-badge">{{ $workbook->sheets_count }}</span>
          </div>
          <div class="docs-inline">
            @foreach((array) ($workbook->summary['category_counts'] ?? []) as $category => $count)
              <span class="docs-tag">{{ $sheetCategories[$category] ?? $category }}: {{ $count }}</span>
            @endforeach
          </div>
          <div class="small docs-muted">
            Владелец: {{ $workbook->owner?->name ?? 'Не назначен' }}
          </div>
          <div class="small docs-muted">
            Импорт: {{ optional($workbook->imported_at)->format('d.m.Y H:i') ?: 'нет даты' }}
          </div>
        </a>
      @empty
        <div class="docs-empty">Пока нет книг для выбранного фильтра.</div>
      @endforelse
    </div>
  </div>

  @if($selectedWorkbook)
    <div class="docs-grid">
      <div class="col-12 col-xl-4 docs-surface docs-panel" style="grid-column:span 4">
        <div class="d-flex justify-content-between align-items-start gap-3 mb-3">
          <div>
            <h4 class="mb-1">{{ $selectedWorkbook->title }}</h4>
            <div class="docs-muted small">{{ $selectedWorkbook->source_name ?: 'Без исходного имени файла' }}</div>
          </div>
          <span class="docs-badge">{{ $selectedWorkbook->sheets_count }} листов</span>
        </div>

        <div class="docs-list">
          <div class="docs-access-box">
            <div class="small docs-muted">Владелец книги</div>
            <div class="fw-semibold">{{ $selectedWorkbook->owner?->name ?? 'Не назначен' }}</div>
          </div>

          <div class="docs-access-box">
            <div class="small docs-muted mb-2">Категории внутри книги</div>
            <div class="docs-inline">
              @forelse($selectedCategoryCounts as $category => $count)
                <span class="docs-tag">{{ $sheetCategories[$category] ?? $category }}: {{ $count }}</span>
              @empty
                <span class="docs-muted small">Категории появятся после импорта листов.</span>
              @endforelse
            </div>
          </div>

          @if($canManageDocuments)
            <form method="POST" action="{{ route('nonclosures.workbooks.access.update', $selectedWorkbook) }}" class="docs-access-box d-flex flex-column gap-3">
              @csrf
              @method('PATCH')
              <input type="hidden" name="scope" value="{{ $viewScope }}">
              <input type="hidden" name="owner_id" value="{{ $ownerFilterId ?: '' }}">
              <input type="hidden" name="workbook" value="{{ $selectedWorkbook->id }}">
              <div>
                <label class="form-label">Назначить владельца книги</label>
                <select class="form-select" name="owner_user_id">
                  <option value="">Не назначен</option>
                  @foreach($activeUsers as $user)
                    <option value="{{ $user->id }}" @selected((int) $selectedWorkbook->owner_user_id === (int) $user->id)>{{ $user->name }} · {{ $user->role }}</option>
                  @endforeach
                </select>
              </div>
              <button class="btn btn-primary align-self-start" type="submit">Сохранить владельца</button>
            </form>
          @endif
        </div>
      </div>

      <div class="col-12 col-xl-8 docs-surface docs-panel" style="grid-column:span 8">
        <div class="d-flex justify-content-between align-items-start gap-3 flex-wrap mb-3">
          <div>
            <h4 class="mb-1">Таблицы книги</h4>
            <div class="docs-muted small">
              Таблицы показываются карточками. Открытие любой карточки переводит на отдельную полноформатную страницу таблицы.
            </div>
          </div>
          <span class="docs-badge">{{ $sheets->count() }} таблиц</span>
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
              <div class="docs-card-head">
                <div>
                  <div class="fw-semibold">{{ $sheet->name }}</div>
                  <div class="small docs-muted mt-1">{{ $sheet->owner?->name ?? 'Не назначен' }}</div>
                </div>
                <span class="docs-badge">
                  <i class="bi {{ $categoryIcons[$sheet->category] ?? 'bi-table' }}"></i>
                  {{ $sheetCategories[$sheet->category] ?? $sheet->category }}
                </span>
              </div>

              <div class="docs-inline">
                <span class="docs-tag">{{ $sheet->row_count }} строк</span>
                <span class="docs-tag">{{ $sheet->column_count }} колонок</span>
                <span class="docs-tag">Доступов: {{ $sheet->shared_users_count ?? $sheet->sharedUsers->count() }}</span>
              </div>

              <div class="docs-preview docs-muted small">
                {{ \Illuminate\Support\Str::limit($sheet->preview_text ?: 'Откройте таблицу для полноформатного просмотра и работы с доступом.', 160) }}
              </div>

              <div class="docs-card-footer">
                <span class="docs-muted small">Открывается отдельной страницей</span>
                <span class="btn btn-sm btn-outline-primary">Открыть таблицу</span>
              </div>
            </a>
          @empty
            <div class="docs-empty">
              В этой книге пока нет доступных таблиц.
            </div>
          @endforelse
        </div>
      </div>
    </div>
  @else
    <div class="docs-surface docs-panel">
      <div class="docs-empty">
        Выберите книгу выше, и здесь появится каталог её таблиц.
      </div>
    </div>
  @endif
</div>
@endsection

@push('scripts')
<script>
(() => {
    const form = document.getElementById('workspaceForm');
    const editor = document.getElementById('workspaceEditor');
    const textarea = document.getElementById('workspaceDocumentHtml');

    if (!form || !editor || !textarea) {
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
                editor.focus();
                return;
            }

            document.execCommand(command, false, value);
            editor.focus();
        });
    });

    form.addEventListener('submit', () => {
        textarea.value = editor.innerHTML;
    });
})();
</script>
@endpush
