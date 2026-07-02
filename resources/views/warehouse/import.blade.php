@extends('layouts.app')

@section('content')
    <div class="d-flex align-items-center justify-content-between mb-3 flex-wrap gap-2">
        <div>
            <h4 class="mb-0">Импорт товаров пачкой</h4>
            <div class="text-muted small">вставьте список в поле ниже — система сама разберёт бренд, модель и размеры</div>
        </div>
        <a class="btn btn-sm btn-outline-secondary" href="{{ route('warehouse.index') }}">← К складу</a>
    </div>

    {{-- Импорт из Excel --}}
    <div class="card shadow-sm mb-3">
        <div class="card-body">
            <h6 class="mb-2">📗 Импорт из Excel (.xlsx)</h6>
            <div class="text-muted small mb-3">
                Формат листа: <b>Название</b> (например «Nike Dunk low retro») · <b>Размер</b> · <b>Кол-во</b> · <b>Артикул</b> (например «DV0833 800», необязателен).
                Первая строка-заголовок пропускается автоматически. Каждая расцветка (артикул) станет отдельной карточкой товара.
            </div>
            <form method="POST" action="{{ route('warehouse.import.run') }}" enctype="multipart/form-data" class="row g-2 align-items-end">
                @csrf
                <div class="col-lg-5">
                    <input type="file" name="xlsx" accept=".xlsx" class="form-control" required>
                </div>
                <div class="col-lg-5">
                    <div class="form-check">
                        <input class="form-check-input" type="radio" name="mode" value="set" id="modeSet" checked>
                        <label class="form-check-label small" for="modeSet">
                            <b>Файл — полный срез остатков</b>: установить количества как в файле (повторная загрузка не задваивает)
                        </label>
                    </div>
                    <div class="form-check">
                        <input class="form-check-input" type="radio" name="mode" value="add" id="modeAdd">
                        <label class="form-check-label small" for="modeAdd">
                            <b>Прибавить</b> к текущим остаткам (новая поставка)
                        </label>
                    </div>
                </div>
                <div class="col-lg-2">
                    <button type="submit" class="btn btn-success w-100">Импортировать</button>
                </div>
            </form>
            <div class="form-text mt-1">Позиции, которых нет в файле, не трогаются (в том числе другие бренды).</div>
        </div>
    </div>

    <div class="card shadow-sm mb-3">
        <div class="card-body">
            <h6 class="mb-2">📄 Импорт из текста</h6>
            <form method="POST" action="{{ route('warehouse.import.run') }}">
                @csrf
                <div class="row g-3">
                    <div class="col-lg-8">
                        <label class="form-label small mb-1">Список товаров</label>
                        <textarea name="raw" rows="18" class="form-control font-monospace"
                                  placeholder="Пример:&#10;NIKE DUNK LOW PINK VELVET GS - рр 37&#10;PUMA LAMELO BALL LAFRANCÉ AMOUR SLIME- рр 42,43&#10;NEW BALANCE 237 SOUR GRAPE LEMON WOMEN'S-36,36.5&#10;NIKE BLAZER LOW OFF WHITE BLACK ELECTRO GREEN-41,41.5,42,43.5" required>{{ old('raw') }}</textarea>
                    </div>
                    <div class="col-lg-4">
                        <label class="form-label small mb-1">Кол-во каждой пары</label>
                        <input type="number" name="quantity" min="1" max="1000" value="{{ old('quantity', 1) }}" class="form-control">
                        <div class="form-text">Умолчание: 1 пара на каждый указанный размер.</div>

                        <div class="mt-3 small">
                            <div class="fw-semibold mb-1">Как парсится строка:</div>
                            <ul class="mb-2 ps-3">
                                <li>Первое слово — <b>бренд</b> (NIKE, PUMA, ADIDAS, KAPPA, LACOSTE…).</li>
                                <li><b>NEW BALANCE</b> распознаётся как двусоставный бренд.</li>
                                <li>Остальное до <b>«-»</b> — модель.</li>
                                <li>После <b>«-»</b> — размеры через запятую или пробел (можно с «рр»).</li>
                            </ul>
                            <div class="text-muted">Если позиция уже есть — количество прибавится.</div>
                        </div>

                        <button type="submit" class="btn btn-success w-100 mt-3">Импортировать</button>
                    </div>
                </div>
            </form>
        </div>
    </div>
@endsection
