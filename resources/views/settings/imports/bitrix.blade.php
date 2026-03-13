@extends('layouts.app')

@section('content')
<div class="d-flex flex-column gap-3">
  <div class="card shadow-sm">
    <div class="card-body d-flex flex-column flex-lg-row justify-content-between gap-3 align-items-lg-center">
      <div>
        <h4 class="mb-1">Импорт лидов из Bitrix</h4>
        <div class="text-muted">Разовая загрузка выгрузки Bitrix в сделки CRM. Подходит для быстрого переноса без постоянной синхронизации.</div>
      </div>
      <div class="d-flex gap-2 flex-wrap">
        <a class="btn btn-outline-secondary" href="{{ route('settings.integrations.index') }}">Интеграции</a>
        <a class="btn btn-outline-secondary" href="{{ route('deals.kanban') }}">Канбан</a>
      </div>
    </div>
  </div>

  <div class="alert alert-info mb-0">
    Импорт из этого экрана односторонний: файл загружается в CRM. Комментарии и Bitrix-дела подтягиваются только если они есть в выгрузке, а обратная отправка локальных дел в Bitrix работает через раздел «Интеграции» при включённом Bitrix webhook.
  </div>

  <div class="row g-3">
    <div class="col-12 col-xl-7">
      <div class="card shadow-sm">
        <div class="card-header fw-semibold">Загрузка файла</div>
        <div class="card-body">
          <form method="POST" action="{{ route('settings.imports.bitrix.import') }}" enctype="multipart/form-data" class="row g-3">
            @csrf

            <div class="col-12">
              <label class="form-label">Файл выгрузки Bitrix</label>
              <input type="file" name="file" class="form-control" accept=".csv,.xlsx" required>
              <div class="form-text">Поддерживаются `CSV` и `XLSX`. Если Bitrix отдал старый `.xls`, пересохрани его в `.xlsx` или `.csv`.</div>
            </div>

            <div class="col-md-6">
              <label class="form-label">Стадия по умолчанию</label>
              <select name="default_stage_id" class="form-select" required>
                @foreach($stages as $stage)
                  <option value="{{ $stage->id }}" @selected((int) old('default_stage_id', $stages->first()?->id) === (int) $stage->id)>{{ $stage->name }}</option>
                @endforeach
              </select>
              <div class="form-text">Если стадия сделки из Bitrix не совпадёт с названием вашей стадии, лид попадёт сюда.</div>
            </div>

            <div class="col-md-6">
              <label class="form-label">Ответственный по умолчанию</label>
              <select name="default_responsible_user_id" class="form-select" required>
                @foreach($users as $crmUser)
                  <option value="{{ $crmUser->id }}" @selected((int) old('default_responsible_user_id', auth()->id()) === (int) $crmUser->id)>{{ $crmUser->name }}</option>
                @endforeach
              </select>
              <div class="form-text">Если имя ответственного из файла не распознается, сделка назначится на этого сотрудника.</div>
            </div>

            <div class="col-12">
              <button class="btn btn-primary">Импортировать лиды</button>
            </div>
          </form>
        </div>
      </div>
    </div>

    <div class="col-12 col-xl-5">
      <div class="card shadow-sm mb-3">
        <div class="card-header fw-semibold">Что переносится</div>
        <div class="card-body small">
          <div class="mb-2">Импорт старается распознать типовые колонки выгрузки Bitrix без ручного маппинга.</div>
          <ul class="mb-0">
            <li>`ID`, `Название сделки`, `Контакт`, `Имя`, `Фамилия`, `Отчество`</li>
            <li>`Телефон`, `E-mail`, `Стадия сделки`, `Ответственный`</li>
            <li>`Сумма`, `Валюта`, `Источник`, `Комментарий`, `Дата создания`</li>
          </ul>
        </div>
      </div>

      <div class="card shadow-sm">
        <div class="card-header fw-semibold">Как отработает импорт</div>
        <div class="card-body small">
          <ul class="mb-0">
            <li>Если статус Bitrix похож на название вашей стадии, стадия подставится автоматически.</li>
            <li>Если ответственный из файла совпадёт с пользователем CRM по имени, он назначится автоматически.</li>
            <li>Если совпадения нет, используются выбранные значения по умолчанию.</li>
            <li>Повторно загруженные лиды с тем же `ID` Bitrix пропускаются как дубли.</li>
            <li>Комментарий, статус, источник и исходный `ID` сохраняются в ленте сделки.</li>
            <li>Импортированные сделки помечаются источником `Bitrix`, чтобы их было легко проверить после загрузки.</li>
          </ul>
        </div>
      </div>
    </div>
  </div>
</div>
@endsection
