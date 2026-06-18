<?php

namespace App\Http\Middleware;

use App\Services\PrivacyModeService;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\View;
use Symfony\Component\HttpFoundation\Response;

class CheckPrivacyMode
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        $isActive = PrivacyModeService::isActiveForUser($user);
        $settings = PrivacyModeService::getSettings();

        View::share('privacyModeActive', $isActive);
        View::share('privacySettings', $settings);

        return $next($request);
    }
}
