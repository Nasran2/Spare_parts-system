<!doctype html>
<html><head><meta charset="utf-8"><style>
    @page { margin: 24px; }
    body { font-family: DejaVu Sans, sans-serif; font-size: 10px; color: #111; }
    h1 { font-size: 18px; margin: 0 0 4px; }
    .period { color: #555; margin-bottom: 16px; }
    table { width: 100%; border-collapse: collapse; }
    th, td { border: 1px solid #333; padding: 6px; }
    th { background: #eee; text-align: left; }
    td:nth-child(4), td:nth-child(5) { text-align: right; }
</style></head><body>
<h1>{{ $title }}</h1><div class="period">{{ $from }} to {{ $to }}</div>
<table><thead><tr>@foreach($headings as $heading)<th>{{ $heading }}</th>@endforeach</tr></thead><tbody>
@foreach($rows as $row)<tr>@foreach($row as $cell)<td>{{ $cell }}</td>@endforeach</tr>@endforeach
</tbody></table></body></html>
