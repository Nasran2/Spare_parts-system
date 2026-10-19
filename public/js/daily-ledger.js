(() => {
    const dialog = document.getElementById('daily-ledger-export-dialog');
    const form = document.getElementById('daily-ledger-export-form');
    const choices = [...form.querySelectorAll('[name="sections[]"]')];
    const error = document.getElementById('dl-export-error');
    let trigger = null;
    document.querySelectorAll('[data-ledger-export]').forEach(button => button.addEventListener('click', () => {
        trigger = button;
        form.reset();
        error.hidden = true;
        const excel = button.dataset.ledgerExport === 'excel';
        form.action = excel ? form.dataset.excelUrl : form.dataset.pdfUrl;
        document.getElementById('dl-export-title').textContent = `Export Daily Ledger as ${excel ? 'Excel' : 'PDF'}`;
        document.getElementById('dl-export-submit').textContent = `Download ${excel ? 'Excel' : 'PDF'}`;
        dialog.showModal();
    }));
    document.querySelectorAll('[data-ledger-close]').forEach(button => button.addEventListener('click', () => dialog.close()));
    dialog.addEventListener('close', () => trigger?.focus());
    form.querySelector('[data-ledger-select-all]').addEventListener('click', () => { choices.forEach(input => input.checked = true); error.hidden = true; });
    form.querySelector('[data-ledger-clear-all]').addEventListener('click', () => choices.forEach(input => input.checked = false));
    choices.forEach(input => input.addEventListener('change', () => error.hidden = true));
    form.addEventListener('submit', event => {
        if (!choices.some(input => input.checked)) { event.preventDefault(); error.hidden = false; choices[0].focus(); return; }
        // Leave the dialog usable; attachment downloads do not replace the page.
        dialog.close();
    });
})();
