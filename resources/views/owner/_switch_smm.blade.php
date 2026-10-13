{{-- Переключатель бизнесов на странице «SMM-контент» — только у владельца всех бизнесов. --}}
<nav class="own-switch-smm" aria-label="Бизнес" style="display:inline-flex;gap:.25rem;padding:.25rem;border-radius:999px;background:var(--crm-surface-strong);border:1px solid var(--crm-border);box-shadow:var(--crm-shadow)">
    @foreach(['ceilings' => '🏠 Потолки', 'sneakers' => '👟 Кроссовки'] as $key => $label)
        <a href="{{ route('owner.smm', ['business' => $key]) }}" style="padding:.35rem .95rem;border-radius:999px;font-weight:700;font-size:.88rem;text-decoration:none;white-space:nowrap;{{ ($active ?? '') === $key ? 'background:#10b981;color:#fff' : 'color:var(--crm-muted)' }}">{{ $label }}</a>
    @endforeach
</nav>
