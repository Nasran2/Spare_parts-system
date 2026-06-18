<?php

namespace App\Http\Controllers;

use App\Services\PrivacyModeService;
use Illuminate\Http\Request;

class PrivacyModeController extends Controller
{
    /**
     * Toggle the Privacy Mode state in session.
     */
    public function toggle(Request $request)
    {
        $user = $request->user();
        if (! $user || ! $user->hasPermission('privacy_mode.toggle')) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        if (! PrivacyModeService::isEnabled()) {
            return response()->json(['error' => 'Privacy Mode is disabled in Super Admin settings.'], 422);
        }

        if ($user->hasPermission('privacy_mode.bypass')) {
            return response()->json(['error' => 'This user has Privacy Mode bypass and always sees real data.'], 403);
        }

        $isActive = session('privacy_mode_active', false);
        $newActive = ! $isActive;
        session(['privacy_mode_active' => $newActive]);

        $action = $newActive ? 'activated' : 'deactivated';
        $page = $request->input('page', 'POS screen');

        PrivacyModeService::logAction($user, $action, $page);

        return response()->json([
            'success' => true,
            'active' => $newActive,
            'message' => $newActive ? 'Activated.' : 'Deactivated.',
        ]);
    }
}
