<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use App\Models\Setting;

class LockdownIfDbError
{
	/**
	 * If force_error_mode is enabled, block normal pages and show error.
	 */
	public function handle(Request $request, Closure $next)
	{
		$force = (bool) Setting::get('secretpos.force_error_mode', false);

		// Allow information pages to remain accessible
		$path = trim($request->path(), '/');
		$isInformation = str_starts_with($path, 'information');

		if ($force && !$isInformation) {
			// Render minimal error page and hide all other components
			return response()->view('errors.force_error_mode', [], 503);
		}

		return $next($request);
	}
}
