<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $businessName ?? 'Ganga Traders' }} Login</title>

    <script src="https://cdn.tailwindcss.com"></script>

    <style>
        @keyframes floatPart {
            0%,100% { transform: translateY(0) rotate(0deg); }
            50% { transform: translateY(-20px) rotate(8deg); }
        }

        @keyframes slideIn {
            from { opacity: 0; transform: translateY(35px); }
            to { opacity: 1; transform: translateY(0); }
        }

        @keyframes pulseGlow {
            0%,100% { box-shadow: 0 0 20px rgba(37,99,235,.35); }
            50% { box-shadow: 0 0 45px rgba(59,130,246,.75); }
        }

        @keyframes moveRoad {
            from { background-position: 0 0; }
            to { background-position: 120px 0; }
        }

        .float-part { animation: floatPart 5s ease-in-out infinite; }
        .slide-in { animation: slideIn .8s ease-out both; }
        .glow-blue { animation: pulseGlow 3s ease-in-out infinite; }

        .road-line {
            background-image: linear-gradient(90deg, transparent 0 40%, white 40% 50%, transparent 50% 100%);
            background-size: 120px 4px;
            animation: moveRoad 2s linear infinite;
        }
    </style>
</head>

<body class="min-h-screen overflow-hidden bg-slate-950">

<div class="relative min-h-screen flex items-center justify-center px-4 py-8 bg-gradient-to-br from-slate-950 via-slate-900 to-blue-950">

    <!-- Background Glow -->
    <div class="absolute inset-0 bg-[radial-gradient(circle_at_15%_20%,rgba(37,99,235,.35),transparent_30%),radial-gradient(circle_at_85%_70%,rgba(59,130,246,.25),transparent_35%)]"></div>

    <!-- Garage Pattern -->
    <div class="absolute inset-0 opacity-[0.06] bg-[linear-gradient(90deg,#fff_1px,transparent_1px),linear-gradient(#fff_1px,transparent_1px)] bg-[size:55px_55px]"></div>

    <!-- Floating Parts -->
    <div class="absolute top-20 left-12 text-7xl opacity-20 float-part">⚙️</div>
    <div class="absolute top-28 right-20 text-6xl opacity-20 float-part">🔧</div>
    <div class="absolute bottom-24 left-20 text-6xl opacity-20 float-part">🛞</div>
    <div class="absolute bottom-28 right-28 text-7xl opacity-20 float-part">🚗</div>

    <!-- Road Line -->
    <div class="absolute bottom-20 left-0 w-full h-1 road-line opacity-60"></div>

    <div class="relative z-10 w-full max-w-7xl grid grid-cols-1 lg:grid-cols-2 gap-10 items-center">

        <!-- Left Branding -->
        <div class="hidden lg:block text-white slide-in">

            <div class="flex items-center gap-4 mb-10">
                <div class="w-20 h-20 rounded-3xl bg-blue-600 flex items-center justify-center text-4xl glow-blue">
                    ⚙️
                </div>
                <div>
                    <h1 class="text-5xl font-black">
                        {{ $businessName ?? 'GANGA TRADERS' }}
                    </h1>
                    <p class="text-blue-100 mt-1">Vehicle Spare Parts Management System</p>
                </div>
            </div>

            <h2 class="text-5xl font-black leading-tight mb-5">
                Smart ERP for <br>
                <span class="text-blue-400">Vehicle Spare Parts</span>
            </h2>

            <p class="text-slate-300 text-lg max-w-xl mb-8">
                Manage stock, sales, purchases, customers, suppliers and reports from one powerful dashboard.
            </p>

            <div class="grid grid-cols-2 gap-5 max-w-xl">
                <div class="bg-white/10 border border-blue-400/20 backdrop-blur-xl rounded-2xl p-5">
                    <div class="text-3xl mb-2">📦</div>
                    <h3 class="font-bold">Inventory</h3>
                    <p class="text-sm text-slate-300">Track spare parts stock</p>
                </div>

                <div class="bg-white/10 border border-blue-400/20 backdrop-blur-xl rounded-2xl p-5">
                    <div class="text-3xl mb-2">🧾</div>
                    <h3 class="font-bold">Sales & POS</h3>
                    <p class="text-sm text-slate-300">Fast billing system</p>
                </div>

                <div class="bg-white/10 border border-blue-400/20 backdrop-blur-xl rounded-2xl p-5">
                    <div class="text-3xl mb-2">🚚</div>
                    <h3 class="font-bold">Suppliers</h3>
                    <p class="text-sm text-slate-300">Purchase management</p>
                </div>

                <div class="bg-white/10 border border-blue-400/20 backdrop-blur-xl rounded-2xl p-5">
                    <div class="text-3xl mb-2">📊</div>
                    <h3 class="font-bold">Reports</h3>
                    <p class="text-sm text-slate-300">Profit and stock reports</p>
                </div>
            </div>

            <div class="mt-8 flex gap-4 text-sm text-slate-300">
                <div class="bg-blue-500/10 border border-blue-400/20 px-5 py-3 rounded-full">
                    2,500+ Products
                </div>
                <div class="bg-blue-500/10 border border-blue-400/20 px-5 py-3 rounded-full">
                    LKR Ready
                </div>
                <div class="bg-blue-500/10 border border-blue-400/20 px-5 py-3 rounded-full">
                    Sri Lanka
                </div>
            </div>
        </div>

        <!-- Login Card -->
        <div class="slide-in">
            <div class="max-w-md mx-auto bg-slate-950/80 border border-blue-400/30 backdrop-blur-2xl rounded-[2rem] shadow-2xl p-8 sm:p-10 text-white">

                <div class="text-center mb-8">
                    <div class="mx-auto w-24 h-24 rounded-full border border-blue-400/50 bg-blue-600/20 flex items-center justify-center text-5xl mb-5 glow-blue">
                        🚗
                    </div>
                    <h2 class="text-4xl font-black">Welcome Back!</h2>
                    <p class="text-slate-300 mt-2">Sign in to continue</p>
                </div>

                @if ($errors->any())
                    <div class="mb-5 bg-red-500/10 border border-red-500/40 text-red-200 px-4 py-3 rounded-xl text-sm">
                        {{ $errors->first() }}
                    </div>
                @endif
                
                @if (session('error'))
                    <div class="mb-5 bg-red-500/10 border border-red-500/40 text-red-200 px-4 py-3 rounded-xl text-sm">
                        {{ session('error') }}
                    </div>
                @endif

                <form method="POST" action="{{ route('login') }}" class="space-y-5">
                    @csrf

                    <div>
                        <label class="block text-sm font-semibold text-blue-200 mb-2">Username</label>
                        <div class="relative">
                            <span class="absolute left-4 top-3.5 text-blue-400">👤</span>
                            <input
                                type="text"
                                name="username"
                                value="{{ old('username') }}"
                                required
                                autofocus
                                placeholder="Enter your username"
                                class="w-full pl-12 pr-4 py-3.5 rounded-xl bg-slate-900/90 border border-blue-400/20 text-white placeholder:text-slate-500 focus:ring-2 focus:ring-blue-500 focus:border-blue-500 outline-none transition">
                        </div>
                    </div>

                    <div>
                        <label class="block text-sm font-semibold text-blue-200 mb-2">Password</label>
                        <div class="relative">
                            <span class="absolute left-4 top-3.5 text-blue-400">🔒</span>
                            <input
                                type="password"
                                name="password"
                                required
                                placeholder="Enter your password"
                                class="w-full pl-12 pr-12 py-3.5 rounded-xl bg-slate-900/90 border border-blue-400/20 text-white placeholder:text-slate-500 focus:ring-2 focus:ring-blue-500 focus:border-blue-500 outline-none transition">
                            <span class="absolute right-4 top-3.5 text-slate-400">👁️</span>
                        </div>
                    </div>

                    <div class="flex items-center justify-between text-sm">
                        <label class="flex items-center gap-2 text-slate-300">
                            <input type="checkbox" name="remember" class="rounded bg-slate-900 border-blue-400/40 text-blue-600 focus:ring-blue-500">
                            Remember me
                        </label>

                        @if (Route::has('password.request'))
                            <a href="{{ route('password.request') }}" class="text-blue-400 hover:text-blue-300 font-semibold">
                                Forgot password?
                            </a>
                        @endif
                    </div>

                    <button
                        type="submit"
                        class="w-full py-3.5 rounded-xl bg-gradient-to-r from-blue-600 to-sky-500 hover:from-sky-500 hover:to-blue-600 text-white font-bold text-lg shadow-xl shadow-blue-900/40 hover:scale-[1.02] active:scale-95 transition">
                        🔐 Sign In
                    </button>
                </form>

                <div class="mt-8">
                    <div class="flex items-center gap-3">
                        <div class="h-px bg-blue-400/20 flex-1"></div>
                        <p class="text-sm text-slate-300">
                            Powered by <span class="font-bold text-blue-400">{{ env('DEV_TEAM_NAME', 'IntelSynQ.com') }}</span>
                        </p>
                        <div class="h-px bg-blue-400/20 flex-1"></div>
                    </div>

                    <div class="grid grid-cols-3 gap-3 mt-6 text-center text-xs text-slate-400">
                        <div>
                            <div class="text-2xl mb-1">🛡️</div>
                            Secure
                        </div>
                        <div>
                            <div class="text-2xl mb-1">⚡</div>
                            Fast
                        </div>
                        <div>
                            <div class="text-2xl mb-1">🔧</div>
                            Support
                        </div>
                    </div>
                </div>
            </div>

            <p class="text-center text-slate-400 text-sm mt-6">
                &copy; {{ date('Y') }} {{ $businessName ?? 'Ganga Traders' }}. All rights reserved.
            </p>
        </div>

    </div>
</div>

</body>
</html>
