<div class="a4-invoice">
    <!-- Header -->
    <div class="a4-header">
        <div class="a4-header-left">
            @if(($invoiceShowLogo ?? true) && !empty($logoSrc))
                <img src="{{ $logoSrc }}" alt="Logo" class="a4-logo" />
            @endif
        </div>
        <div class="a4-header-right">
            <h1 class="a4-shop-title">{{ $shop['name'] }}</h1>
            <div class="a4-shop-address">
                {{ $shop['address'] }}<br>
                @if($shop['phone']) Tel: {{ $shop['phone'] }}<br>@endif
                @if($shop['email']) Email: {{ $shop['email'] }}<br>@endif
            </div>
        </div>
    </div>

    <div class="a4-title-row">
        <h2>INVOICE</h2>
        @if($isExchangeBill)
            <span class="a4-badge a4-badge-exchange">EXCHANGE BILL</span>
        @elseif(!empty($hasReturns))
            <span class="a4-badge a4-badge-returned">RETURNED</span>
        @endif
    </div>

    <!-- Meta Information -->
    <div class="a4-meta">
        <div class="a4-meta-box">
            <strong>Invoice To:</strong><br>
            @php
                $cust = !empty($controls['hide_supplier_names']) ? null : $sale->customer;
            @endphp
            @if($cust)
                <div class="a4-customer-name">{{ $cust->name }}</div>
                @if($cust->phone) <div>{{ $cust->phone }}</div> @endif
                @if($cust->email) <div>{{ $cust->email }}</div> @endif
                @if($cust->address) <div>{{ $cust->address }}</div> @endif
            @else
                <div class="a4-customer-name">Walk-in Customer</div>
            @endif
        </div>
        <div class="a4-meta-box a4-text-right">
            <table class="a4-meta-table">
                <tr>
                    <td><strong>Invoice No:</strong></td>
                    <td>{{ !empty($controls['hide_invoice_details']) ? 'HIDDEN' : $sale->sale_no }}</td>
                </tr>
                <tr>
                    <td><strong>Date:</strong></td>
                    <td>{{ ($sale->created_at ?? now())->timezone(config('app.timezone'))->format('Y-m-d H:i') }}</td>
                </tr>
                <tr>
                    <td><strong>Cashier:</strong></td>
                    <td>{{ $sale->user?->name }}</td>
                </tr>
                <tr>
                    <td><strong>Status:</strong></td>
                    <td>{{ $displayPaymentStatus }}</td>
                </tr>
            </table>
        </div>
    </div>

    <!-- Items Table -->
    <table class="a4-table">
        <thead>
            <tr>
                <th style="width: 50%;">Item Description</th>
                <th style="text-align:center; width: 10%;">Qty</th>
                <th style="text-align:right; width: 20%;">Unit Price</th>
                <th style="text-align:right; width: 20%;">Total</th>
            </tr>
        </thead>
        <tbody>
            @foreach(($netItems ?? $sale->items) as $it)
                @php
                    $displayQtyRaw = (float) ($it->net_quantity ?? $it->quantity);
                    $displayUnitMasked = $maskMoney((float) ($it->display_unit_price ?? $it->unit_price), !empty($controls['hide_actual_stock_price']) || !empty($controls['hide_invoice_details']));
                    $displayLineTotal = is_numeric(str_replace(',', '', (string) $displayUnitMasked))
                        ? ((float) str_replace(',', '', (string) $displayUnitMasked)) * $displayQtyRaw
                        : null;
                @endphp
                <tr>
                    <td>
                        <div class="a4-item-name">{{ !empty($controls['hide_product_wise_data']) ? 'Hidden Product' : ($it->product?->name ?? ('#'.$it->product_id)) }}</div>
                        @if(((float) ($it->line_discount_amount ?? 0)) > 0)
                            <div class="a4-item-discount">Discount: -{{ $maskMoney((float) ($it->line_discount_amount ?? 0), !empty($controls['hide_invoice_details'])) }}</div>
                        @endif
                    </td>
                    <td style="text-align:center;">{{ $maskQty($it->net_quantity ?? $it->quantity) }}</td>
                    <td style="text-align:right;">{{ $displayUnitMasked }}</td>
                    <td style="text-align:right; font-weight:600;">{{ $displayLineTotal === null ? '—' : number_format($displayLineTotal, $priceVisiblePct < 100 ? 0 : 2) }}</td>
                </tr>
            @endforeach

            @if(!empty($exchangeReturnItems) && $exchangeReturnItems->count() > 0)
                <tr>
                    <td colspan="4" class="a4-section-heading">Returned Items</td>
                </tr>
                @foreach($exchangeReturnItems as $rit)
                    @php
                        $retQtyRaw = -1 * (float) $rit->quantity;
                        $retUnitMasked = $maskMoney((float) $rit->unit_price, !empty($controls['hide_actual_stock_price']) || !empty($controls['hide_invoice_details']));
                        $retLineTotal = is_numeric(str_replace(',', '', (string) $retUnitMasked))
                            ? ((float) str_replace(',', '', (string) $retUnitMasked)) * $retQtyRaw
                            : null;
                    @endphp
                    <tr>
                        <td>
                            <div class="a4-item-name">{{ !empty($controls['hide_product_wise_data']) ? 'Hidden Product' : ($rit->product?->name ?? ('#'.$rit->product_id)) }}</div>
                        </td>
                        <td style="text-align:center;">{{ $maskQty(-1 * (int) $rit->quantity) }}</td>
                        <td style="text-align:right;">{{ $retUnitMasked }}</td>
                        <td style="text-align:right; font-weight:600;">{{ $retLineTotal === null ? '—' : number_format($retLineTotal, $priceVisiblePct < 100 ? 0 : 2) }}</td>
                    </tr>
                @endforeach
            @endif

            @if(!empty($returnItems) && $returnItems->count() > 0)
                <tr>
                    <td colspan="4" class="a4-section-heading">Returned From This Invoice</td>
                </tr>
                @foreach($returnItems as $rit)
                    @php
                        $retQtyRaw = -1 * (float) $rit->quantity;
                        $retUnitMasked = $maskMoney((float) $rit->unit_price, !empty($controls['hide_actual_stock_price']) || !empty($controls['hide_invoice_details']));
                        $retLineTotal = is_numeric(str_replace(',', '', (string) $retUnitMasked))
                            ? ((float) str_replace(',', '', (string) $retUnitMasked)) * $retQtyRaw
                            : null;
                    @endphp
                    <tr>
                        <td>
                            <div class="a4-item-name">{{ !empty($controls['hide_product_wise_data']) ? 'Hidden Product' : ($rit->product?->name ?? ('#'.$rit->product_id)) }}</div>
                        </td>
                        <td style="text-align:center;">{{ $maskQty(-1 * (int) $rit->quantity) }}</td>
                        <td style="text-align:right;">{{ $retUnitMasked }}</td>
                        <td style="text-align:right; font-weight:600;">{{ $retLineTotal === null ? '—' : number_format($retLineTotal, $priceVisiblePct < 100 ? 0 : 2) }}</td>
                    </tr>
                @endforeach
            @endif
        </tbody>
    </table>

    <div class="a4-summary-container">
        <div class="a4-summary-left">
            @if(!empty($invoiceTerms))
                <div class="a4-terms">
                    <strong>Terms &amp; Conditions</strong>
                    <div class="a4-terms-text">{{ $invoiceTerms }}</div>
                </div>
            @endif
        </div>
        <div class="a4-summary-right">
            <table class="a4-summary-table">
                <tr>
                    <td>Subtotal:</td>
                    <td>{{ $maskMoney((float) ($displaySubtotal ?? $sale->subtotal), !empty($controls['hide_invoice_details'])) }}</td>
                </tr>
                @if(((float) ($cartDiscountAmount ?? 0)) > 0)
                <tr>
                    <td>Discount:</td>
                    <td>{{ $maskMoney((float) $cartDiscountAmount, !empty($controls['hide_invoice_details'])) }}</td>
                </tr>
                @endif
                @if($vatEnabled && $showVatBreakdown)
                <tr>
                    <td>VAT{{ $vatRate ? ' ('.$vatRate.'%)' : '' }}:</td>
                    <td>{{ $maskMoney($sale->tax, !empty($controls['hide_invoice_details'])) }}</td>
                </tr>
                @endif
                <tr class="a4-grand-total">
                    <td>Total:</td>
                    <td>{{ $maskMoney($totalBeforeReturn, !empty($controls['hide_invoice_details'])) }}</td>
                </tr>

                @if($exchangeCredit > 0)
                    <tr>
                        <td>Return Credit:</td>
                        <td>{{ $maskMoney(-1 * $exchangeCredit, !empty($controls['hide_invoice_details'])) }}</td>
                    </tr>
                    @if($refundDue > 0)
                        <tr class="a4-grand-total">
                            <td>Refund Due:</td>
                            <td>{{ $maskMoney($refundDue, !empty($controls['hide_invoice_details'])) }}</td>
                        </tr>
                    @elseif($customerPay > 0)
                        <tr class="a4-grand-total">
                            <td>New Total (Payable):</td>
                            <td>{{ $maskMoney($customerPay, !empty($controls['hide_invoice_details'])) }}</td>
                        </tr>
                    @endif
                @endif

                @if($showPaidRow)
                    <tr>
                        <td>Tendered Amount:</td>
                        <td>{{ $maskMoney($tenderedAmount, !empty($controls['hide_invoice_details'])) }}</td>
                    </tr>
                    <tr class="a4-balance">
                        <td>Balance / Due:</td>
                        <td>{{ $maskMoney($balanceAmount, !empty($controls['hide_invoice_details'])) }}</td>
                    </tr>
                @endif
            </table>
        </div>
    </div>

    @if($chequePayments->isNotEmpty())
        <div class="a4-cheques">
            <div class="a4-cheques-title">Cheque Payments</div>
            <table class="a4-cheques-table">
                <thead>
                    <tr>
                        <th>Pass Date</th>
                        <th>Cheque No</th>
                        <th>Bank</th>
                        <th>Amount</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($chequePayments as $cheque)
                        <tr>
                            <td>{{ $cheque->cheque_date?->format('Y-m-d') ?? '-' }}</td>
                            <td>{{ $cheque->cheque_number }}</td>
                            <td>{{ $cheque->bank_name ?? '-' }}</td>
                            <td>{{ $maskMoney($cheque->amount, !empty($controls['hide_supplier_payments']) || !empty($controls['hide_invoice_details'])) }}</td>
                            <td>
                                {{ ucfirst($cheque->status) }}
                                @if($cheque->status === 'pending')
                                    <small>(Hold)</small>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif

    <div class="a4-footer">
        <div class="a4-footer-msg">{{ $invoiceFooterText ?? 'Thank you for your business!' }}</div>
        @php
            $dev = config('services.developer');
            $phoneDigits = preg_replace('/\D+/', '', $dev['phone'] ?? '');
        @endphp
        <div class="a4-footer-powered">
            Powered by 
            @if(!empty($dev['website']))
                <a href="https://{{ $dev['website'] }}">{{ $dev['website'] }}</a>
            @elseif(!empty($phoneDigits))
                <a href="https://wa.me/{{ $dev['phone'] ? preg_replace('/\D+/', '', $dev['phone']) : '' }}">{{ $dev['name'] ?? $phoneDigits }}</a>
            @else
                <span>{{ $dev['name'] ?? 'Developer' }}</span>
            @endif
        </div>
    </div>
</div>
