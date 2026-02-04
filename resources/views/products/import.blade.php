@extends('layouts.app')

@section('title', 'Import Products')
@section('page-title', 'Import Products')

@section('content')
<div class="space-y-6">

    <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4">
        <div>
            <h3 class="text-lg font-semibold text-gray-900">Import Products</h3>
            <p class="text-sm text-gray-600">Bring your catalog into Vehicle POS using the Excel template.</p>
        </div>
        <a 
            href="{{ route('products.import.template') }}" 
            data-skip-page-loader="true"
            class="inline-flex items-center px-5 py-3 bg-gradient-to-r from-indigo-600 to-cyan-500 text-white rounded-xl shadow-lg hover:from-indigo-700 hover:to-cyan-600 transition"
        >
            <i class="fas fa-file-download mr-2"></i>Download Template
        </a>
    </div>

    @if(session('success'))
    <div class="rounded-2xl bg-emerald-50 border border-emerald-100 px-5 py-4 text-sm font-medium text-emerald-700">
        {{ session('success') }}
    </div>
    @endif

    @if(session('error'))
    <div class="rounded-2xl bg-red-50 border border-red-100 px-5 py-4 text-sm font-medium text-red-700">
        {{ session('error') }}
    </div>
    @endif

    @if(session('import_errors'))
    <div class="rounded-2xl bg-yellow-50 border border-yellow-100 px-5 py-4 text-sm text-yellow-900 space-y-2">
        <p class="font-semibold text-gray-800">Please review the following notices:</p>
        <ul class="list-disc list-inside space-y-1">
            @foreach(session('import_errors') as $issue)
                <li>{{ $issue }}</li>
            @endforeach
        </ul>
    </div>
    @endif

    <div class="bg-white rounded-2xl shadow-xl border border-gray-100 p-6">
        <form method="POST" action="{{ route('products.import.store') }}" enctype="multipart/form-data" class="space-y-6">
            @csrf
            <div class="space-y-2">
                <label class="block text-sm font-medium text-gray-600">Select Excel file</label>
                <input 
                    type="file" 
                    name="file" 
                    accept=".xlsx,.xls,.csv" 
                    required 
                    class="w-full text-sm text-gray-600 focus:outline-none focus:ring-2 focus:ring-blue-500 border-2 border-dashed border-gray-300 rounded-2xl p-4"
                >
                @error('file')
                    <p class="text-xs text-red-600">{{ $message }}</p>
                @enderror
            </div>
            <div class="flex flex-wrap gap-3">
                <button 
                    type="submit" 
                    class="inline-flex items-center px-5 py-3 bg-gradient-to-r from-blue-600 to-blue-700 text-white rounded-xl shadow-lg hover:from-blue-700 hover:to-blue-800 transition"
                >
                    <i class="fas fa-upload mr-2"></i>Upload and Import
                </button>
                <a 
                    href="{{ route('products.import.template') }}" 
                    data-skip-page-loader="true"
                    class="inline-flex items-center px-5 py-3 border border-gray-300 rounded-xl text-gray-700 hover:border-gray-400 transition"
                >
                    <i class="fas fa-file-alt mr-2"></i>Download Template
                </a>
            </div>
            <p class="text-xs text-gray-400">Keep the header row untouched and do not rename the columns.</p>
        </form>
    </div>

    <div class="bg-white rounded-2xl shadow-xl border border-gray-100 p-6 space-y-3">
        <h4 class="text-sm font-semibold text-gray-800">Template guidelines</h4>
        <ul class="space-y-2 text-sm text-gray-600">
            <li><span class="font-semibold text-gray-800">Name</span> is mandatory for every row.</li>
            <li>Use the dropdowns in <span class="font-semibold text-gray-800">Category Names</span>, <span class="font-semibold text-gray-800">Brand Name</span>, and <span class="font-semibold text-gray-800">Unit Short Name</span>; they are seeded from your active master data.</li>
            <li>List <span class="font-semibold text-gray-800">Category Names</span> separated by <span class="font-semibold">|</span>; categories must already exist in the system.</li>
            <li>Provide the <span class="font-semibold text-gray-800">Unit Short Name</span> (case-insensitive) that matches an active unit.</li>
            <li><span class="font-semibold">SKU</span> is optional; leaving it empty auto-generates a unique SKU and barcode.</li>
            <li><span class="font-semibold text-gray-800">Brand Name</span> is optional but must match an existing brand when supplied.</li>
            <li>Cost and selling prices default to 0 when left blank, and stock quantities fall back to 0.</li>
            <li><span class="font-semibold text-gray-800">Profit margin</span> columns calculate automatically once you fill both the cost and selling prices.</li>
        </ul>
    </div>

</div>
@endsection
