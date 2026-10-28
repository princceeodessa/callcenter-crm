{{-- Поля закупки (создание и карточка) — разметка «Нордика»; имена полей прежние (PurchaseController::validateData) --}}
@php
    $p = $purchase ?? null;
@endphp
<div class="ns-pf">
    <div class="ns-field ns-pf-wide"><label>Название *</label><input type="text" name="title" required maxlength="255" value="{{ old('title', $p->title ?? '') }}" placeholder="Напр. Nike Air Max 90"></div>
    <div class="ns-field"><label>Этап *</label>
        <select name="purchase_stage_id" required>
            @foreach($stages as $stage)
                <option value="{{ $stage->id }}" @selected((int) old('purchase_stage_id', $p->purchase_stage_id ?? optional($stages->first())->id) === $stage->id)>{{ $stage->name }}</option>
            @endforeach
        </select>
    </div>
    <div class="ns-field"><label>Бренд</label><input type="text" name="brand" maxlength="255" value="{{ old('brand', $p->brand ?? '') }}"></div>
    <div class="ns-field"><label>Модель</label><input type="text" name="model" maxlength="255" value="{{ old('model', $p->model ?? '') }}"></div>
    <div class="ns-field"><label>Размер</label><input type="text" name="size" maxlength="50" value="{{ old('size', $p->size ?? '') }}"></div>
    <div class="ns-field"><label>Пар</label><input type="number" name="quantity" min="1" value="{{ old('quantity', $p->quantity ?? 1) }}"></div>
    <div class="ns-field"><label>Поставщик</label><input type="text" name="supplier" maxlength="255" value="{{ old('supplier', $p->supplier ?? '') }}"></div>
    <div class="ns-field"><label>Артикул / ссылка на заказ</label><input type="text" name="article" maxlength="255" value="{{ old('article', $p->article ?? '') }}"></div>
    <div class="ns-field"><label>Закупочная цена, ₽</label><input type="number" step="0.01" min="0" name="cost" value="{{ old('cost', $p->cost ?? '') }}"></div>
    <div class="ns-field"><label>Ожидаемая продажа, ₽</label><input type="number" step="0.01" min="0" name="expected_sale_price" value="{{ old('expected_sale_price', $p->expected_sale_price ?? '') }}"></div>
    <div class="ns-field"><label>Ответственный</label>
        <select name="responsible_user_id">
            <option value="">— не назначен —</option>
            @foreach($users as $u)
                <option value="{{ $u->id }}" @selected((int) old('responsible_user_id', $p->responsible_user_id ?? 0) === $u->id)>{{ $u->name }}</option>
            @endforeach
        </select>
    </div>
    <label class="ns-check ns-pf-check"><input type="checkbox" name="is_white" value="1" @checked(old('is_white', $p->is_white ?? false))>Заказано в белую</label>
    <div class="ns-field ns-pf-full"><label>Заметки</label><textarea name="notes" rows="3">{{ old('notes', $p->notes ?? '') }}</textarea></div>
</div>
