{{-- Сделки за числом таблицы «Операторы» (ReportController::drill): окно на странице отчёта или страница reports/drill. --}}
@php
    $drFmt = fn (string $d) => \Carbon\Carbon::parse($d)->format('d.m.Y');
    $drPhone = function (?string $p) {
        $d = preg_replace('/\D+/', '', (string) $p);

        return strlen($d) === 11 ? '+7 ('.substr($d, 1, 3).') '.substr($d, 4, 3).'-'.substr($d, 7, 2).'-'.substr($d, 9, 2) : (string) $p;
    };
@endphp
<div id="drill-body">
  <div class="d-flex flex-wrap align-items-baseline gap-2 mb-2">
    <h5 class="mb-0">{{ $title }}: {{ $deals->count() }}</h5>
    <span class="text-muted small">{{ $who }} · {{ $drFmt($period['from']) }}{{ $period['from'] !== $period['to'] ? ' — '.$drFmt($period['to']) : '' }}</span>
  </div>
  @if($deals->isEmpty())
    <div class="text-muted">Сделок нет.</div>
  @else
    <div class="table-responsive">
      <table class="table table-sm align-middle mb-0">
        <thead><tr><th>Сделка</th><th>Клиент</th><th>Этап / итог</th><th>Создана</th><th>Закрыта</th><th>Ответственный</th></tr></thead>
        <tbody>
        @foreach($deals as $deal)
          @php
            $res = $deal->closed_at ? ($deal->closed_result === 'won' ? ['Успешно', 'success'] : ($deal->closed_result === 'lost' ? ['Отказ', 'danger'] : ($deal->closed_result === 'extra_non_target' ? ['Доп.Работы / Не целевой', 'warning'] : ['Закрыта', 'secondary']))) : null;
            $cat = \App\Support\Deals\NonTargetCategory::label($deal->non_target_category);
          @endphp
          <tr>
            <td><a href="{{ route('deals.show', $deal) }}" target="_blank" rel="noopener">{{ $deal->title ?: 'Сделка №'.$deal->id }}</a> <span class="text-muted small">№{{ $deal->id }}</span>
              @isset($rescued[$deal->id])<div class="small text-success">потом записали другие</div>@endisset</td>
            <td>{{ $deal->contact?->name }}@if($deal->contact?->phone)<div class="small text-muted">{{ $drPhone($deal->contact->phone) }}</div>@endif</td>
            <td>
              @if($res)<span class="badge text-bg-{{ $res[1] }}">{{ $res[0] }}</span>@else{{ $deal->stage?->name }}@endif
              @if($cat)<div class="small text-muted">{{ $cat }}</div>@endif
            </td>
            <td class="text-nowrap">{{ $deal->created_at?->format('d.m.Y H:i') }}</td>
            <td class="text-nowrap">{{ $deal->closed_at ? \Carbon\Carbon::parse($deal->closed_at)->format('d.m.Y H:i') : '—' }}</td>
            <td>{{ $deal->responsible?->name ?? '—' }}</td>
          </tr>
        @endforeach
        </tbody>
      </table>
    </div>
  @endif
</div>
