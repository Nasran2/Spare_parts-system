<!-- Payment Reminder Modal -->
<div id="paymentReminderModal" class="hidden fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full z-50">
    <div class="relative top-20 mx-auto p-6 border w-full max-w-2xl shadow-lg rounded-lg bg-white">
        <div class="flex items-center justify-between mb-4 pb-3 border-b">
            <h3 class="text-xl font-bold text-gray-900 flex items-center">
                <i class="fas fa-paper-plane text-blue-600 mr-2"></i>Send Payment Reminder
            </h3>
            <button onclick="closePaymentReminderModal()" class="text-gray-400 hover:text-gray-600 text-xl">
                <i class="fas fa-times"></i>
            </button>
        </div>
        
        <form id="paymentReminderForm" onsubmit="sendPaymentReminder(event)">
            @csrf
            <input type="hidden" id="reminder_customer_id" name="customer_id">
            
            <div class="space-y-4">
                <!-- Channel Selection -->
                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-2">
                        <i class="fas fa-comments mr-1"></i>Send Via
                    </label>
                    <div class="flex gap-4">
                        <label class="flex items-center">
                            <input type="radio" name="channel" value="sms" checked class="mr-2">
                            <i class="fas fa-sms text-green-600 mr-1"></i> SMS
                        </label>
                        @if(env('ENABLE_WHATSAPP', false))
                        <label class="flex items-center">
                            <input type="radio" name="channel" value="whatsapp" class="mr-2">
                            <i class="fab fa-whatsapp text-green-600 mr-1"></i> WhatsApp
                        </label>
                        @endif
                    </div>
                </div>

                <!-- Message Type -->
                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-2">
                        <i class="fas fa-envelope mr-1"></i>Message Type
                    </label>
                    <select name="message_type" id="message_type" onchange="updateReminderOptions()" class="w-full border border-gray-300 rounded-lg p-2.5">
                        <option value="due_reminder">Due Payment Reminder</option>
                        <option value="bill_link">Specific Bill Link</option>
                        <option value="history_link">Billing History Link</option>
                    </select>
                </div>

                <!-- Bill Selection (shown when bill_link selected) -->
                <div id="bill_selection" class="hidden">
                    <label class="block text-sm font-semibold text-gray-700 mb-2">
                        <i class="fas fa-file-invoice mr-1"></i>Select Bill
                    </label>
                    <select name="sale_id" id="sale_id" class="w-full border border-gray-300 rounded-lg p-2.5">
                        <option value="">Loading bills...</option>
                    </select>
                </div>

                <!-- Preview -->
                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-2">
                        <i class="fas fa-eye mr-1"></i>Message Preview
                    </label>
                    <div id="message_preview" class="bg-gray-50 border border-gray-300 rounded-lg p-4 text-sm whitespace-pre-wrap min-h-[120px]">
                        Loading preview...
                    </div>
                </div>
            </div>

            <div class="flex justify-end gap-3 mt-6 pt-4 border-t">
                <button type="button" onclick="closePaymentReminderModal()" class="px-5 py-2.5 border border-gray-300 rounded-lg text-gray-700 hover:bg-gray-50 transition">
                    <i class="fas fa-times mr-1"></i> Cancel
                </button>
                <button type="submit" class="px-5 py-2.5 bg-blue-600 hover:bg-blue-700 text-white rounded-lg transition">
                    <i class="fas fa-paper-plane mr-1"></i> Send Reminder
                </button>
            </div>
        </form>
    </div>
</div>

<script>
let currentCustomer = null;
let customerSales = [];

function openPaymentReminderModal(customerId) {
    currentCustomer = customerId;
    document.getElementById('reminder_customer_id').value = customerId;
    
    // Fetch customer sales
    fetch(`/customers/${customerId}`, { headers: { 'Accept': 'application/json' } })
        .then(r => r.json())
        .then(data => {
            if (data.success) {
                currentCustomer = data.customer;
                customerSales = data.transactions.filter(t => t.type === 'Sell');
                updateReminderOptions();
                document.getElementById('paymentReminderModal').classList.remove('hidden');
            }
        });
}

function closePaymentReminderModal() {
    document.getElementById('paymentReminderModal').classList.add('hidden');
}

function updateReminderOptions() {
    const messageType = document.getElementById('message_type').value;
    const billSelection = document.getElementById('bill_selection');
    const saleSelect = document.getElementById('sale_id');
    
    if (messageType === 'bill_link') {
        billSelection.classList.remove('hidden');
        saleSelect.innerHTML = customerSales.map(s => 
            `<option value="${s.sale_id}">Invoice #${s.invoice} - {{ $currency }} ${s.debit.toFixed(2)} (Due: {{ $currency }} ${(s.due || 0).toFixed(2)})</option>`
        ).join('');
    } else {
        billSelection.classList.add('hidden');
    }
    
    updateMessagePreview();
}

function updateMessagePreview() {
    if (!currentCustomer) return;
    
    const messageType = document.getElementById('message_type').value;
    const preview = document.getElementById('message_preview');
    const currency = '{{ $currency }} ';
    const appName = '{{ config("app.name", "Vehicle POS") }}';
    
    let message = '';
    
    if (messageType === 'due_reminder') {
        const dueAmount = customerSales.reduce((sum, s) => sum + (s.due || 0), 0);
        message = `Dear ${currentCustomer.name},\n\n`;
        message += `This is a friendly reminder about your outstanding balance.\n`;
        message += `Total Due Amount: ${currency}${dueAmount.toFixed(2)}\n\n`;
        message += `Please make payment at your earliest convenience.\n`;
        message += `Thank you for your business!\n\n`;
        message += `- ${appName}`;
    } else if (messageType === 'bill_link') {
        const saleId = document.getElementById('sale_id').value;
        const sale = customerSales.find(s => s.sale_id == saleId);
        if (sale) {
            const billUrl = `${window.location.origin}/customer/${currentCustomer.id}/bill/${sale.sale_id}`;
            message = `Dear ${currentCustomer.name},\n\n`;
            message += `Your invoice #${sale.invoice} is ready.\n`;
            message += `Amount: ${currency}${sale.debit.toFixed(2)}\n`;
            if (sale.due > 0) {
                message += `Due: ${currency}${sale.due.toFixed(2)}\n`;
            }
            message += `\nView/Download Bill: ${billUrl}\n\n`;
            message += `- ${appName}`;
        }
    } else if (messageType === 'history_link') {
        const historyUrl = `${window.location.origin}/customer/${currentCustomer.id}/history`;
        message = `Dear ${currentCustomer.name},\n\n`;
        message += `View your complete billing history and account statement:\n`;
        message += `${historyUrl}\n\n`;
        message += `- ${appName}`;
    }
    
    preview.textContent = message;
}

function sendPaymentReminder(event) {
    event.preventDefault();
    const form = event.target;
    const formData = new FormData(form);
    const customerId = document.getElementById('reminder_customer_id').value;
    const channel = formData.get('channel');
    
    // Disable submit button
    const submitBtn = form.querySelector('button[type="submit"]');
    const originalText = submitBtn.innerHTML;
    submitBtn.disabled = true;
    submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin mr-1"></i> Sending...';
    
    fetch(`/customers/${customerId}/send-reminder`, {
        method: 'POST',
        headers: {
            'X-CSRF-TOKEN': '{{ csrf_token() }}',
            'Accept': 'application/json',
        },
        body: formData
    })
    .then(response => {
        if (!response.ok) {
            return response.json().then(err => Promise.reject(err));
        }
        return response.json();
    })
    .then(data => {
        if (data.whatsapp_link) {
            // Open WhatsApp in new tab
            window.open(data.whatsapp_link, '_blank');
            closePaymentReminderModal();
            alert('WhatsApp opened in new tab. Please send the message from there.');
        } else if (data.success) {
            closePaymentReminderModal();
            alert(data.message || 'Payment reminder sent successfully!');
            // Reload page to show updated data
            setTimeout(() => window.location.reload(), 500);
        } else {
            alert(data.message || 'Failed to send reminder');
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert(error.message || 'Failed to send payment reminder');
    })
    .finally(() => {
        // Re-enable submit button
        submitBtn.disabled = false;
        submitBtn.innerHTML = originalText;
    });
}
</script>
