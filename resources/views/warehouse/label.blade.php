<!doctype html>
<html lang="ru">
<head>
    <meta charset="utf-8">
    <title>Этикетка · {{ $display_name }}</title>
    <style>
        body{ font-family: system-ui, -apple-system, "Segoe UI", Roboto, Arial, sans-serif; padding:1.5rem; }
        .label{
            width: 60mm; padding: 4mm; border: 1px dashed #999; border-radius: 4mm;
            margin: 0 auto 8mm; text-align: center;
        }
        .label .name{ font-weight: 700; font-size: 12pt; margin-bottom: 2mm; word-break: break-word; }
        .label .art{ font-weight: 800; font-size: 11pt; letter-spacing: .1em; }
        .label svg{ width: 100%; height: 15mm; margin-top: 2mm; }
        .actions{ text-align:center; margin-bottom: 10mm; }
        .actions button{ padding: .6rem 1.3rem; font-size: 14pt; border-radius: 8px; border: 0; background:#4f46e5; color:#fff; cursor: pointer; }
        @media print{ .actions{ display:none; } body{ padding: 0; } .label{ border-style:solid; } }
    </style>
</head>
<body>
    <div class="actions">
        <button onclick="window.print()">🖨️ Печать</button>
    </div>
    <div class="label">
        <div class="name">{{ $display_name }}</div>
        <div class="art">{{ $product->article }}</div>
        {!! $barcode_svg !!}
    </div>
</body>
</html>
