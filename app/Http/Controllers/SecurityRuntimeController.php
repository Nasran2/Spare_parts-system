<?php

namespace App\Http\Controllers;

class SecurityRuntimeController extends Controller
{
    public function __invoke()
    {
        $minimums = ['8.3' => '8.3.36', '8.4' => '8.4.27', '8.5' => '8.5.12'];
        $minimum = $minimums[PHP_MAJOR_VERSION.'.'.PHP_MINOR_VERSION] ?? null;

        return response()->json([
            'php_version' => PHP_VERSION,
            'sapi' => PHP_SAPI,
            'required_patch' => $minimum ?? 'Upgrade to PHP 8.3.36+, 8.4.27+, or 8.5.12+',
            'meets_requested_php2shell_version' => $minimum !== null && version_compare(PHP_VERSION, $minimum, '>='),
            'session_upload_progress_enabled' => (bool) ini_get('session.upload_progress.enabled'),
            'note' => 'Version check only; vendor backports require hosting evidence. Application fixes do not patch PHP.',
        ])->header('Cache-Control', 'no-store, private');
    }
}
