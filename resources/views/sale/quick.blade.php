@extends('layouts.app')

@section('content')
<style>
    .qs-wrap{ max-width:1100px; margin:0 auto; }
    .qs-search{
        display:flex; align-items:center; gap:.5rem; padding:.6rem 1rem;
        background:var(--crm-surface-strong); border:1px solid var(--crm-border);
        border-radius:999px; box-shadow:var(--crm-shadow); margin-bottom:1rem;
    }
    .qs-search input{ flex:1; border:0; background:transparent; outline:none; font-size:1.05rem; color:var(--crm-text); }
    .qs-search input::placeholder{ color:var(--crm-muted); }

    .qs-grid{ display:flex; flex-direction:column; gap:.5rem; margin-bottom:7rem; }
    .qs-card{
        display:flex; align-items:center; gap:.8rem; padding:.55rem .8rem;
        background:var(--crm-surface-strong); border:1px solid var(--crm-border);
        border-radius:.9rem;
    }
    .qs-photo{
        width:52px; height:52px; border-radius:10px; flex-shrink:0; overflow:hidden;
        display:flex; align-items:center; justify-content:center;
        background:var(--crm-surface); color:var(--crm-muted); font-weight:800; font-size:1.2rem;
        border:1px solid var(--crm-border);
    }
    .qs-photo img{ width:100%; height:100%; object-fit:cover; }
    .qs-info{ min-width:0; width:290px; flex-shrink:0; }
    .qs-name{ font-weight:700; font-size:.88rem; line-height:1.2; word-break:break-word; }
    .qs-art{ font-size:.7rem; color:var(--crm-muted); font-family:ui-monospace,Menlo,monospace; }
    .qs-sizes{ display:flex; flex-wrap:wrap; gap:.3rem; }
    .qs-size{
        border:1px solid var(--crm-border); background:var(--crm-surface);
        border-radius:.55rem; padding:.3rem .6rem; font-size:.85rem; font-weight:700;
        cursor:pointer; user-select:none; color:var(--crm-text);
    }
    .qs-size small{ display:block; font-size:.62rem; color:var(--crm-muted); font-weight:600; text-align:center; }
    .qs-size:hover{ border-color:var(--crm-accent); }
    .qs-size.sel{ background:var(--crm-accent); color:#fff; border-color:transparent; }
    .qs-size.sel small{ color:rgba(255,255,255,.8); }
    .qs-size.off{ opacity:.35; cursor:not-allowed; }

    /* нижняя панель оформления */
    .qs-panel{
        position:fixed; left:50%; bottom:1rem; transform:translateX(-50%); z-index:30;
        width:min(980px, calc(100vw - 2rem));
        background:var(--crm-surface-strong); border:1px solid var(--crm-accent);
        border-radius:1rem; box-shadow:0 18px 45px rgba(15,23,42,.25); padding:.8rem 1rem;
        display:none;
    }
    .qs-panel.on{ display:block; }
    .qs-panel .sel-name{ font-weight:800; }
    .qs-panel .fld label{ font-size:.68rem; color:var(--crm-muted); display:block; margin-bottom:.1rem; }
    .qs-panel input, .qs-panel select{
        border:1px solid var(--crm-border); background:var(--crm-surface); color:var(--crm-text);
        border-radius:.5rem; padding:.4rem .55rem; font-size:.95rem; outline:none; width:100%;
    }
    .qs-total{ font-size:1.5rem; font-weight:800; letter-spacing:-.02em; white-space:nowrap; }
    .qs-sell{ font-size:1.05rem; font-weight:700; padding:.55rem 1.6rem; border-radius:.7rem; }

    .qs-done{
        background:linear-gradient(135deg, rgba(16,185,129,.12), rgba(5,150,105,.06));
        border:1px solid rgba(16,185,129,.5); border-radius:1rem;
        padding:1rem 1.2rem; margin-bottom:1rem;
    }
</style>

@php
    $money = fn ($v) => number_format((float) $v, 0, ',', ' ');
    $done = session('quick_sale');
@endphp

<div class="qs-wrap">
    <div class="d-flex align-items-center justify-content-between mb-3 flex-wrap gap-2">
        <div>
            <h4 class="mb-0" style="letter-spacing:-.02em">💵 Продажа</h4>
            <div class="text-muted small">найдите модель, нажмите на размер и на кнопку «Продать» — всё остальное система сделает сама</div>
        </div>
    </div>

    @if($done)
        <div class="qs-done">
            <div class="d-flex align-items-center justify-content-between flex-wrap gap-2">
                <div>
                    <div class="fw-bold" style="font-size:1.1rem">✅ Продажа оформлена!</div>
                    <div class="small">{{ $done['name'] }} × {{ $done['qty'] }}@if($done['amount'] !== null) · {{ $money($done['amount']) }} ₽ @endif</div>
                    @if(!empty($done['low']))
                        <div class="small text-danger fw-semibold mt-1">⚠ Этот размер закончился — сообщите руководителю.</div>
                    @endif
                </div>
                <div class="d-flex gap-2 flex-wrap">
                    <a class="btn btn-primary" href="{{ route('deals.receipt', $done['deal_id']) }}" target="_blank">🖨️ Напечатать чек</a>
                    <a class="btn btn-outline-secondary" href="{{ route('deals.show', $done['deal_id']) }}">Открыть сделку</a>
                </div>
            </div>
        </div>
    @endif

    <div class="qs-search">
        <span style="color:var(--crm-muted);font-size:1.1rem">🔎</span>
        <input type="search" id="qsSearch" placeholder="Название модели или артикул… (можно сканером)" autofocus autocomplete="off">
    </div>

    <div class="qs-grid" id="qsGrid">
        @foreach($products as $prod)
            @php
                $search = mb_strtolower($prod['name'].' '.$prod['article'].' '.$prod['brand']);
                $letter = mb_strtoupper(mb_substr(trim($prod['brand']) ?: '?', 0, 1));
            @endphp
            <div class="qs-card" data-search="{{ $search }}">
                <div class="qs-photo">
                    @if($prod['image_url'])
                        <img src="{{ $prod['image_url'] }}" alt="">
                    @else
                        {{ $letter }}
                    @endif
                </div>
                <div class="qs-info">
                    <div class="qs-name">{{ $prod['name'] }}</div>
                    <div class="qs-art">{{ $prod['article'] }}</div>
                </div>
                <div class="qs-sizes">
                    @foreach($prod['sizes'] as $s)
                        @if($s['available'] > 0)
                            <span class="qs-size"
                                  data-item="{{ $s['id'] }}"
                                  data-size="{{ $s['size'] }}"
                                  data-price="{{ $s['price'] !== null ? $s['price'] : '' }}"
                                  data-name="{{ $prod['name'] }}"
                                  data-avail="{{ $s['available'] }}">
                                {{ $s['size'] }}
                                <small>{{ $s['available'] }} шт</small>
                            </span>
                        @else
                            <span class="qs-size off" title="Нет в наличии">{{ $s['size'] }}<small>нет</small></span>
                        @endif
                    @endforeach
                </div>
            </div>
        @endforeach
    </div>
</div>

{{-- Панель оформления --}}
<form method="POST" action="{{ route('sale.quick.store') }}" class="qs-panel" id="qsPanel">
    @csrf
    <input type="hidden" name="warehouse_item_id" id="fItem">
    <div class="row g-2 align-items-end">
        <div class="col-lg-3">
            <div class="sel-name" id="pName">—</div>
            <div class="small text-muted">размер <b id="pSize">—</b> · в наличии <b id="pAvail">—</b></div>
        </div>
        <div class="col-6 col-lg-1 fld">
            <label>Кол-во</label>
            <input type="number" name="qty" id="fQty" value="1" min="1">
        </div>
        <div class="col-6 col-lg-2 fld">
            <label>Цена за пару, ₽</label>
            <input type="number" name="price" id="fPrice" step="0.01" min="0" placeholder="0">
        </div>
        <div class="col-6 col-lg-2 fld">
            <label>Откуда клиент</label>
            <select name="source">
                <option value="">— не важно —</option>
                <option>Avito</option><option>Instagram</option><option>Telegram</option>
                <option>Сайт</option><option>Сарафан</option><option>Магазин</option><option>Другое</option>
            </select>
        </div>
        <div class="col-6 col-lg-2 fld">
            <label>Имя клиента (не обязательно)</label>
            <input type="text" name="client_name" maxlength="255">
        </div>
        <div class="col-6 col-lg-2 fld">
            <label>Телефон (не обязательно)</label>
            <input type="text" name="client_phone" maxlength="32">
        </div>
        <div class="col-6 col-lg-12 d-flex align-items-center justify-content-between gap-2 mt-2">
            <div class="qs-total">Итого: <span id="pTotal">—</span></div>
            <div class="d-flex gap-2">
                <button type="button" class="btn btn-outline-secondary" id="qsCancel">Отмена</button>
                <button type="submit" class="btn btn-success qs-sell">✅ Продать</button>
            </div>
        </div>
    </div>
</form>

<script>
(() => {
    const search = document.getElementById('qsSearch');
    const cards = Array.from(document.querySelectorAll('.qs-card'));
    const panel = document.getElementById('qsPanel');
    const fItem = document.getElementById('fItem');
    const fQty = document.getElementById('fQty');
    const fPrice = document.getElementById('fPrice');
    const pName = document.getElementById('pName');
    const pSize = document.getElementById('pSize');
    const pAvail = document.getElementById('pAvail');
    const pTotal = document.getElementById('pTotal');
    let selected = null;

    const fmt = (n) => new Intl.NumberFormat('ru-RU').format(Math.round(n)) + ' ₽';

    const updateTotal = () => {
        const q = parseInt(fQty.value || '1', 10);
        const p = parseFloat(fPrice.value || '0');
        pTotal.textContent = (p > 0 && q > 0) ? fmt(p * q) : '—';
    };

    const applyFilter = () => {
        const needle = (search.value || '').trim().toLowerCase();
        cards.forEach(c => {
            c.style.display = !needle || (c.dataset.search || '').includes(needle) ? '' : 'none';
        });
    };

    const select = (pill) => {
        document.querySelectorAll('.qs-size.sel').forEach(x => x.classList.remove('sel'));
        pill.classList.add('sel');
        selected = pill;
        fItem.value = pill.dataset.item;
        pName.textContent = pill.dataset.name;
        pSize.textContent = pill.dataset.size;
        pAvail.textContent = pill.dataset.avail + ' шт';
        fQty.value = 1;
        fQty.max = pill.dataset.avail;
        fPrice.value = pill.dataset.price || '';
        panel.classList.add('on');
        updateTotal();
        fPrice.focus();
    };

    document.querySelectorAll('.qs-size:not(.off)').forEach(pill => {
        pill.addEventListener('click', () => select(pill));
    });

    search.addEventListener('input', applyFilter);
    search.addEventListener('keydown', (e) => {
        if (e.key !== 'Enter') return;
        e.preventDefault();
        // сканер штрих-кода: если после фильтра видна одна карточка — выбрать её первый доступный размер
        const visible = cards.filter(c => c.style.display !== 'none');
        if (visible.length === 1) {
            const pill = visible[0].querySelector('.qs-size:not(.off)');
            if (pill) select(pill);
        }
    });

    fQty.addEventListener('input', updateTotal);
    fPrice.addEventListener('input', updateTotal);

    document.getElementById('qsCancel').addEventListener('click', () => {
        panel.classList.remove('on');
        if (selected) selected.classList.remove('sel');
        selected = null;
        search.focus();
    });

    panel.addEventListener('submit', (e) => {
        if (!fItem.value) { e.preventDefault(); alert('Сначала выберите товар и размер.'); }
    });
})();
</script>
@endsection
