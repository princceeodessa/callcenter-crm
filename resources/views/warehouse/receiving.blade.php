@extends('layouts.app')

@section('content')
<style>
    .scan-box{ background:var(--crm-surface-strong); border:1px solid var(--crm-border); border-radius:1rem; padding:1.5rem; box-shadow:var(--crm-shadow); }
    .scan-input{ font-size:1.5rem !important; padding:1rem 1.2rem !important; font-family:ui-monospace, "SFMono-Regular", Menlo, monospace; letter-spacing:.05em; border-radius:.7rem; }
    .scan-input:focus{ box-shadow:0 0 0 3px rgba(79,70,229,.25); }
    .result-ok{ color:#059669; font-weight:700; }
    .result-warn{ color:#d97706; font-weight:700; }
    .result-err{ color:#dc2626; font-weight:700; }
    .mode-tab{ padding:.35rem .8rem; border-radius:999px; border:1px solid var(--crm-border); background:var(--crm-surface); cursor:pointer; text-decoration:none; color:var(--crm-text); font-size:.85rem; }
    .mode-tab.active{ background:var(--crm-accent); color:#fff; border-color:transparent; }
</style>

<div class="d-flex align-items-center justify-content-between mb-3 flex-wrap gap-2">
    <div>
        <h4 class="mb-0">Приёмка со сканером</h4>
        <div class="text-muted small">поставьте курсор в поле — ТСД/сканер сам введёт код и нажмёт Enter</div>
    </div>
    <a class="btn btn-sm btn-outline-secondary" href="{{ route('warehouse.index') }}">← К складу</a>
</div>

<div class="scan-box mb-3">
    <form method="POST" action="{{ route('warehouse.receiving.scan') }}" id="scanForm">
        @csrf
        <div class="d-flex gap-2 mb-3">
            <label class="mode-tab {{ (session('scan_mode', 'article') === 'article') ? 'active' : '' }}">
                <input type="radio" name="mode" value="article" {{ (session('scan_mode', 'article') === 'article') ? 'checked' : '' }} hidden>
                📦 Артикул (K-XXXXX)
            </label>
            <label class="mode-tab {{ (session('scan_mode') === 'mark') ? 'active' : '' }}">
                <input type="radio" name="mode" value="mark" {{ (session('scan_mode') === 'mark') ? 'checked' : '' }} hidden>
                🏷️ Код маркировки (Честный знак)
            </label>
        </div>
        <div class="row g-2 align-items-end">
            <div class="col-md-9">
                <label class="form-label small mb-1">Код со сканера</label>
                <input type="text" name="code" class="form-control form-control-lg scan-input" autofocus autocomplete="off" placeholder="Отсканируйте штрих-код…">
            </div>
            <div class="col-md-2">
                <label class="form-label small mb-1">Кол-во</label>
                <input type="number" name="quantity" class="form-control form-control-lg" min="1" value="1">
            </div>
            <div class="col-md-1"><button class="btn btn-success btn-lg w-100">✓</button></div>
        </div>
    </form>

    @if(session('receive_last'))
        <div class="mt-3">
            <div class="small text-muted">Последний код:</div>
            <div class="d-flex align-items-center gap-2">
                <code>{{ session('receive_last') }}</code>
                <span>→</span>
                @php($r = session('receive_result'))
                @if(str_starts_with($r, 'RECEIVED:'))<span class="result-ok">✓ Принято на склад: {{ substr($r, 9) }}</span>
                @elseif(str_starts_with($r, 'MARK_OK:'))<span class="result-ok">✓ Код маркировки привязан к {{ substr($r, 8) }}</span>
                @elseif($r === 'NOT_FOUND')<span class="result-err">✗ Артикул не найден</span>
                @elseif(str_starts_with($r, 'NO_SIZES:'))<span class="result-warn">Товар найден, но нет позиций: {{ substr($r, 9) }}</span>
                @elseif($r === 'MARK_DUP')<span class="result-warn">Такой код уже есть в системе</span>
                @elseif($r === 'MARK_NO_TARGET')<span class="result-err">Сначала отсканируйте артикул товара</span>
                @else<span>{{ $r }}</span>
                @endif
            </div>
        </div>
    @endif
</div>

<h6 class="mb-2">Последние приходы</h6>
<div class="card shadow-sm">
    <div class="table-responsive">
        <table class="table table-sm align-middle mb-0">
            <thead><tr><th>Когда</th><th>Позиция</th><th class="text-end">Кол-во</th><th>Примечание</th></tr></thead>
            <tbody>
                @forelse($recent as $m)
                    <tr>
                        <td class="text-muted small">{{ optional($m->created_at)->format('d.m H:i') }}</td>
                        <td>{{ $m->item?->display_name ?? '—' }}</td>
                        <td class="text-end fw-semibold text-success">+{{ $m->quantity }}</td>
                        <td class="small text-muted">{{ $m->note }}</td>
                    </tr>
                @empty
                    <tr><td colspan="4" class="text-muted small text-center py-3">Пока приёмок не было.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>

<script>
    // Автофокус на input после сабмита; клики по вкладкам — плавно выделяют выбранный режим
    document.querySelectorAll('.mode-tab').forEach(t => {
        t.addEventListener('click', () => {
            document.querySelectorAll('.mode-tab').forEach(x => x.classList.remove('active'));
            t.classList.add('active');
            t.querySelector('input').checked = true;
            document.querySelector('.scan-input').focus();
        });
    });
    document.querySelector('.scan-input')?.focus();
</script>
@endsection
