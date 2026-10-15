{{-- Логотип площадки у источника замеров (из дизайн-системы «БлагоДар»): Директ / Авито / ВК; у остальных — ничего --}}
@switch(mb_strtolower(trim($name ?? '')))
    @case('директ')
    @case('яндекс директ')
        <svg class="src-logo" viewBox="0 0 24 24" role="img" aria-label="Яндекс Директ"><circle cx="12" cy="12" r="11" fill="#FC3F1D"/><path fill="#fff" d="M13.2 5.85h-1.78c-2.22 0-3.66 1.41-3.66 3.6 0 1.58.74 2.52 2.08 3.26l-2.42 5.08h2.04l2.18-4.64h.88v4.64h1.78V5.85H13.2Zm-1.12 5.9h-.64c-1.08 0-1.8-.65-1.8-1.98 0-1.42.65-2.11 1.72-2.11h.72v4.09Z"/></svg>
        @break
    @case('авито')
    @case('avito')
    @case('авито · потолки')
        <svg class="src-logo" viewBox="0 0 24 24" role="img" aria-label="Авито"><circle cx="12" cy="12" r="11.5" fill="#F2F3F7"/><circle cx="9" cy="15" r="3.7" fill="#19C85A"/><circle cx="15.6" cy="9.1" r="3.4" fill="#0AA3FF"/><circle cx="9.4" cy="8.5" r="2.05" fill="#9E5BEF"/><circle cx="15.8" cy="15.5" r="2.4" fill="#FF4154"/></svg>
        @break
    @case('вк')
    @case('вконтакте')
    @case('vk реклама')
        <svg class="src-logo" viewBox="0 0 24 24" role="img" aria-label="ВКонтакте"><rect width="24" height="24" rx="6" fill="#0077FF"/><path fill="#fff" d="M12.78 16.55c-4.62 0-7.26-3.17-7.37-8.45h2.31c.08 3.88 1.79 5.52 3.15 5.86V8.1h2.18v3.33c1.34-.14 2.74-1.66 3.22-3.33h2.18c-.37 2.06-1.89 3.58-2.97 4.21 1.08.5 2.82 1.83 3.48 4.24h-2.4c-.5-1.59-1.79-2.83-3.5-3v3h-.46Z"/></svg>
        @break
@endswitch
