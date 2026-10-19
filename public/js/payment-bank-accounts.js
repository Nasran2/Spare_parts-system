(() => {
    const config = window.paymentBankConfig;
    const methods = ['bank_deposit', 'bank_transfer', 'card', 'mobile_payment', 'own_cheque'];
    const mounted = new WeakMap();

    function note(parent) {
        if (parent.querySelector('.payment-bank-note')) return;
        const text = document.createElement('p');
        text.className = 'payment-bank-note mt-2 text-xs text-gray-600';
        text.append('Need another account? Open Accounting → Banks and choose Add Bank Account. ');
        const link = document.createElement('a');
        link.href = config.addUrl;
        link.target = '_blank';
        link.rel = 'noopener';
        link.className = 'font-semibold text-blue-600 underline';
        link.textContent = 'Add a bank account';
        text.append(link, '. Then refresh this page to load the new account.');
        parent.append(text);
    }

    function mount(method) {
        if (mounted.has(method)) return;
        const form = method.closest('form');
        if (form && (form.getAttribute('method') || 'get').toLowerCase() === 'get') return;
        const name = method.name
            ? method.name.replace(/(?:payment_method|method)(\]?)$/, 'bank_account_id$1')
            : '';
        // Accounting forms already provide a bank selector of their own.
        const existing = name === 'bank_account_id' && form?.querySelector('select[name="bank_account_id"]');
        if (existing) {
            note(existing.parentElement);
            mounted.set(method, existing);
            return;
        }

        const container = document.createElement('div');
        container.className = 'payment-bank-field col-span-full mt-2 hidden';
        const label = document.createElement('label');
        label.className = 'block text-sm font-semibold text-gray-700 mb-1';
        const labelText = document.createTextNode('Business bank account');
        label.append(labelText);
        const select = document.createElement('select');
        select.name = name;
        select.className = 'payment-bank-account w-full px-3 py-2 border rounded-lg bg-white text-sm';
        select.add(new Option('Select bank account', ''));
        config.accounts.forEach(bank => select.add(new Option(
            [bank.bank_name, bank.account_name, bank.account_number].filter(Boolean).join(' - '), bank.id
        )));
        const path = name.replace(/\]/g, '').split('[');
        let oldValue = config.oldValues;
        path.forEach(key => { oldValue = oldValue?.[key]; });
        select.value = oldValue || method.dataset.bankAccountId || '';
        label.append(select);
        container.append(label);
        note(container);
        method.insertAdjacentElement('afterend', container);
        mounted.set(method, select);

        function update() {
            const show = methods.includes(method.value) || method.value === 'cheque';
            labelText.textContent = method.value === 'cheque' ? 'Deposit account (optional until clearing)' : (method.value === 'own_cheque' ? 'Drawn from bank account' : 'Business bank account');
            container.classList.toggle('hidden', !show);
            select.disabled = !show;
            select.required = show && methods.includes(method.value) && config.accounts.length > 0;
        }
        method.addEventListener('change', update);
        update();
    }

    function scan(root = document) {
        root.querySelectorAll('select[name$="payment_method"], select[name$="[method]"], select[name$="[payment_method]"], #payment-method, select.payment-method')
            .forEach(mount);
    }

    window.PaymentBankAccounts = {
        value(method) { mount(method); return mounted.get(method)?.value || null; },
        scan,
    };
    scan();
    new MutationObserver(records => {
        if (records.some(record => [...record.addedNodes].some(node => node.nodeType === 1 && (node.matches('select') || node.querySelector('select'))))) scan();
    }).observe(document.body, { childList: true, subtree: true });
})();
