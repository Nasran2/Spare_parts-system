@php
    $fromName = $fromName ?? 'date_from';
    $toName = $toName ?? 'date_to';
    $label = $label ?? 'Quick Filter';
    $labelClass = $labelClass ?? 'text-xs font-semibold text-gray-600';
    $selectClass = $selectClass ?? 'mt-1 w-full px-3 py-2 border rounded-lg';
    $inline = (bool) ($inline ?? false);
@endphp

@if($inline)
    <select
        class="{{ $selectClass }} js-quick-date-range"
        data-from="{{ $fromName }}"
        data-to="{{ $toName }}"
    >
        <option value="">Quick Filter</option>
        <option value="today">Today</option>
        <option value="yesterday">Yesterday</option>
        <option value="this_week">This Week</option>
        <option value="this_month">This Month</option>
    </select>
@else
    <div>
        <label class="{{ $labelClass }}">{{ $label }}</label>
        <select
            class="{{ $selectClass }} js-quick-date-range"
            data-from="{{ $fromName }}"
            data-to="{{ $toName }}"
        >
            <option value="">Quick Filter</option>
            <option value="today">Today</option>
            <option value="yesterday">Yesterday</option>
            <option value="this_week">This Week</option>
            <option value="this_month">This Month</option>
        </select>
    </div>
@endif

@once
    @push('scripts')
        <script>
            (function () {
                function pad(n) {
                    return n < 10 ? '0' + n : '' + n;
                }

                function fmt(d) {
                    return d.getFullYear() + '-' + pad(d.getMonth() + 1) + '-' + pad(d.getDate());
                }

                function startOfWeekMonday(date) {
                    const d = new Date(date.getFullYear(), date.getMonth(), date.getDate());
                    const day = d.getDay(); // 0=Sun, 1=Mon, ...
                    const diff = (day + 6) % 7; // days since Monday
                    d.setDate(d.getDate() - diff);
                    return d;
                }

                function setRange(form, fromName, toName, start, end) {
                    if (!form) return;

                    const fromInput = form.querySelector('[name="' + fromName + '"]');
                    const toInput = form.querySelector('[name="' + toName + '"]');
                    if (!fromInput || !toInput) return;

                    fromInput.value = start;
                    toInput.value = end;

                    if (typeof form.requestSubmit === 'function') {
                        form.requestSubmit();
                    } else {
                        form.submit();
                    }
                }

                document.addEventListener('change', function (e) {
                    const select = e.target;
                    if (!select || !select.classList || !select.classList.contains('js-quick-date-range')) return;

                    const value = (select.value || '').trim();
                    if (!value) return;

                    const form = select.closest('form');
                    const fromName = select.dataset.from;
                    const toName = select.dataset.to;
                    if (!fromName || !toName) return;

                    const today = new Date();
                    let start;
                    let end;

                    if (value === 'today') {
                        start = fmt(today);
                        end = fmt(today);
                    } else if (value === 'yesterday') {
                        const y = new Date(today);
                        y.setDate(y.getDate() - 1);
                        start = fmt(y);
                        end = fmt(y);
                    } else if (value === 'this_week') {
                        const s = startOfWeekMonday(today);
                        start = fmt(s);
                        end = fmt(today);
                    } else if (value === 'this_month') {
                        const s = new Date(today.getFullYear(), today.getMonth(), 1);
                        start = fmt(s);
                        end = fmt(today);
                    } else {
                        return;
                    }

                    setRange(form, fromName, toName, start, end);
                });
            })();
        </script>
    @endpush
@endonce
