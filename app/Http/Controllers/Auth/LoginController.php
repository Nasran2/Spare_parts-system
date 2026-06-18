<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\Setting;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Schema;

class LoginController extends Controller
{
    public function showLoginForm()
    {
        $businessName = Setting::get('shop_name', config('app.name', 'Vehicle POS'));
        $tagline = Setting::get('shop_tagline', 'Auto Parts Management System');

        return view('auth.login', compact('businessName', 'tagline'));
    }

    public function login(Request $request)
    {
        $validated = $request->validate([
            'username' => ['required', 'string'],
            'password' => ['required'],
        ]);

        $remember = $request->boolean('remember');

        $loginValue = $validated['username'];
        $loginField = filter_var($loginValue, FILTER_VALIDATE_EMAIL) ? 'email' : 'username';

        // Fall back to the "name" column when a dedicated username column does not exist
        if ($loginField === 'username' && ! Schema::hasColumn('users', 'username')) {
            $loginField = 'name';
        }

        $attemptCredentials = [
            $loginField => $loginValue,
            'password' => $validated['password'],
        ];

        // Include is_active if present on users table
        if (Schema::hasColumn('users', 'is_active')) {
            $attemptCredentials['is_active'] = 1;
        }

        if (Auth::attempt($attemptCredentials, $remember)) {
            $request->session()->regenerate();

            // Log the login activity
            ActivityLog::log('login', 'User logged in successfully');

            $user = Auth::user();
            if ($user && method_exists($user, 'isSuperAdmin') && $user->isSuperAdmin()) {
                return redirect()->route('fun.dashboard');
            }

            return redirect()->route('dashboard');
        }

        return back()->withErrors([
            'username' => 'The provided credentials do not match our records.',
        ])->onlyInput('username');
    }

    public function logout(Request $request)
    {
        // Log the logout activity
        ActivityLog::log('logout', 'User logged out');

        Auth::logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }
}
