<div class="lg:col-span-1">
    <div class="bg-white rounded-xl shadow-md p-4 sticky top-4">
        <h3 class="font-bold text-gray-800 mb-4">
            <i class="fas fa-cog mr-2"></i>Settings Menu
        </h3>
        <nav class="space-y-1">
            <a href="{{ route('settings.business') }}" class="flex items-center space-x-3 px-4 py-3 rounded-lg {{ request()->routeIs('settings.business') ? 'bg-blue-50 text-blue-600 font-semibold' : 'hover:bg-gray-50 text-gray-700' }}">
                <i class="fas fa-building w-5"></i>
                <span>Business Info</span>
            </a>
            <a href="{{ route('settings.general') }}" class="flex items-center space-x-3 px-4 py-3 rounded-lg {{ request()->routeIs('settings.general') ? 'bg-blue-50 text-blue-600 font-semibold' : 'hover:bg-gray-50 text-gray-700' }}">
                <i class="fas fa-cog w-5"></i>
                <span>General Settings</span>
            </a>
            <a href="{{ route('settings.invoice') }}" class="flex items-center space-x-3 px-4 py-3 rounded-lg {{ request()->routeIs('settings.invoice') ? 'bg-blue-50 text-blue-600 font-semibold' : 'hover:bg-gray-50 text-gray-700' }}">
                <i class="fas fa-file-invoice w-5"></i>
                <span>Invoice Settings</span>
            </a>
            <a href="{{ route('settings.quotation') }}" class="flex items-center space-x-3 px-4 py-3 rounded-lg {{ request()->routeIs('settings.quotation') ? 'bg-blue-50 text-blue-600 font-semibold' : 'hover:bg-gray-50 text-gray-700' }}">
                <i class="fas fa-file-contract w-5"></i>
                <span>Quotation Settings</span>
            </a>
            <a href="{{ route('settings.pos') }}" class="flex items-center space-x-3 px-4 py-3 rounded-lg {{ request()->routeIs('settings.pos') ? 'bg-blue-50 text-blue-600 font-semibold' : 'hover:bg-gray-50 text-gray-700' }}">
                <i class="fas fa-cash-register w-5"></i>
                <span>POS Settings</span>
            </a>
            <a href="{{ route('settings.barcode') }}" class="flex items-center space-x-3 px-4 py-3 rounded-lg {{ request()->routeIs('settings.barcode') ? 'bg-blue-50 text-blue-600 font-semibold' : 'hover:bg-gray-50 text-gray-700' }}">
                <i class="fas fa-barcode w-5"></i>
                <span>Barcode Settings</span>
            </a>
            <a href="{{ route('notifications.index') }}" class="flex items-center space-x-3 px-4 py-3 rounded-lg hover:bg-gray-50 text-gray-700">
                <i class="fas fa-bell w-5"></i>
                <span>Notifications</span>
            </a>
        </nav>
    </div>
</div>
