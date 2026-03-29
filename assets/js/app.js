// ===== Confirmation avant suppression / action critique =====
document.querySelectorAll('[data-confirm]').forEach(el => {
    el.addEventListener('click', e => {
        if (!confirm(el.dataset.confirm)) e.preventDefault();
    });
});

// ===== Auto-dismiss alertes =====
setTimeout(() => {
    document.querySelectorAll('.alert-dismissible.auto-dismiss').forEach(a => {
        bootstrap.Alert.getOrCreateInstance(a).close();
    });
}, 4000);

// ===== Filtre tableau côté client =====
const searchInput = document.getElementById('tableSearch');
if (searchInput) {
    searchInput.addEventListener('input', function () {
        const q = this.value.toLowerCase();
        document.querySelectorAll('[data-searchable] tbody tr').forEach(row => {
            row.style.display = row.textContent.toLowerCase().includes(q) ? '' : 'none';
        });
    });
}
