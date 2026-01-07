<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>System Error</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <style> html, body { height:100%; } </style>
</head>
<body class="min-h-screen bg-gradient-to-br from-gray-900 via-gray-950 to-black text-white flex items-center justify-center">
    <div class="max-w-xl w-full p-6">
        <div class="backdrop-blur-md bg-red-700/10 border border-red-500/40 rounded-2xl p-8 shadow-2xl">
            <div class="flex items-center mb-4">
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" class="w-8 h-8 text-red-400 mr-2">
                    <path fill-rule="evenodd" d="M12 2.25c-.41 0-.8.157-1.1.44L2.44 10.15a1.5 1.5 0 000 2.2l8.46 7.46c.3.283.69.44 1.1.44s.8-.157 1.1-.44l8.46-7.46a1.5 1.5 0 000-2.2L13.1 2.69c-.3-.283-.69-.44-1.1-.44zm0 6.75a.75.75 0 01.75.75v4.5a.75.75 0 01-1.5 0v-4.5a.75.75 0 01.75-.75zm0 8.25a1.125 1.125 0 100-2.25 1.125 1.125 0 000 2.25z" clip-rule="evenodd" />
                </svg>
                <h1 class="text-2xl font-bold">Critical System Error</h1>
            </div>
            <p class="text-red-200 mb-4">Database is deleted or not connected. Please contact the Developer team.</p>
            <div class="bg-black/40 rounded-xl p-4 text-sm space-y-1">
                @php($dev = config('services.developer'))
                <p><span class="font-semibold">{{ $dev['name'] ?? 'Support' }}</span> — {{ $dev['website'] ?? '' }}</p>
                <p><span class="font-semibold">Phone:</span> {{ $dev['phone'] ?? '' }}</p>
                <p><span class="font-semibold">Email:</span> {{ $dev['email'] ?? '' }}</p>
            </div>
            <p class="mt-4 text-xs text-red-300">All system components are hidden to prevent further errors.</p>
        </div>
    </div>
</body>
</html>
