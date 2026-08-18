<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SavePreOrderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'customer_id' => ['required', Rule::exists('customers', 'id')->where('is_active', true)],
            'store_id' => ['nullable', Rule::exists('stores', 'id')->where('is_active', true)],
            'pre_order_date' => ['required', 'date'],
            'document_type' => ['required', Rule::in(['quotation', 'invoice'])],
            'vehicle_name' => ['required', 'string', 'max:255'],
            'registration_number' => ['nullable', 'string', 'max:255'],
            'chassis_number' => ['nullable', 'string', 'max:255'],
            'vehicle_description' => ['nullable', 'string', 'max:3000'],
            'vehicle_image' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
            'instructions' => ['nullable', 'string', 'max:5000'],
            'notes' => ['nullable', 'string', 'max:5000'],
            'expected_delivery_date' => ['nullable', 'date', 'after_or_equal:pre_order_date'],
            'bill_discount_type' => ['required', Rule::in(['fixed', 'percentage'])],
            'bill_discount_value' => ['required', 'numeric', 'min:0'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.product_id' => ['nullable', 'integer', 'exists:products,id'],
            'items.*.product_price_id' => ['nullable', 'integer', 'exists:product_prices,id'],
            'items.*.original_product_name' => ['required', 'string', 'max:255'],
            'items.*.description' => ['nullable', 'string', 'max:2000'],
            'items.*.quantity' => ['required', 'integer', 'min:1', 'max:1000000'],
            'items.*.unit_price' => ['required', 'numeric', 'min:0', 'max:999999999999.99'],
            'items.*.quoted_price' => ['nullable', 'numeric', 'min:0', 'max:999999999999.99'],
            'items.*.discount_type' => ['required', Rule::in(['fixed', 'percentage'])],
            'items.*.discount_value' => ['required', 'numeric', 'min:0'],
            'items.*.notes' => ['nullable', 'string', 'max:2000'],
        ];
    }

    public function after(): array
    {
        return [function ($validator) {
            if ($this->input('bill_discount_type') === 'percentage' && (float) $this->input('bill_discount_value') > 100) {
                $validator->errors()->add('bill_discount_value', 'Percentage discount cannot exceed 100%.');
            }
            foreach ($this->input('items', []) as $index => $item) {
                if (($item['discount_type'] ?? null) === 'percentage' && (float) ($item['discount_value'] ?? 0) > 100) {
                    $validator->errors()->add("items.$index.discount_value", 'Percentage discount cannot exceed 100%.');
                }
                if (! empty($item['product_price_id']) && ! empty($item['product_id'])) {
                    $belongs = \App\Models\ProductPrice::query()
                        ->whereKey($item['product_price_id'])
                        ->where('product_id', $item['product_id'])
                        ->exists();
                    if (! $belongs) {
                        $validator->errors()->add("items.$index.product_price_id", 'The selected price does not belong to this product.');
                    }
                }
            }
        }];
    }
}
