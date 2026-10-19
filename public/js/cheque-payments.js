(() => {
    const modal = document.getElementById('cheque-confirm-modal');
    const confirmForm = document.getElementById('cheque-confirm-form');
    const bank = document.getElementById('modal-bank-account-id');
    const supplier = document.getElementById('modal-supplier-id');
    const options = document.getElementById('cheque-pass-options');
    const methods = document.getElementById('cheque-clear-method');
    const submit = document.getElementById('cheque-confirm-submit');
    let pendingForm = null;
    let trigger = null;
    const updateMethod = () => {
        const pass = pendingForm?.dataset.actionLabel === 'pass';
        const endorse = pass && pendingForm?.dataset.canEndorse === '1' && pendingForm?.dataset.chequeType !== 'own' && confirmForm.elements.clear_method.value === 'supplier';
        bank.disabled = !pass || endorse;
        bank.required = pass && !endorse;
        supplier.disabled = !endorse;
        supplier.required = endorse;
        document.getElementById('bank-deposit-container').hidden = !pass || endorse;
        document.getElementById('endorse-container').hidden = !endorse;
    };
    methods.addEventListener('change', updateMethod);
    document.querySelectorAll('[data-cheque-confirm-cancel]').forEach(button => button.addEventListener('click', () => modal.close()));
    modal.addEventListener('close', () => {
        pendingForm = null;
        trigger?.focus();
    });
    modal.addEventListener('click', event => { if (event.target === modal) { const rect = modal.getBoundingClientRect(); if (event.clientX < rect.left || event.clientX > rect.right || event.clientY < rect.top || event.clientY > rect.bottom) modal.close(); } });
    document.querySelectorAll('.js-cheque-action-form').forEach(form => form.addEventListener('submit', event => {
        event.preventDefault();
        pendingForm = form;
        trigger = event.submitter;
        confirmForm.reset();
        submit.disabled = false;
        const pass = form.dataset.actionLabel === 'pass';
        const own = form.dataset.chequeType === 'own';
        document.getElementById('cheque-confirm-title').textContent = pass ? 'Pass cheque' : 'Return cheque';
        document.getElementById('cheque-confirm-summary').textContent = `Cheque ${form.dataset.chequeNumber} · ${form.dataset.chequeAmount}`;
        document.getElementById('cheque-bank-label').textContent = own ? 'Which bank account paid this cheque?' : 'Which bank received this cheque?';
        options.hidden = !pass;
        methods.hidden = own || form.dataset.canEndorse !== '1';
        const today = new Date();
        today.setHours(0, 0, 0, 0);
        const future = form.dataset.chequeDate && new Date(`${form.dataset.chequeDate}T00:00:00`) > today;
        const message = document.getElementById('cheque-confirm-message');
        message.classList.toggle('cheque-date-warning', !!future);
        message.textContent = future ? `This cheque is dated ${form.dataset.chequeDate}. You are about to ${pass ? 'pass' : 'return'} it before that date.` : (pass ? (own ? 'The amount will be deducted from the bank account you select.' : 'Select the account that received this payment. Its balance will increase when you confirm.') : 'This cheque will be marked as returned. No bank deposit will be recorded.');
        submit.textContent = pass ? 'Pass cheque' : 'Return cheque';
        submit.style.backgroundColor = pass ? '#16a34a' : '#dc2626';
        updateMethod();
        modal.showModal();
    }));
    confirmForm.addEventListener('submit', event => {
        event.preventDefault();
        if (!pendingForm || !confirmForm.reportValidity()) return;
        pendingForm.querySelectorAll('[name="passed_bank_id"], [name="supplier_id"]').forEach(input => input.remove());
        if (pendingForm.dataset.actionLabel === 'pass') {
            const input = document.createElement('input');
            input.type = 'hidden';
            input.name = supplier.disabled ? 'passed_bank_id' : 'supplier_id';
            input.value = supplier.disabled ? bank.value : supplier.value;
            pendingForm.appendChild(input);
        }
        submit.disabled = true;
        submit.textContent = 'Saving…';
        HTMLFormElement.prototype.submit.call(pendingForm);
    });
})();
