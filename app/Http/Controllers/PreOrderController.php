<?php

namespace App\Http\Controllers;

use App\Http\Requests\SavePreOrderRequest;
use App\Models\Customer;
use App\Models\Payment;
use App\Models\PreOrder;
use App\Models\PreOrderItem;
use App\Models\Product;
use App\Models\Setting;
use App\Models\Store;
use App\Models\TaxSetting;
use App\Services\PreOrderImageService;
use App\Services\PreOrderService;
use App\Services\TaxCalculationService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class PreOrderController extends Controller
{
    public function __construct(
        private readonly PreOrderService $service,
        private readonly PreOrderImageService $images,
    ) {}

    public function index(Request $request, ?string $status = null)
    {
        $query = $this->filteredQuery($request, $status)->with(['customer', 'creator', 'store', 'sale']);
        $preOrders = $query->paginate(20)->withQueryString();
        $customers = Customer::query()->where('is_active', true)->orderBy('name')->get(['id', 'name', 'phone']);

        return view('preorders.index', [
            'preOrders' => $preOrders,
            'customers' => $customers,
            'activeStatus' => $status ?: $request->input('status'),
            'currency' => Setting::get('currency_symbol', 'Rs '),
        ]);
    }

    public function report(Request $request)
    {
        $orders = $this->filteredQuery($request)->get();
        $summary = [
            'total' => $orders->count(),
            'pending' => $orders->where('status', 'pending')->sum('grand_total'),
            'completed' => $orders->where('status', 'completed')->sum('grand_total'),
            'cancelled' => $orders->where('status', 'cancelled')->count(),
            'paid' => $orders->where('status', 'completed')->sum('paid_amount'),
            'due' => $orders->where('status', 'completed')->sum('due_amount'),
        ];
        $customers = Customer::query()->where('is_active', true)->orderBy('name')->get(['id', 'name']);

        return view('preorders.report', compact('orders', 'summary', 'customers'));
    }

    public function create(Request $request)
    {
        return view('preorders.create', $this->formData($request));
    }

    public function store(SavePreOrderRequest $request)
    {
        $data = $request->validated();
        $this->authorizeStore($request, $data['store_id'] ?? null);
        $newImage = null;
        try {
            if ($request->hasFile('vehicle_image')) {
                $newImage = $this->images->store($request->file('vehicle_image'));
                $data['vehicle_image'] = $newImage;
            }
            $preOrder = $this->service->save($data, (int) $request->user()->id);

            return redirect()->route('preorders.show', $preOrder)->with('success', 'Pre-Order created successfully.');
        } catch (\Throwable $e) {
            if ($newImage) {
                $this->images->delete($newImage);
            }
            throw $e;
        }
    }

    public function show(Request $request, PreOrder $preOrder)
    {
        $this->authorizeStore($request, $preOrder->store_id);
        
        $needsReload = false;
        if ($preOrder->status === 'pending') {
            foreach ($preOrder->items as $item) {
                if (!$item->product_id) {
                    $match = \App\Models\Product::where('name', $item->original_product_name)
                        ->where('is_active', true)->first();
                    if ($match) {
                        try {
                            $this->service->syncProduct($preOrder, $item, $match, null, (int) $request->user()->id, 'keep');
                            $needsReload = true;
                        } catch (\Exception $e) {}
                    }
                }
            }
        }
        
        if ($needsReload) {
            $preOrder->refresh();
        }

        $preOrder->load([
            'customer', 'store', 'creator', 'updater', 'completer', 'canceller',
            'items.product.activePrices', 'items.productPrice', 'activities.user',
            'sale.payments.user', 'sale.chequePayments.user', 'sale.chequePayments.processor',
        ]);
        foreach ($preOrder->items as $item) {
            $item->current_stock = $item->product
                ? $this->service->currentStock($item->product, $preOrder->store_id, $item->product_price_id)
                : null;
            $item->current_selling_price = $item->product ? $this->service->currentSellingPrice($item) : null;
        }

        return view('preorders.show', [
            'preOrder' => $preOrder,
            'currency' => Setting::get('currency_symbol', 'Rs '),
        ]);
    }

    public function edit(Request $request, PreOrder $preOrder)
    {
        $this->authorizeStore($request, $preOrder->store_id);
        abort_unless($preOrder->status === 'pending', 422, 'Only pending Pre-Orders can be edited.');
        $preOrder->load(['items.product', 'items.productPrice']);
        foreach ($preOrder->items as $item) {
            $item->current_stock = $item->product
                ? $this->service->currentStock($item->product, $preOrder->store_id, $item->product_price_id)
                : null;
        }

        return view('preorders.edit', array_merge($this->formData($request), compact('preOrder')));
    }

    public function update(SavePreOrderRequest $request, PreOrder $preOrder)
    {
        $this->authorizeStore($request, $preOrder->store_id);
        $data = $request->validated();
        $this->authorizeStore($request, $data['store_id'] ?? null);
        $oldImage = $preOrder->vehicle_image;
        $newImage = null;
        try {
            if ($request->hasFile('vehicle_image')) {
                $newImage = $this->images->store($request->file('vehicle_image'));
                $data['vehicle_image'] = $newImage;
            } else {
                $data['vehicle_image'] = $oldImage;
            }
            $preOrder = $this->service->save($data, (int) $request->user()->id, $preOrder);
            if ($newImage && $oldImage) {
                $this->images->delete($oldImage);
            }

            return redirect()->route('preorders.show', $preOrder)->with('success', 'Pre-Order updated successfully.');
        } catch (\Throwable $e) {
            if ($newImage) {
                $this->images->delete($newImage);
            }
            throw $e;
        }
    }

    public function cancel(Request $request, PreOrder $preOrder)
    {
        $this->authorizeStore($request, $preOrder->store_id);
        $validated = $request->validate(['reason' => ['nullable', 'string', 'max:2000']]);
        $this->service->cancel($preOrder, $validated['reason'] ?? null, (int) $request->user()->id);

        return back()->with('success', 'Pre-Order cancelled.');
    }

    public function reopen(Request $request, PreOrder $preOrder)
    {
        $this->authorizeStore($request, $preOrder->store_id);
        $validated = $request->validate(['reason' => ['nullable', 'string', 'max:2000']]);
        $this->service->reopen($preOrder, $validated['reason'] ?? null, (int) $request->user()->id);

        return back()->with('success', 'Pre-Order reopened as Pending.');
    }

    public function complete(Request $request, PreOrder $preOrder)
    {
        $this->authorizeStore($request, $preOrder->store_id);
        $validated = $request->validate([
            'payments' => ['nullable', 'array'],
            'payments.*.method' => ['required', Rule::in(['cash', 'bank_deposit', 'bank_transfer', 'card', 'mobile_payment', 'cheque', 'due'])],
            'payments.*.amount' => ['nullable', 'numeric', 'min:0'],
            'payments.*.date' => ['nullable', 'date'],
            'payments.*.reference' => ['nullable', 'string', 'max:191'],
            'payments.*.cheque_number' => ['nullable', 'string', 'max:100'],
            'payments.*.cheque_date' => ['nullable', 'date'],
            'payments.*.bank_name' => ['nullable', 'string', 'max:191'],
            'payments.*.account_name' => ['nullable', 'string', 'max:191'],
            'payments.*.notes' => ['nullable', 'string', 'max:1000'],
        ]);
        $preOrder = $this->service->complete($preOrder, $validated['payments'] ?? [], (int) $request->user()->id);

        return redirect()->route('preorders.show', $preOrder)->with('success', 'Pre-Order completed as sale '.$preOrder->sale->sale_no.'.');
    }

    public function syncProduct(Request $request, PreOrder $preOrder, PreOrderItem $item)
    {
        $this->authorizeStore($request, $preOrder->store_id);
        abort_unless($item->pre_order_id === $preOrder->id, 404);
        $validated = $request->validate([
            'product_id' => ['required', Rule::exists('products', 'id')->where('is_active', true)],
            'product_price_id' => ['nullable', 'exists:product_prices,id'],
            'price_action' => ['required', Rule::in(['keep', 'current', 'custom'])],
            'custom_price' => ['nullable', 'numeric', 'min:0'],
        ]);
        $product = Product::findOrFail($validated['product_id']);
        $this->service->syncProduct(
            $preOrder, $item, $product, $validated['product_price_id'] ?? null,
            (int) $request->user()->id, $validated['price_action'],
            isset($validated['custom_price']) ? (float) $validated['custom_price'] : null
        );

        return back()->with('success', 'Product linked successfully.');
    }

    public function changePrice(Request $request, PreOrder $preOrder, PreOrderItem $item)
    {
        $this->authorizeStore($request, $preOrder->store_id);
        abort_unless($item->pre_order_id === $preOrder->id, 404);
        $validated = $request->validate([
            'price_action' => ['required', Rule::in(['keep', 'current', 'custom'])],
            'custom_price' => ['nullable', 'numeric', 'min:0'],
        ]);
        $this->service->changePrice(
            $preOrder, $item, $validated['price_action'],
            isset($validated['custom_price']) ? (float) $validated['custom_price'] : null,
            (int) $request->user()->id
        );

        return back()->with('success', 'Item price updated.');
    }

    public function addPayment(Request $request, PreOrder $preOrder)
    {
        $this->authorizeStore($request, $preOrder->store_id);
        $rules = [
            'amount' => ['required', 'numeric', 'min:0.01'],
            'payment_method' => ['required', Rule::in(['cash', 'bank_deposit', 'bank_transfer', 'card', 'mobile_payment', 'cheque'])],
            'payment_date' => ['required', 'date'],
            'reference_no' => ['nullable', 'string', 'max:191'],
            'notes' => ['nullable', 'string', 'max:1000'],
            'cheque_number' => ['required_if:payment_method,cheque', 'nullable', 'string', 'max:100'],
            'cheque_date' => ['required_if:payment_method,cheque', 'nullable', 'date'],
            'bank_name' => ['nullable', 'string', 'max:191'],
            'account_name' => ['nullable', 'string', 'max:191'],
        ];
        $this->service->addPayment($preOrder, $request->validate($rules), (int) $request->user()->id);

        return back()->with('success', 'Payment collected successfully.');
    }

    public function deletePayment(Request $request, PreOrder $preOrder, Payment $payment)
    {
        $this->authorizeStore($request, $preOrder->store_id);
        $this->service->deletePayment($preOrder, $payment, (int) $request->user()->id);

        return back()->with('success', 'Payment removed and accounting reversed.');
    }

    public function quotationPdf(Request $request, PreOrder $preOrder)
    {
        return $this->pdf($request, $preOrder, 'quotation');
    }

    public function invoicePdf(Request $request, PreOrder $preOrder)
    {
        return $this->pdf($request, $preOrder, 'invoice');
    }

    public function searchProducts(Request $request)
    {
        $request->validate(['q' => ['nullable', 'string', 'max:100'], 'store_id' => ['nullable', 'exists:stores,id']]);
        $this->authorizeStore($request, $request->integer('store_id') ?: null);
        $term = trim((string) $request->input('q'));
        $settings = TaxSetting::current();
        $calculator = app(TaxCalculationService::class);
        $products = Product::query()->with(['activePrices', 'taxSetting'])->where('is_active', true)
            ->when($term !== '', fn ($query) => $query->where(fn ($q) => $q
                ->where('name', 'like', "%{$term}%")->orWhere('sku', 'like', "%{$term}%")
                ->orWhere('barcode', 'like', "%{$term}%")))
            ->orderBy('name')->limit(20)->get();

        return response()->json($products->map(function (Product $product) use ($request, $settings, $calculator) {
            $price = $product->activePrices->firstWhere('is_default', true) ?: $product->activePrices->first();
            $rules = $calculator->productRules($product, $settings, 'sale');

            return [
                'id' => $product->id, 'name' => $product->name, 'sku' => $product->sku,
                'product_price_id' => $price?->id,
                'selling_price' => (float) ($price?->selling_price ?? $product->selling_price),
                'stock' => $this->service->currentStock($product, $request->integer('store_id') ?: null, $price?->id),
                'tax' => array_merge($rules, ['vat_enabled' => (bool) $settings->vat_enabled]),
            ];
        })->values());
    }

    public function quickCustomer(Request $request)
    {
        abort_unless($request->user()?->hasPermission('customers.create'), 403);
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'phone' => ['required', 'string', 'max:30'],
            'email' => ['nullable', 'email', 'max:255'],
            'address' => ['nullable', 'string', 'max:1000'],
        ]);
        $customer = Customer::create(array_merge($validated, ['is_active' => true, 'opening_balance' => 0]));

        return response()->json($customer, 201);
    }

    private function filteredQuery(Request $request, ?string $forcedStatus = null)
    {
        $query = PreOrder::query()->latest('pre_order_date')->latest('id');
        $status = $forcedStatus ?: $request->input('status');
        if (in_array($status, ['pending', 'completed', 'cancelled'], true)) {
            $query->where('status', $status);
        }
        if ($request->filled('payment_status')) {
            $query->where('payment_status', $request->input('payment_status'));
        }
        if ($request->filled('customer_id')) {
            $query->where('customer_id', $request->integer('customer_id'));
        }
        if ($request->filled('date_from')) {
            $query->whereDate('pre_order_date', '>=', $request->input('date_from'));
        }
        if ($request->filled('date_to')) {
            $query->whereDate('pre_order_date', '<=', $request->input('date_to'));
        }
        if ($request->filled('delivery_from')) {
            $query->whereDate('expected_delivery_date', '>=', $request->input('delivery_from'));
        }
        if ($request->filled('delivery_to')) {
            $query->whereDate('expected_delivery_date', '<=', $request->input('delivery_to'));
        }
        if ($request->filled('q')) {
            $term = trim((string) $request->input('q'));
            $query->where(function ($q) use ($term) {
                $q->where('pre_order_number', 'like', "%{$term}%")
                    ->orWhere('vehicle_name', 'like', "%{$term}%")
                    ->orWhereHas('customer', fn ($customer) => $customer->where('name', 'like', "%{$term}%")->orWhere('phone', 'like', "%{$term}%"))
                    ->orWhereHas('items', fn ($item) => $item->where('original_product_name', 'like', "%{$term}%"))
                    ->orWhereHas('sale', fn ($sale) => $sale->where('sale_no', 'like', "%{$term}%"));
            });
        }
        $user = $request->user();
        if ($user && ! $user->isSystemAdministrator()) {
            $storeIds = $user->stores()->pluck('stores.id');
            if ($storeIds->isNotEmpty()) {
                $query->where(fn ($q) => $q->whereNull('store_id')->orWhereIn('store_id', $storeIds));
            }
        }

        return $query;
    }

    private function formData(Request $request): array
    {
        $stores = Store::query()->where('is_active', true)->orderByDesc('is_default')->orderBy('name')->get();
        if (! $request->user()->isSystemAdministrator() && $request->user()->stores()->exists()) {
            $allowed = $request->user()->stores()->pluck('stores.id');
            $stores = $stores->whereIn('id', $allowed)->values();
        }

        return [
            'customers' => Customer::query()->where('is_active', true)->orderBy('name')->get(),
            'stores' => $stores,
            'currency' => Setting::get('currency_symbol', 'Rs '),
            'taxSettings' => TaxSetting::current(),
            'nextNumber' => PreOrder::generateNumber(),
        ];
    }

    private function authorizeStore(Request $request, ?int $storeId): void
    {
        if (! $storeId || $request->user()?->isSystemAdministrator()) {
            return;
        }
        if ($request->user()->stores()->exists() && ! $request->user()->stores()->where('stores.id', $storeId)->exists()) {
            abort(403, 'You do not have access to this store.');
        }
    }

    private function pdf(Request $request, PreOrder $preOrder, string $kind)
    {
        $this->authorizeStore($request, $preOrder->store_id);
        $preOrder->load(['customer', 'store', 'creator', 'items.product', 'sale.payments', 'sale.chequePayments']);
        $shop = [
            'name' => Setting::get('shop_name', config('app.name', 'Vehicle POS')),
            'tagline' => Setting::get('shop_tagline', 'Auto Parts System'),
            'address' => Setting::get('shop_address', ''), 'phone' => Setting::get('shop_phone', ''),
            'email' => Setting::get('shop_email', ''), 'logo' => Setting::get('shop_logo'),
            'terms' => Setting::get('quotation_terms', ''),
        ];
        abort_unless(app()->bound('dompdf.wrapper'), 500, 'PDF export library not installed.');
        $pdf = app('dompdf.wrapper')->loadView('preorders.pdf', [
            'preOrder' => $preOrder, 'shop' => $shop, 'kind' => $kind,
            'currency' => Setting::get('currency_symbol', 'Rs '),
        ])->setPaper('a4', 'portrait');

        return $pdf->stream(strtolower($kind).'-'.$preOrder->pre_order_number.'.pdf');
    }
}
