(() => {
    document.addEventListener('click', event => {
        const link = event.target.closest('a[data-business-export]');
        if (!link || event.button !== 0) return;
        // These quick searches filter lists without submitting a form.
        const search = document.querySelector('#purchaseSearchInput');
        if (search) {
            const url = new URL(link.href);
            if (search.value.trim()) url.searchParams.set('search', search.value.trim());
            else url.searchParams.delete('search');
            link.href = url.toString();
        }
    });
})();
