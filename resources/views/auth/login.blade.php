<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Vehicle POS - Login</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap');
        
        * {
            font-family: 'Poppins', sans-serif;
        }
        
        body {
            background: linear-gradient(135deg, #0f172a 0%, #1e293b 25%, #334155 50%, #475569 75%, #64748b 100%);
            position: relative;
            overflow-x: hidden;
        }
        
        /* Road Pattern Background */
        .road-pattern {
            position: fixed;
            bottom: 0;
            left: 0;
            right: 0;
            height: 150px;
            background: 
                linear-gradient(90deg, transparent 0%, transparent 45%, white 45%, white 55%, transparent 55%, transparent 100%) 0 0/100px 5px repeat-x,
                linear-gradient(to bottom, #374151 0%, #1f2937 100%);
            animation: roadMove 2s linear infinite;
            z-index: 0;
        }
        
        @keyframes roadMove {
            0% { background-position: 0 0; }
            100% { background-position: 100px 0; }
        }
        
        /* Animated Vehicles Moving */
        .moving-vehicle {
            position: fixed;
            font-size: 3rem;
            opacity: 0.15;
            animation: driveAcross 15s linear infinite;
            z-index: 1;
        }
        
        .moving-vehicle-1 {
            bottom: 80px;
            animation-delay: 0s;
            color: #60a5fa;
        }
        
        .moving-vehicle-2 {
            bottom: 180px;
            animation-delay: 5s;
            color: #34d399;
            font-size: 2.5rem;
        }
        
        .moving-vehicle-3 {
            bottom: 280px;
            animation-delay: 10s;
            color: #fbbf24;
            font-size: 3.5rem;
        }
        
        @keyframes driveAcross {
            0% { 
                left: -200px;
                transform: scaleX(1);
            }
            100% { 
                left: 110%;
                transform: scaleX(1);
            }
        }
        
        /* Clouds Animation */
        .cloud {
            position: fixed;
            color: rgba(255, 255, 255, 0.1);
            font-size: 4rem;
            animation: cloudFloat 30s linear infinite;
            z-index: 1;
        }
        
        .cloud-1 {
            top: 10%;
            animation-delay: 0s;
        }
        
        .cloud-2 {
            top: 25%;
            animation-delay: 10s;
            font-size: 5rem;
        }
        
        .cloud-3 {
            top: 40%;
            animation-delay: 20s;
            font-size: 3rem;
        }
        
        @keyframes cloudFloat {
            0% { left: -200px; }
            100% { left: 110%; }
        }
        
        .gear-animation {
            animation: rotate 20s linear infinite;
        }
        
        @keyframes rotate {
            from { transform: rotate(0deg); }
            to { transform: rotate(360deg); }
        }
        
        .gear-reverse {
            animation: rotate-reverse 15s linear infinite;
        }
        
        @keyframes rotate-reverse {
            from { transform: rotate(360deg); }
            to { transform: rotate(0deg); }
        }
        
        .login-card {
            backdrop-filter: blur(10px);
            background: rgba(255, 255, 255, 0.98);
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.5);
        }
        
        .input-icon {
            transition: all 0.3s ease;
        }
        
        input:focus + .input-icon {
            color: #3b82f6;
        }
        
        .btn-primary {
            background: linear-gradient(135deg, #2563eb 0%, #3b82f6 100%);
            transition: all 0.3s ease;
            position: relative;
            overflow: hidden;
        }
        
        .btn-primary:hover {
            background: linear-gradient(135deg, #1d4ed8 0%, #2563eb 100%);
            transform: translateY(-2px);
            box-shadow: 0 10px 20px rgba(37, 99, 235, 0.3);
        }
        
        .btn-primary:active {
            transform: translateY(0);
        }
        
        .floating {
            animation: floating 3s ease-in-out infinite;
        }
        
        @keyframes floating {
            0%, 100% { transform: translateY(0px); }
            50% { transform: translateY(-20px); }
        }
        
        /* Loading Spinner with Vehicle */
        .loading-overlay {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(15, 23, 42, 0.95);
            z-index: 9999;
            justify-content: center;
            align-items: center;
            flex-direction: column;
        }
        
        .loading-overlay.active {
            display: flex;
        }
        
        .loading-vehicle {
            font-size: 5rem;
            color: #3b82f6;
            animation: vehicleLoading 1.5s ease-in-out infinite;
        }
        
        @keyframes vehicleLoading {
            0%, 100% { 
                transform: translateX(-50px) scale(1);
                opacity: 0.5;
            }
            50% { 
                transform: translateX(50px) scale(1.1);
                opacity: 1;
            }
        }
        
        .loading-road {
            width: 300px;
            height: 4px;
            background: #1e293b;
            border-radius: 2px;
            margin-top: 30px;
            position: relative;
            overflow: hidden;
        }
        
        .loading-road::after {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, #3b82f6, transparent);
            animation: loadingProgress 1.5s ease-in-out infinite;
        }
        
        @keyframes loadingProgress {
            0% { left: -100%; }
            100% { left: 200%; }
        }
        
        .loading-text {
            color: white;
            margin-top: 20px;
            font-size: 1.2rem;
            font-weight: 600;
            animation: pulse 1.5s ease-in-out infinite;
        }
        
        @keyframes pulse {
            0%, 100% { opacity: 0.6; }
            50% { opacity: 1; }
        }
        
        .wheel-spin {
            display: inline-block;
            animation: wheelSpin 1s linear infinite;
            margin: 0 5px;
        }
        
        @keyframes wheelSpin {
            from { transform: rotate(0deg); }
            to { transform: rotate(360deg); }
        }
    </style>
</head>
<body class="min-h-screen flex items-center justify-center p-4">
    
    <!-- Loading Overlay -->
    <div class="loading-overlay" id="loadingOverlay">
        <i class="fas fa-car loading-vehicle"></i>
        <div class="loading-road"></div>
        <div class="loading-text">
            <i class="fas fa-circle-notch wheel-spin"></i>
            Loading {{ $businessName ?? 'Vehicle POS' }}
            <i class="fas fa-circle-notch wheel-spin"></i>
        </div>
    </div>
    
    <!-- Animated Road at Bottom -->
    <div class="road-pattern"></div>
    
    <!-- Moving Vehicles -->
    <i class="fas fa-car moving-vehicle moving-vehicle-1"></i>
    <i class="fas fa-truck moving-vehicle moving-vehicle-2"></i>
    <i class="fas fa-shuttle-van moving-vehicle moving-vehicle-3"></i>
    
    <!-- Floating Clouds -->
    <i class="fas fa-cloud cloud cloud-1"></i>
    <i class="fas fa-cloud cloud cloud-2"></i>
    <i class="fas fa-cloud cloud cloud-3"></i>
    
    <!-- Background Decorative Gears -->
    <div class="fixed inset-0 overflow-hidden pointer-events-none opacity-10">
        <i class="fas fa-cog gear-animation text-white absolute top-20 left-10 text-9xl"></i>
        <i class="fas fa-cog gear-reverse text-white absolute bottom-20 right-10 text-7xl"></i>
        <i class="fas fa-cog gear-animation text-white absolute top-1/2 right-1/4 text-6xl"></i>
    </div>

    <!-- Login Container -->
    <div class="w-full max-w-md relative z-10">
        
        <!-- Logo & Title -->
        <div class="text-center mb-8">
            <div class="inline-flex items-center justify-center w-24 h-24 bg-white rounded-full shadow-2xl mb-4 floating">
                <i class="fas fa-car-side text-5xl text-blue-600"></i>
            </div>
            <h1 class="text-4xl md:text-5xl font-bold text-white mb-2 drop-shadow-lg">
                {{ $businessName ?? 'Vehicle POS' }}
            </h1>
            <p class="text-blue-100 text-sm md:text-base">
                <i class="fas fa-tools mr-2"></i>
                {{ $tagline ?? 'Auto Parts Management System' }}
            </p>
        </div>

        <!-- Login Card -->
        <div class="login-card rounded-2xl shadow-2xl p-8 md:p-10">
            <div class="mb-6">
                <h2 class="text-2xl md:text-3xl font-bold text-gray-800 mb-2">Welcome Back!</h2>
                <p class="text-gray-600 text-sm md:text-base">Sign in to manage {{ strtolower($tagline ?? 'your vehicle parts inventory') }}</p>
            </div>

            @if (session('error'))
                <div class="bg-red-50 border-l-4 border-red-500 p-4 mb-6 rounded">
                    <div class="flex items-center">
                        <i class="fas fa-exclamation-circle text-red-500 mr-3"></i>
                        <p class="text-red-700 text-sm">{{ session('error') }}</p>
                    </div>
                </div>
            @endif

            <form method="POST" action="{{ route('login') }}" class="space-y-6">
                @csrf
                
                <!-- Username Input -->
                <div class="relative">
                    <label for="username" class="block text-sm font-medium text-gray-700 mb-2">
                        <i class="fas fa-user mr-2 text-blue-600"></i>Username
                    </label>
                    <div class="relative">
                        <input 
                            type="text" 
                            id="username" 
                            name="username" 
                            required
                            value="{{ old('username') }}"
                            class="w-full px-4 py-3 pl-12 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent transition duration-200 @error('username') border-red-500 @enderror"
                            placeholder="Enter your username"
                        >
                        <i class="fas fa-user input-icon absolute left-4 top-1/2 transform -translate-y-1/2 text-gray-400"></i>
                    </div>
                    @error('username')
                        <p class="text-red-500 text-xs mt-1">{{ $message }}</p>
                    @enderror
                </div>

                <!-- Password Input -->
                <div class="relative">
                    <label for="password" class="block text-sm font-medium text-gray-700 mb-2">
                        <i class="fas fa-lock mr-2 text-blue-600"></i>Password
                    </label>
                    <div class="relative">
                        <input 
                            type="password" 
                            id="password" 
                            name="password" 
                            required
                            autocomplete="current-password"
                            class="w-full px-4 py-3 pl-12 pr-12 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent transition duration-200 @error('password') border-red-500 @enderror"
                            placeholder="Enter your password"
                        >
                        <i class="fas fa-key input-icon absolute left-4 top-1/2 transform -translate-y-1/2 text-gray-400"></i>
                        <button 
                            type="button" 
                            onclick="togglePassword()"
                            class="absolute right-4 top-1/2 transform -translate-y-1/2 text-gray-400 hover:text-gray-600"
                        >
                            <i class="fas fa-eye" id="toggleIcon"></i>
                        </button>
                    </div>
                    @error('password')
                        <p class="text-red-500 text-xs mt-1">{{ $message }}</p>
                    @enderror
                </div>

                <!-- Remember Me & Forgot Password -->
                <div class="flex items-center justify-between text-sm">
                    <label class="flex items-center cursor-pointer">
                        <input 
                            type="checkbox" 
                            name="remember" 
                            class="w-4 h-4 text-blue-600 border-gray-300 rounded focus:ring-blue-500"
                        >
                        <span class="ml-2 text-gray-700">Remember me</span>
                    </label>
                    <a href="#" class="text-blue-600 hover:text-blue-700 font-medium">
                        Forgot password?
                    </a>
                </div>

                <!-- Login Button -->
                <button 
                    type="submit" 
                    id="loginBtn"
                    class="btn-primary w-full py-3 px-6 rounded-lg text-white font-semibold shadow-lg flex items-center justify-center space-x-2"
                >
                    <i class="fas fa-sign-in-alt"></i>
                    <span>Sign In</span>
                </button>
            </form>

            <!-- Divider -->
            <div class="relative my-6">
                <div class="absolute inset-0 flex items-center">
                    <div class="w-full border-t border-gray-300"></div>
                </div>
                <div class="relative flex justify-center text-sm">
                    @php($dev = config('services.developer'))
                    @php($phoneDigits = preg_replace('/\D+/', '', $dev['phone'] ?? ''))
                    <span class="px-4 bg-white text-gray-500">
                        <i class="fas fa-wrench mr-2"></i>
                        Powered by
                        @if(!empty($dev['website']))
                            <a href="https://{{ $dev['website'] }}" target="_blank" class="text-blue-600 hover:text-blue-800 font-semibold">{{ $dev['website'] }}</a>
                        @elseif(!empty($phoneDigits))
                            <a href="https://wa.me/{{ $phoneDigits }}" target="_blank" class="text-green-600 hover:text-green-800 font-semibold">{{ $dev['name'] ?? $phoneDigits }}</a>
                        @else
                            <span class="font-semibold">{{ $dev['name'] ?? 'Developer' }}</span>
                        @endif
                    </span>
                </div>
            </div>

            <!-- Footer Info -->
            <div class="text-center text-xs text-gray-500 space-y-2">
                <p>
                    <i class="fas fa-shield-alt mr-1"></i>
                    Secure & Encrypted Connection
                </p>
                <p class="flex items-center justify-center space-x-4">
                    <span><i class="fas fa-box mr-1"></i>Inventory</span>
                    <span><i class="fas fa-cash-register mr-1"></i>Sales</span>
                    <span><i class="fas fa-chart-line mr-1"></i>Reports</span>
                </p>
            </div>
        </div>

        <!-- Copyright -->
        <div class="text-center mt-6 text-white text-sm">
            <p>&copy; 2025 {{ $businessName ?? 'Vehicle POS' }}. All rights reserved.</p>
        </div>
    </div>

    <script>
        function togglePassword() {
            const passwordInput = document.getElementById('password');
            const toggleIcon = document.getElementById('toggleIcon');
            
            if (passwordInput.type === 'password') {
                passwordInput.type = 'text';
                toggleIcon.classList.remove('fa-eye');
                toggleIcon.classList.add('fa-eye-slash');
            } else {
                passwordInput.type = 'password';
                toggleIcon.classList.remove('fa-eye-slash');
                toggleIcon.classList.add('fa-eye');
            }
        }
        
        // Show loading animation when form is submitted
        document.querySelector('form').addEventListener('submit', function(e) {
            const loadingOverlay = document.getElementById('loadingOverlay');
            loadingOverlay.classList.add('active');
        });
        
        // Hide loading animation on page load
        window.addEventListener('load', function() {
            const loadingOverlay = document.getElementById('loadingOverlay');
            loadingOverlay.classList.remove('active');
        });
    </script>
</body>
</html>
