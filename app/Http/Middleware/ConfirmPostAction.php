<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class ConfirmPostAction
{
    public function handle(Request $request, Closure $next)
    {
        return $request->isMethod('GET')
            ? response()->view('maintenance.confirm')
            : $next($request);
    }
}
