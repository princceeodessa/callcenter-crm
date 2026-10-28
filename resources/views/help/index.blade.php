@extends('layouts.sneakers')
{{--
    «Помощь» кроссовочного отдела — каркас «Нордика» (прототип: PAGES.help, снимок 16). Тексты — наши инструкции docs/SELLER_GUIDE.md
    («Для продавца») и docs/USER_GUIDE.md («Полная»), разложенные по разделам (заголовки ## и ###): слева оглавление с номерами,
    справа один раздел — «Шаг / Раздел N из M», нумерованные списки — шагами-карточками, цитаты — зелёным советом, внизу
    «Назад / Дальше». Раздел — в адресе (#s3), его можно переслать.
--}}
@php
    $ui = \App\Support\Ui\Sneakers::class;
    $seller = ($active ?? 'full') === 'simple';
    $bulb = $ui::icon('bulb');
    // разделы: ## и ### по порядку; вступление (до первого раздела, без # заголовка) — в начало первого
    $parts = preg_split('~(?=<h[23][\s>])~u', (string) $html);
    $intro = preg_replace('~<h1[^>]*>.*?</h1>|<hr>~su', '', (string) array_shift($parts));
    $sections = [];
    foreach ($parts as $p) {
        if (! preg_match('~^<h([23])[^>]*>(.*?)</h\1>~su', $p, $m)) {
            continue;
        }
        $title = preg_replace('/^\d+\.\s*/u', '', trim(strip_tags($m[2])));
        $body = trim(preg_replace('~(<hr>\s*)+$~', '', substr($p, strlen($m[0]))));
        // нумерованные шаги — карточками, цитаты — советом, первый абзац — вводным
        $body = preg_replace_callback('~<ol>(.*?)</ol>~su', function ($ol) {
            preg_match_all('~<li>(.*?)</li>~su', $ol[1], $li);
            return '<div class="ns-hsteps">'.implode('', array_map(fn ($t, $i) => '<div class="ns-hstep"><i>'.($i + 1).'</i><div>'.$t.'</div></div>', $li[1], array_keys($li[1]))).'</div>';
        }, $body);
        $body = preg_replace('~<blockquote>(.*?)</blockquote>~su', '<div class="ns-tip">'.$bulb.'<span>$1</span></div>', $body);
        $body = preg_replace('~^<p>~u', '<p class="ns-lead">', $body, 1);
        $sections[] = ['title' => $title, 'body' => $body];
    }
    if ($sections && trim(strip_tags($intro)) !== '') {
        $intro = preg_replace('~<blockquote>(.*?)</blockquote>~su', '<div class="ns-tip">'.$bulb.'<span>$1</span></div>', $intro);
        $sections[0]['body'] = '<div class="ns-hintro">'.$intro.'</div>'.$sections[0]['body'];
    }
    $total = count($sections);
    $left = '<svg class="ns-icon" viewBox="0 0 24 24"><path d="M15 6l-6 6 6 6"/></svg>';
    $right = '<svg class="ns-icon" viewBox="0 0 24 24"><path d="M9 6l6 6-6 6"/></svg>';
@endphp

@section('title', 'Инструкция')
@section('ns_sub', 'кроссовочный отдел')
@section('ns_no_search', '1')
@section('ns_no_sell', '1')
@section('ns_actions')
    <div class="ns-seg"><a href="{{ route('help.simple') }}" @class(['ns-on' => $seller])>Для продавца</a><a href="{{ route('help.index') }}" @class(['ns-on' => ! $seller])>Полная</a></div>
    <a class="ns-btn" href="{{ route('help.download', ['doc' => $seller ? 'simple' : 'full']) }}" title="Скачать инструкцию (Markdown)">{!! $ui::icon('down', 'ns-sm') !!}Скачать</a>
@endsection

@section('content')
<div class="ns-help">
    <nav class="ns-panel ns-toc" id="hToc">
        <div class="ns-toc-h">{{ $seller ? 'Для продавца' : 'Полная инструкция' }}<span>{{ $total }} {{ $total % 10 === 1 && $total % 100 !== 11 ? 'раздел' : ($total % 10 >= 2 && $total % 10 <= 4 && ($total % 100 < 12 || $total % 100 > 14) ? 'раздела' : 'разделов') }}</span></div>
        @foreach($sections as $i => $s)
            <a href="#s{{ $i + 1 }}" data-i="{{ $i + 1 }}" @class(['ns-on' => $i === 0])><span class="ns-tn">{{ $i + 1 }}</span><span class="ns-tt">{{ $s['title'] }}</span></a>
        @endforeach
    </nav>
    <div>
        @forelse($sections as $i => $s)
            <article class="ns-panel ns-hdoc" id="s{{ $i + 1 }}" @if($i > 0) hidden @endif>
                <div class="ns-hk">{{ $seller ? 'Шаг' : 'Раздел' }} {{ $i + 1 }} из {{ $total }}</div>
                <h2>{{ $s['title'] }}</h2>
                <div class="ns-hbody">{!! $s['body'] !!}</div>
                <div class="ns-hnav">
                    @if($i > 0)<a class="ns-hn" href="#s{{ $i }}"><small>{!! $left !!}Назад</small><b>{{ $sections[$i - 1]['title'] }}</b></a>@else<span></span>@endif
                    @if($i < $total - 1)<a class="ns-hn ns-r" href="#s{{ $i + 2 }}"><small>Дальше{!! $right !!}</small><b>{{ $sections[$i + 1]['title'] }}</b></a>@else<span></span>@endif
                </div>
            </article>
        @empty
            <div class="ns-panel ns-empty">{!! $ui::icon('help') !!}<b>Инструкция не найдена</b></div>
        @endforelse
    </div>
</div>
@endsection

@push('scripts')
<script>
(() => {
    // раздел — по адресу #sN: назад/вперёд браузера и ссылки «Назад / Дальше» работают сами
    const show = () => {
        const m = /^#s(\d+)$/.exec(location.hash), n = m ? +m[1] : 1;
        const art = document.getElementById('s' + n) || document.getElementById('s1');
        if (!art) return;
        document.querySelectorAll('.ns-hdoc').forEach((a) => { a.hidden = a !== art; });
        document.querySelectorAll('#hToc a').forEach((a) => a.classList.toggle('ns-on', a.getAttribute('href') === '#' + art.id));
        document.querySelector('.ns-page')?.scrollTo({ top: 0 });
    };
    window.addEventListener('hashchange', show);
    show();
})();
</script>
@endpush
