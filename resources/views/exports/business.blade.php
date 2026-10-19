<!DOCTYPE html>
<html><head><meta charset="utf-8"><title>{{ $metadata['title'] }}</title>
<style>
@page { margin: 28px 25px 34px; }
body { font-family: DejaVu Sans, sans-serif; color:#172033; font-size:9px; }
h1 { color:#1e40af; font-size:21px; margin:0 0 6px; } h2 { font-size:12px; margin:18px 0 7px; }
.meta { color:#475569; line-height:1.6; } .header { border-bottom:2px solid #2563eb; padding-bottom:10px; }
table { width:100%; border-collapse:collapse; table-layout:fixed; } thead { display:table-header-group; }
th { background:#e8efff; color:#1e3a8a; text-align:left; font-size:8px; } th,td { border:1px solid #dbe2ec; padding:6px 4px; word-wrap:break-word; }
tr { page-break-inside:avoid; } tbody tr:nth-child(even) { background:#f7f9fc; } .number { text-align:right; }
.footer { position:fixed; bottom:-22px; font-size:8px; color:#64748b; } .page:after { content:counter(page); }
</style></head><body>
@include('pdf.partials.letterhead', ['documentTitle' => $metadata['title'], 'documentMeta' => ['Period' => str_replace('Period: ', '', $metadata['period']), 'Currency' => $metadata['currency']]])
<p class="meta">{{ $metadata['filters'] }}</p>
@foreach($tables as $table)
<h2>{{ $table['title'] }}</h2>
<table><thead><tr>@foreach($table['headers'] as $heading)<th>{{ $heading }}</th>@endforeach</tr></thead><tbody>
@forelse($table['rows'] as $row)<tr>@foreach($row as $value)<td class="{{ is_int($value) || is_float($value) ? 'number' : '' }}">{{ is_int($value) || is_float($value) ? number_format($value, 2) : $value }}</td>@endforeach</tr>
@empty<tr><td colspan="{{ count($table['headers']) }}">No records match these filters.</td></tr>@endforelse
</tbody></table>
@endforeach
<div class="footer">{{ $metadata['title'] }} · <span class="page">Page </span></div>
</body></html>
