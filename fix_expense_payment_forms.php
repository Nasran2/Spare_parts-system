<?php

$expensesCreate = file_get_contents('resources/views/expenses/create.blade.php');
$storeHtml = '
            <div>
                <label class="block text-sm font-medium text-gray-700">Store</label>
                <select name="store_id" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                    <option value="">Select Store</option>
                    @foreach(\App\Models\Store::where(\'is_active\', true)->get() as $store)
                        <option value="{{ $store->id }}" @selected(old(\'store_id\') == $store->id)>{{ $store->name }}</option>
                    @endforeach
                </select>
                @error(\'store_id\') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
            </div>
';
$expensesCreate = str_replace('<div>
                <label class="block text-sm font-medium text-gray-700">Category</label>', $storeHtml . '            <div>
                <label class="block text-sm font-medium text-gray-700">Category</label>', $expensesCreate);
file_put_contents('resources/views/expenses/create.blade.php', $expensesCreate);


$expensesEdit = file_get_contents('resources/views/expenses/edit.blade.php');
$storeEditHtml = '
            <div>
                <label class="block text-sm font-medium text-gray-700">Store</label>
                <select name="store_id" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                    <option value="">Select Store</option>
                    @foreach(\App\Models\Store::where(\'is_active\', true)->get() as $store)
                        <option value="{{ $store->id }}" @selected(old(\'store_id\', $expense->store_id) == $store->id)>{{ $store->name }}</option>
                    @endforeach
                </select>
                @error(\'store_id\') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
            </div>
';
$expensesEdit = str_replace('<div>
                <label class="block text-sm font-medium text-gray-700">Category</label>', $storeEditHtml . '            <div>
                <label class="block text-sm font-medium text-gray-700">Category</label>', $expensesEdit);
file_put_contents('resources/views/expenses/edit.blade.php', $expensesEdit);


$paymentsCreate = file_get_contents('resources/views/payments/create.blade.php');
$paymentStoreHtml = '
        <div class="mb-4">
            <label class="block text-sm font-medium text-gray-700">Store</label>
            <select name="store_id" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                <option value="">Select Store</option>
                @foreach(\App\Models\Store::where(\'is_active\', true)->get() as $store)
                    <option value="{{ $store->id }}" @selected(old(\'store_id\') == $store->id)>{{ $store->name }}</option>
                @endforeach
            </select>
            @error(\'store_id\') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
        </div>
';
$paymentsCreate = str_replace('<div class="mb-4">
            <label class="block text-sm font-medium text-gray-700">Payment Date</label>', $paymentStoreHtml . '        <div class="mb-4">
            <label class="block text-sm font-medium text-gray-700">Payment Date</label>', $paymentsCreate);
file_put_contents('resources/views/payments/create.blade.php', $paymentsCreate);

echo "Updated create/edit views\n";
