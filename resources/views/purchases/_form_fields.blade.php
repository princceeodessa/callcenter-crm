@php($p = $purchase ?? null)
<div class="row g-3">
    <div class="col-md-8">
        <label class="form-label">Название *</label>
        <input type="text" name="title" class="form-control" required maxlength="255"
               value="{{ old('title', $p->title ?? '') }}" placeholder="Напр. Nike Air Max 90">
    </div>
    <div class="col-md-4">
        <label class="form-label">Стадия *</label>
        <select name="purchase_stage_id" class="form-select" required>
            @foreach($stages as $stage)
                <option value="{{ $stage->id }}" @selected((int) old('purchase_stage_id', $p->purchase_stage_id ?? optional($stages->first())->id) === $stage->id)>{{ $stage->name }}</option>
            @endforeach
        </select>
    </div>
    <div class="col-md-4">
        <label class="form-label">Бренд</label>
        <input type="text" name="brand" class="form-control" maxlength="255" value="{{ old('brand', $p->brand ?? '') }}">
    </div>
    <div class="col-md-4">
        <label class="form-label">Модель</label>
        <input type="text" name="model" class="form-control" maxlength="255" value="{{ old('model', $p->model ?? '') }}">
    </div>
    <div class="col-md-2">
        <label class="form-label">Размер</label>
        <input type="text" name="size" class="form-control" maxlength="50" value="{{ old('size', $p->size ?? '') }}">
    </div>
    <div class="col-md-2">
        <label class="form-label">Кол-во пар</label>
        <input type="number" name="quantity" class="form-control" min="1" value="{{ old('quantity', $p->quantity ?? 1) }}">
    </div>
    <div class="col-md-6">
        <label class="form-label">Поставщик</label>
        <input type="text" name="supplier" class="form-control" maxlength="255" value="{{ old('supplier', $p->supplier ?? '') }}">
    </div>
    <div class="col-md-6">
        <label class="form-label">Артикул / ссылка на заказ</label>
        <input type="text" name="article" class="form-control" maxlength="255" value="{{ old('article', $p->article ?? '') }}">
    </div>
    <div class="col-12">
        <div class="form-check">
            <input type="checkbox" name="is_white" value="1" class="form-check-input" id="is_white" @checked(old('is_white', $p->is_white ?? false))>
            <label class="form-check-label" for="is_white">Заказано в белую</label>
        </div>
    </div>
    <div class="col-md-4">
        <label class="form-label">Закупочная цена, ₽</label>
        <input type="number" step="0.01" min="0" name="cost" class="form-control" value="{{ old('cost', $p->cost ?? '') }}">
    </div>
    <div class="col-md-4">
        <label class="form-label">Ожидаемая продажа, ₽</label>
        <input type="number" step="0.01" min="0" name="expected_sale_price" class="form-control" value="{{ old('expected_sale_price', $p->expected_sale_price ?? '') }}">
    </div>
    <div class="col-md-4">
        <label class="form-label">Ответственный</label>
        <select name="responsible_user_id" class="form-select">
            <option value="">— не назначен —</option>
            @foreach($users as $u)
                <option value="{{ $u->id }}" @selected((int) old('responsible_user_id', $p->responsible_user_id ?? 0) === $u->id)>{{ $u->name }}</option>
            @endforeach
        </select>
    </div>
    <div class="col-12">
        <label class="form-label">Заметки</label>
        <textarea name="notes" class="form-control" rows="3">{{ old('notes', $p->notes ?? '') }}</textarea>
    </div>
</div>
