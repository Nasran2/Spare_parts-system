@php
    $exportPage = \App\Support\BusinessExport::page(request()->route()?->getName());
    $exportFilters = request()->except(['page', 'per_page', 'export']);
    if (request()->routeIs('preorders.status')) {
        $exportFilters['status'] = request()->route('status');
    }
@endphp
@if($exportPage && !request()->attributes->get('business_export'))
<div class="business-export-toolbar" aria-label="Download {{ $exportPage[1] }}">
    <div><strong>Download {{ $exportPage[1] }}</strong><p>Includes all records matching the applied filters.</p></div>
    <div class="business-export-actions">
        <a data-business-export data-no-loader href="{{ route('business.export', ['module' => $exportPage[0], 'format' => 'pdf'] + $exportFilters) }}" class="export-pdf"><i class="fas fa-file-pdf" aria-hidden="true"></i> PDF</a>
        <a data-business-export data-no-loader href="{{ route('business.export', ['module' => $exportPage[0], 'format' => 'excel'] + $exportFilters) }}" class="export-excel"><i class="fas fa-file-excel" aria-hidden="true"></i> Excel</a>
    </div>
</div>
@endif
