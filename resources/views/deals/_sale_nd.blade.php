{{-- Карточка сделки кроссовок (_show_nordika в каркасе кроссовок): «Склад · что продаём» — тот же блок, что был в прежней карточке:
     товар со склада, пары, источник (deals.sale-item), состояние списания, прибыль (руководителю), белая/серая и оплата для 1С
     (deals.sale-flags), чек и возврат, коды «Честного знака» (deals.marks.add). --}}
@php
  $saleSources = \App\Models\Deal::SNEAKER_SOURCES;
  $saleSeesCost = \App\Support\Ui\Sneakers::seesCost();
  $saleMarks = \App\Models\StockMark::where('account_id', $deal->account_id)->where('deal_id', $deal->id)->get();
@endphp
<div class="nd-deal-sec nd-sale" id="ndSale">
  <div class="nd-lbl">Склад · что продаём</div>
  <form method="POST" action="{{ route('deals.sale-item', $deal) }}" class="nd-deal-form nd-sale-form">
    @csrf
    <div class="nd-field">
      <label>Товар со склада</label>
      <select name="warehouse_item_id" class="nd-deal-select" data-sale-item>
        <option value="">— не выбрано —</option>
        @foreach($warehouseItems as $wi)
          <option value="{{ $wi->id }}" @selected((int) $deal->warehouse_item_id === $wi->id)>{{ $wi->display_name }} (доступно {{ $wi->available }} из {{ $wi->quantity }}{{ $wi->sale_price ? ', '.number_format((float) $wi->sale_price, 0, ',', ' ').' ₽' : '' }})</option>
        @endforeach
      </select>
    </div>
    <div class="nd-deal-form-2">
      <div class="nd-field"><label>Пар</label><input type="number" name="sold_quantity" min="1" value="{{ $deal->sold_quantity ?? 1 }}"></div>
      <div class="nd-field">
        <label>Источник</label>
        <select name="manual_source">
          <option value="">— не указан —</option>
          @if($deal->manual_source && ! in_array($deal->manual_source, $saleSources, true))
            <option value="{{ $deal->manual_source }}" selected>{{ $deal->manual_source }}</option>
          @endif
          @foreach($saleSources as $so)
            <option value="{{ $so }}" @selected($deal->manual_source === $so)>{{ $so }}</option>
          @endforeach
        </select>
      </div>
    </div>
    <button class="nd-btn nd-sm nd-primary">Сохранить</button>
  </form>

  <div class="nd-deal-note @if($deal->stock_deducted_at && ! $deal->warehouse_item_id && ! $deal->stock_link_skipped_at) nd-tone-amber @endif">
    @if($deal->returned_at)
      Возврат оформлен {{ $deal->returned_at->format('d.m.Y H:i') }} — пары на складе.
    @elseif($deal->stock_deducted_at && ! $deal->warehouse_item_id && ! $deal->stock_link_skipped_at)
      <b>Продано {{ $deal->stock_deducted_at->format('d.m.Y H:i') }}, но пара со склада не списана</b> — продажа внесена без размера. Выберите товар
      и нажмите «Сохранить»: пара спишется, дата продажи не изменится. <a href="{{ route('warehouse.unlinked') }}#sale-{{ $deal->id }}">Все такие продажи →</a>
    @elseif($deal->stock_deducted_at && ! $deal->warehouse_item_id)
      Продано {{ $deal->stock_deducted_at->format('d.m.Y H:i') }}. Со склада не списывали — при сверке отмечено «списывать не нужно».
    @elseif($deal->stock_deducted_at)
      <span class="nd-sale-ok">Списано со склада {{ $deal->stock_deducted_at->format('d.m.Y H:i') }}.</span>
    @elseif($deal->stock_reserved_at)
      Зарезервировано {{ $deal->stock_reserved_at->format('d.m.Y H:i') }}. Списание — при «Продано».
    @else
      Резерв — на этапе «Бронь / Счёт», списание — на «Продано».
    @endif
  </div>

  @if($deal->stock_deducted_at && ! $deal->returned_at && $saleSeesCost)
    <div class="nd-deal-note">
      @if($deal->sale_profit !== null)
        Чистая прибыль: <b class="{{ $deal->sale_profit >= 0 ? 'nd-sale-ok' : 'nd-sale-bad' }}">{{ ($deal->sale_profit < 0 ? '−' : '').number_format(abs($deal->sale_profit), 0, ',', ' ') }} ₽</b>@if($deal->sale_margin_percent !== null) · маржа {{ $deal->sale_margin_percent }}%@endif
      @else
        Прибыль не рассчитана — укажите закупочную цену товара на складе.
      @endif
    </div>
  @endif

  @if($deal->warehouse_item_id && ($deal->stock_deducted_at || $deal->returned_at))
    {{-- для 1С «Обувь»: белые пары уходят в отчёт о розничных продажах, наличные и безнал раздельно --}}
    <form method="POST" action="{{ route('deals.sale-flags', $deal) }}" class="nd-deal-form nd-sale-flags">
      @csrf
      <div class="nd-deal-form-2">
        <div class="nd-field"><label>Пара</label>
          <select name="white">
            <option value="" @selected($deal->stock_white === null)>— не отмечено —</option>
            <option value="1" @selected($deal->stock_white === true)>белая (в 1С)</option>
            <option value="0" @selected($deal->stock_white === false)>серая</option>
          </select>
        </div>
        <div class="nd-field"><label>Оплата</label>
          <select name="payment">
            <option value="">— не указана —</option>
            @foreach(\App\Models\Deal::PAYMENT_METHODS as $pv => $pl)
              <option value="{{ $pv }}" @selected($deal->payment_method === $pv)>{{ $pl }}</option>
            @endforeach
          </select>
        </div>
      </div>
      <div class="nd-sale-row">
        <button class="nd-btn nd-sm">Сохранить</button>
        @foreach($deal->onecDocs as $od)
          <span class="nd-tag nd-tone-{{ $od->status === 'done' ? 'green' : ($od->status === 'error' ? 'red' : 'amber') }}">1С, {{ \App\Models\OnecSaleDoc::KIND_LABELS[$od->kind] ?? $od->kind }}: {{ $od->onec_number ? 'отчёт № '.$od->onec_number : (\App\Models\OnecSaleDoc::STATUS_LABELS[$od->status] ?? $od->status) }}</span>
        @endforeach
      </div>
    </form>
  @endif

  @if($deal->stock_deducted_at && ! $deal->returned_at)
    <div class="nd-sale-row">
      <a class="nd-btn nd-sm" href="{{ route('deals.receipt', $deal) }}" target="_blank" rel="noopener">Печать чека</a>
      @if($deal->warehouse_item_id)
        <form method="POST" action="{{ route('deals.return', $deal) }}" onsubmit="return confirm('Оформить возврат? Пары вернутся на склад, продажа отменится.')">
          @csrf
          <button class="nd-btn nd-sm">Оформить возврат</button>
        </form>
      @endif
    </div>
  @endif

  <details class="nd-deal-addtask nd-sale-marks" @if($saleMarks->isNotEmpty()) open @endif>
    <summary>Коды маркировки («Честный знак»)@if($saleMarks->isNotEmpty()) · {{ $saleMarks->count() }}@endif</summary>
    <form method="POST" action="{{ route('deals.marks.add', $deal) }}" class="nd-deal-form">
      @csrf
      <div class="nd-field"><textarea name="codes" rows="2" class="nd-mono" placeholder="Отсканируйте или вставьте коды — по одному в строке"></textarea></div>
      <button type="submit" class="nd-btn nd-sm">Добавить коды</button>
    </form>
    @foreach($saleMarks as $mk)
      <div class="nd-sale-mark"><code>{{ \Illuminate\Support\Str::limit($mk->code, 44) }}</code><span class="nd-tag nd-tone-{{ $mk->status === 'sold' ? 'green' : ($mk->status === 'returned' ? 'blue' : 'grey') }}">{{ ['sold' => 'продан', 'returned' => 'возврат', 'in_stock' => 'на складе'][$mk->status] ?? $mk->status }}</span></div>
    @endforeach
  </details>
</div>
