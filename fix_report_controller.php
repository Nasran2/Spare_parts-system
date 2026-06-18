<?php

$file = 'app/Http/Controllers/ReportController.php';
$content = file_get_contents($file);

// Replace sale and purchase and expense query chains
$replacements = [
    "->when(\$to, fn(\$q) => \$q->whereDate('sale_date', '<=', \$to))" => "->when(\$to, fn(\$q) => \$q->whereDate('sale_date', '<=', \$to))\n            ->when(\$request->filled('store_id'), fn(\$q) => \$q->where('store_id', \$request->input('store_id')))",
    
    "->when(\$to, fn(\$q) => \$q->whereDate('purchase_date', '<=', \$to))" => "->when(\$to, fn(\$q) => \$q->whereDate('purchase_date', '<=', \$to))\n            ->when(\$request->filled('store_id'), fn(\$q) => \$q->where('store_id', \$request->input('store_id')))",
    
    "->when(\$to, fn(\$q) => \$q->whereDate('expense_date', '<=', \$to))" => "->when(\$to, fn(\$q) => \$q->whereDate('expense_date', '<=', \$to))\n            ->when(\$request->filled('store_id'), fn(\$q) => \$q->where('store_id', \$request->input('store_id')))",

    "->when(\$to, fn(\$q) => \$q->whereDate('payment_date', '<=', \$to))" => "->when(\$to, fn(\$q) => \$q->whereDate('payment_date', '<=', \$to))\n            ->when(\$request->filled('store_id'), fn(\$q) => \$q->where('store_id', \$request->input('store_id')))",
];

$content = str_replace(array_keys($replacements), array_values($replacements), $content);

file_put_contents($file, $content);
echo "Replaced successfully\n";
