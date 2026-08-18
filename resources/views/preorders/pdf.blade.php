<!DOCTYPE html>
<html><head><meta charset="UTF-8"><title>{{ ucfirst($kind) }} {{ $preOrder->pre_order_number }}</title><style>
body{font-family:DejaVu Sans,Arial,sans-serif;font-size:11px;color:#1f2937}.header{border-bottom:2px solid #1d4ed8;padding-bottom:10px;margin-bottom:12px}.logo{max-height:65px;max-width:145px;float:left;margin-right:14px}.title{font-size:23px;color:#1d4ed8;margin:0}.right{float:right;text-align:right}.clear{clear:both}.muted{color:#6b7280}.box{border:1px solid #d1d5db;border-radius:5px;padding:9px;margin-top:10px}.grid{width:100%;border-collapse:separate;border-spacing:8px 0}.grid td{width:50%;vertical-align:top}.items{width:100%;border-collapse:collapse;margin-top:13px}.items th,.items td{border:1px solid #d1d5db;padding:6px}.items th{background:#eff6ff;text-align:left;color:#1e40af}.num{text-align:right}.totals{width:310px;margin-left:auto;margin-top:12px;border-collapse:collapse}.totals td{padding:4px 7px}.grand{font-size:14px;font-weight:bold;border-top:2px solid #1d4ed8;color:#1d4ed8}.vehicle-img{max-width:210px;max-height:125px;object-fit:contain;border:1px solid #ddd;padding:3px}.badge{display:inline-block;padding:2px 7px;border:1px solid #64748b;border-radius:10px}.footer{margin-top:20px;text-align:center;color:#6b7280;font-size:10px}.notes{white-space:pre-wrap}
</style></head><body>
<div class="header">
    @if(!empty($shop['logo']) && is_file(public_path($shop['logo'])))
        <img class="logo" src="{{ public_path($shop['logo']) }}">
    @endif
    <div class="right">
        <span class="badge">{{ $preOrder->pre_order_number }}</span><br>
        <span class="muted">Date: {{ $preOrder->pre_order_date->format('Y-m-d') }}</span>
        @if($preOrder->expected_delivery_date)
            <br><span class="muted">Expected Delivery: {{ $preOrder->expected_delivery_date->format('Y-m-d') }}</span>
        @endif
        @if($kind === 'invoice' && $preOrder->sale)
            <br><strong>Invoice: {{ $preOrder->sale->sale_no }}</strong>
        @endif
    </div>
    <h1 class="title">{{ $kind === 'quotation' ? 'QUOTATION' : 'PRE-ORDER INVOICE' }}</h1>
    <strong style="font-size:15px">{{ $shop['name'] }}</strong>
    @if($shop['tagline'])<div class="muted">{{ $shop['tagline'] }}</div>@endif
    <div class="muted">
        {{ $shop['address'] }}
        @if($shop['phone']) · {{ $shop['phone'] }} @endif
        @if($shop['email']) · {{ $shop['email'] }} @endif
    </div>
    <div class="clear"></div>
</div>
<table class="grid"><tr><td class="box"><strong>Customer</strong><br>{{ $preOrder->customer->name }}@if($preOrder->customer->phone)<br>{{ $preOrder->customer->phone }}@endif @if($preOrder->customer->email)<br>{{ $preOrder->customer->email }}@endif @if($preOrder->customer->address)<br>{{ $preOrder->customer->address }}@endif</td><td class="box"><strong>Vehicle</strong><br>{{ $preOrder->vehicle_name }}@if($preOrder->registration_number)<br>Registration: {{ $preOrder->registration_number }}@endif @if($preOrder->chassis_number)<br>Chassis: {{ $preOrder->chassis_number }}@endif @if($preOrder->vehicle_description)<br>{{ $preOrder->vehicle_description }}@endif</td></tr></table>
@if($preOrder->vehicle_image_absolute_path)<div class="box"><strong>Vehicle Image</strong><br><img class="vehicle-img" src="{{ $preOrder->vehicle_image_absolute_path }}"></div>@endif
<table class="items"><thead><tr><th>#</th><th>Product / Part</th><th class="num">Qty</th><th class="num">Unit Price</th><th class="num">Discount</th><th class="num">Total</th></tr></thead><tbody>@foreach($preOrder->items as $i=>$item)<tr><td>{{ $i+1 }}</td><td><strong>{{ $item->original_product_name }}</strong>@if($item->description)<br><span class="muted">{{ $item->description }}</span>@endif</td><td class="num">{{ $item->quantity }}</td><td class="num">{{ $currency }}{{ number_format((float)$item->final_price,2) }}</td><td class="num">{{ $currency }}{{ number_format((float)$item->discount_amount,2) }}</td><td class="num">{{ $currency }}{{ number_format((float)$item->line_total,2) }}</td></tr>@endforeach</tbody></table>
<table class="totals"><tr><td>Subtotal</td><td class="num">{{ $currency }}{{ number_format((float)$preOrder->subtotal,2) }}</td></tr><tr><td>Discount</td><td class="num">{{ $currency }}{{ number_format((float)$preOrder->discount_amount,2) }}</td></tr><tr><td>Tax</td><td class="num">{{ $currency }}{{ number_format((float)$preOrder->tax_amount,2) }}</td></tr><tr class="grand"><td>Grand Total</td><td class="num">{{ $currency }}{{ number_format((float)$preOrder->grand_total,2) }}</td></tr>@if($kind==='invoice')<tr><td>Paid</td><td class="num">{{ $currency }}{{ number_format((float)$preOrder->paid_amount,2) }}</td></tr><tr><td>Pending Cheques</td><td class="num">{{ $currency }}{{ number_format((float)$preOrder->held_cheque_amount,2) }}</td></tr><tr><td>Due</td><td class="num">{{ $currency }}{{ number_format((float)$preOrder->due_amount,2) }}</td></tr><tr><td>Payment Status</td><td class="num">{{ $preOrder->payment_status === 'partial' ? 'Partially Paid' : ucfirst($preOrder->payment_status) }}</td></tr>@endif</table>
@if($preOrder->instructions || $preOrder->notes)<div class="box notes"><strong>Notes / Instructions</strong><br>{{ $preOrder->instructions }}@if($preOrder->notes)<br>{{ $preOrder->notes }}@endif</div>@endif
@if($kind==='quotation' && $shop['terms'])<div class="box notes"><strong>Terms & Conditions</strong><br>{{ $shop['terms'] }}</div>@endif
<div class="footer">This is a system-generated {{ $kind }}.</div></body></html>
