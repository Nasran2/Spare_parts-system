// Escape text and quoted attributes before inserting data into HTML templates.
window.securityEscapeHtml = function (value) {
    return String(value ?? '').replace(/[&<>"']/g, char => ({
        '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;'
    })[char]);
};
