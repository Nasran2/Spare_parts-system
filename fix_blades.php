<?php

$dir = 'resources/views/reports';
$files = glob($dir . '/*.blade.php');

$dropdown = '
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
';

foreach ($files as $file) {
    $content = file_get_contents($file);
    // Find where the buttons are
    // Usually <div class="flex items-center
    // or <div class="mt-
    // Let\'s just insert it right after <form ...>
    if (preg_match('/(<form[^>]*>)/', $content, $matches)) {
        // If already has store_id, skip
        if (strpos($content, 'name="store_id"') !== false) {
            continue;
        }
        $content = str_replace($matches[1], $matches[1] . $dropdown, $content);
        file_put_contents($file, $content);
        echo "Updated $file\n";
    }
}
