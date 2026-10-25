<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class MaintenanceAccess
{
    public function handle(Request $request, Closure $next)
    {
        abort_unless($request->user()?->isSuperAdmin(), 403);

        // GET links remain usable, but database changes require a CSRF-protected POST.
        if ($request->isMethod('GET') && (! $request->boolean('check') || ! $request->is('migration', 'migrate', 'run-migration'))) {
            return response()->view('maintenance.confirm');
        }

        return $next($request);
    }
}
