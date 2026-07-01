@extends('layouts.app')

@section('content')
<style>
    .help-wrap{ max-width:960px; margin:0 auto; }
    .help-content{
        background:var(--crm-surface-strong); border:1px solid var(--crm-border);
        border-radius:1rem; padding:2rem 2.4rem; box-shadow:var(--crm-shadow);
    }
    .help-content h1{ font-size:1.7rem; margin-top:0; letter-spacing:-.02em; }
    .help-content h2{ font-size:1.35rem; margin-top:2.2rem; letter-spacing:-.02em;
        border-bottom:1px solid var(--crm-border); padding-bottom:.3rem; }
    .help-content h3{ font-size:1.1rem; margin-top:1.6rem; letter-spacing:-.01em; }
    .help-content h4{ font-size:1rem; margin-top:1.2rem; }
    .help-content p, .help-content li{ line-height:1.6; }
    .help-content ul, .help-content ol{ padding-left:1.4rem; }
    .help-content ul li, .help-content ol li{ margin-bottom:.35rem; }
    .help-content code{
        background:color-mix(in srgb, var(--crm-accent) 10%, var(--crm-surface));
        color:var(--crm-text); padding:.05rem .35rem; border-radius:.3rem;
        font-size:.88em; font-family:ui-monospace,"SFMono-Regular",Menlo,monospace;
    }
    .help-content pre{
        background:color-mix(in srgb, var(--crm-text) 6%, transparent);
        border:1px solid var(--crm-border); border-radius:.6rem;
        padding:.8rem 1rem; overflow:auto; font-size:.85rem;
    }
    .help-content pre code{ background:transparent; padding:0; }
    .help-content blockquote{
        border-left:4px solid var(--crm-accent);
        background:color-mix(in srgb, var(--crm-accent) 8%, var(--crm-surface));
        padding:.6rem 1rem; margin:1rem 0; border-radius:0 .6rem .6rem 0;
    }
    .help-content a{ color:var(--crm-accent); text-decoration:none; }
    .help-content a:hover{ text-decoration:underline; }
    .help-content hr{ border:0; border-top:1px solid var(--crm-border); margin:2rem 0; }
    .help-toolbar{ display:flex; align-items:center; justify-content:space-between; gap:.5rem; margin-bottom:1rem; flex-wrap:wrap; }
</style>

<div class="help-wrap">
    <div class="help-toolbar">
        <div>
            <h4 class="mb-0" style="letter-spacing:-.02em">📖 Инструкция</h4>
            <div class="text-muted small">полное руководство сотрудника кроссовочного отдела</div>
        </div>
        <a class="btn btn-sm btn-outline-primary" href="{{ route('help.download') }}">⬇️ Скачать MD</a>
    </div>

    <div class="help-content">
        {!! $html !!}
    </div>
</div>
@endsection
