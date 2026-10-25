<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class EnsureActiveAccount
{
    public function handle(Request $request, Closure $next)
    {
        $user = $request->user();
        if ($user && ($user->is_active === false || $user->role?->is_active === false)) {
            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();
            abort(403);
        }

        return $next($request);
    }
}
