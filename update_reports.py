import re

with open('app/Http/Controllers/ReportController.php', 'r') as f:
    content = f.read()

# Add $stores = \App\Models\Store::where('is_active', true)->orderBy('name')->get(); to all methods
methods = ['sales', 'purchase', 'profitLoss', 'stock', 'expense', 'vat', 'rateConversion', 'receive', 'debit', 'dueBills', 'customerDue', 'neverSold', 'unsoldRecently']

# We need to inject the stores variable and compact('stores')

for method in methods:
    # Find the function definition
    pattern = r'(public function ' + method + r'\(Request \$request\)(?:\s*:\s*\w+)?\s*\{)'
    match = re.search(pattern, content)
    if not match:
        continue
    
    # Inject stores query right after start
    injection = "\n        $stores = \App\Models\Store::where('is_active', true)->orderBy('name')->get();"
    content = content[:match.end()] + injection + content[match.end():]

    # Find the return view(..., compact(...)) or similar and inject 'stores'
    # Actually, some use compact, some use associative arrays. It's safer to just let the view have access to $stores.
    # Alternatively, just replace compact(' with compact('stores', '
    # Let's find the return view inside the method
    # This is a bit tricky to do with regex. Let's just do it manually for the compacts.

with open('app/Http/Controllers/ReportController.php', 'w') as f:
    f.write(content)
