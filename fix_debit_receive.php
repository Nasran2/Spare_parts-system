<?php
$content = file_get_contents('resources/views/reports/debit.blade.php');
$form = '
    <form method="get" class="bg-white p-4 rounded shadow flex flex-wrap gap-4 items-end mb-6">
        <div>
            <label class="text-sm font-medium text-gray-600">From</label>
            <input type="date" name="from" value="{{ request(\'from\') }}" class="mt-1 border rounded px-3 py-2 text-sm w-48" />
        </div>
        <div>
            <label class="text-sm font-medium text-gray-600">To</label>
            <input type="date" name="to" value="{{ request(\'to\') }}" class="mt-1 border rounded px-3 py-2 text-sm w-48" />
        </div>
        <div>
            <label class="text-sm font-medium text-gray-600">Store</label>
            <select name="store_id" class="mt-1 border rounded px-3 py-2 text-sm w-48 bg-white">
                <option value="">All Stores</option>
                @if(isset($stores))
                    @foreach($stores as $s)
                        <option value="{{ $s->id }}" @selected(request(\'store_id\') == $s->id)>{{ $s->name }}</option>
                    @endforeach
                @endif
            </select>
        </div>
        <div class="flex items-center gap-2">
            <button class="bg-blue-600 text-white px-4 py-2 rounded text-sm">Filter</button>
            <a href="{{ route(\'reports.debit\') }}" class="text-sm text-gray-600 hover:text-gray-800">Reset</a>
        </div>
    </form>
';
$content = str_replace('<div class="bg-white rounded-xl shadow-md p-6">', $form . '<div class="bg-white rounded-xl shadow-md p-6">', $content);
file_put_contents('resources/views/reports/debit.blade.php', $content);

$content = file_get_contents('resources/views/reports/receive.blade.php');
$form = str_replace('reports.debit', 'reports.receive', $form);
$content = str_replace('<div class="bg-white rounded-xl shadow-md p-6">', $form . '<div class="bg-white rounded-xl shadow-md p-6">', $content);
file_put_contents('resources/views/reports/receive.blade.php', $content);

echo "Added forms to debit and receive views\n";
