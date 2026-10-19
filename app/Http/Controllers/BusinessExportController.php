<?php

namespace App\Http\Controllers;

use App\Exports\BusinessWorkbook;
use App\Models\Setting;
use App\Services\BusinessTableService;
use App\Support\BusinessExport;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Illuminate\View\View;
use Maatwebsite\Excel\Facades\Excel;

class BusinessExportController extends Controller
{
    public function __invoke(Request $request, string $module, string $format, BusinessTableService $tables)
    {
        $routeName = collect(BusinessExport::PAGES)->search(fn ($page) => $page[0] === $module);
        abort_unless($routeName && in_array($format, ['pdf', 'excel'], true), 404);
        $source = Route::getRoutes()->getByName($routeName);
        abort_unless($source, 404);
        $source = clone $source;
        $source->bind(Request::create(route($routeName)));
        // A download requires precisely the permission of its source screen.
        foreach ($source->gatherMiddleware() as $middleware) {
            if (str_starts_with($middleware, 'permission:')) {
                abort_unless($request->user()?->hasPermission(substr($middleware, 11)), 403);
            }
        }
        $title = BusinessExport::PAGES[$routeName][1];
        $originalRoute = $request->route();
        $request->attributes->set('business_export', true);
        $request->query->remove('page');
        $request->query->remove('export');
        $request->setRouteResolver(fn () => $source);
        try {
            // Reuse the screen's queries, scopes and masking; only pagination changes.
            $view = app()->call([$source->getController(), $source->getActionMethod()], ['request' => $request]);
            abort_unless($view instanceof View, 422, 'This selection cannot be exported.');
            $data = $view->getData();
            $sections = $view->renderSections();
            $exportTables = $tables->extract($sections['content'] ?? '', $title, Setting::get('currency_symbol', 'Rs'));
            abort_if(! $exportTables, 422, 'There are no exportable tables in this selection.');
            $from = $data['from'] ?? $request->input('from', $request->input('date_from'));
            $to = $data['to'] ?? $request->input('to', $request->input('date_to'));
            if ($routeName === 'sales.index' && ! $from && ! $to && ! $request->boolean('all_dates')) {
                $from = $to = today()->toDateString();
            }
            if (str_starts_with($routeName, 'reports.') || str_starts_with($routeName, 'preorders.')) {
                [$firstDate, $lastDate] = $data['periodDates'] ?? $tables->dates($exportTables);
                $from = $from ?: $firstDate;
                $to = $to ?: $lastDate;
            }
            $filters = collect($request->except(['page', 'per_page', '_token', 'export']))
                ->filter(fn ($value) => is_scalar($value) && (string) $value !== '')
                ->map(fn ($value, $key) => str_replace('_', ' ', ucfirst($key)).': '.$value)->implode(' · ');
            $metadata = [
                'title' => $title,
                'business' => Setting::get('shop_name', config('app.name', 'Vehicle POS System')),
                'period' => $from || $to ? 'Period: '.($from ?: 'All earlier dates').' to '.($to ?: 'Latest entry') : 'As of '.today()->toDateString().' · All matching records',
                'generated' => now()->format('Y-m-d H:i'),
                'filters' => $filters ?: 'Filters: All records',
                'currency' => Setting::get('currency_symbol', 'Rs'),
            ];
        } finally {
            $request->setRouteResolver(fn () => $originalRoute);
            $request->attributes->remove('business_export');
        }
        $filename = $module.'-'.today()->toDateString();
        if ($format === 'excel') {
            return Excel::download(new BusinessWorkbook($exportTables, $metadata), $filename.'.xlsx');
        }

        return Pdf::loadView('exports.business', ['tables' => $exportTables, 'metadata' => $metadata])
            ->setPaper('a4', 'landscape')->download($filename.'.pdf');
    }
}
