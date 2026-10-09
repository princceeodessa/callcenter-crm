{{-- Переключатель бизнесов в сводке — только у владельца всех бизнесов (users.all_businesses). --}}
@php($ownerUser = auth()->user())
@if($ownerUser && $ownerUser->role === 'sneaker_owner' && $ownerUser->all_businesses)
    <style>
        .own-switch{ display:inline-flex; gap:.25rem; padding:.25rem; border-radius:999px; background:var(--crm-surface-strong); border:1px solid var(--crm-border); box-shadow:var(--crm-shadow); }
        .own-switch a{ padding:.35rem .95rem; border-radius:999px; font-weight:700; font-size:.88rem; text-decoration:none; color:var(--crm-muted); white-space:nowrap; }
        .own-switch a.on{ background:#10b981; color:#fff; }
        .own-switch a:not(.on):hover{ color:inherit; background:rgba(127,127,127,.12); }
    </style>
    <nav class="own-switch mb-3" aria-label="Бизнес">
        <a href="{{ route('owner.ceilings') }}" class="{{ ($active ?? '') === 'ceilings' ? 'on' : '' }}">🏠 Потолки</a>
        <a href="{{ route('owner.dashboard') }}" class="{{ ($active ?? '') === 'sneakers' ? 'on' : '' }}">👟 Кроссовки</a>
    </nav>
@endif
