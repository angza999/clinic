(function () {
    const table = document.getElementById('patientReportTable');
    if (!table) {
        return;
    }

    const tbody = table.querySelector('tbody');
    const searchInput = document.querySelector('[data-report-search]');
    const sortSelect = document.querySelector('[data-report-sort]');

    const visibleRows = () => Array.from(tbody.querySelectorAll('tr')).filter((row) => row.dataset.search);

    const filterRows = () => {
        const query = (searchInput?.value || '').trim().toLowerCase();
        visibleRows().forEach((row) => {
            row.hidden = query !== '' && !(row.dataset.search || '').includes(query);
        });
    };

    const sortRows = () => {
        const value = sortSelect?.value || 'time';
        const rows = visibleRows();

        rows.sort((a, b) => {
            if (value === 'amount-desc') {
                return Number(b.dataset.amount || 0) - Number(a.dataset.amount || 0);
            }

            const key = value === 'name' ? 'name' : value === 'status' ? 'status' : 'time';
            return String(a.dataset[key] || '').localeCompare(String(b.dataset[key] || ''), 'th');
        });

        rows.forEach((row) => tbody.appendChild(row));
        filterRows();
    };

    searchInput?.addEventListener('input', filterRows);
    sortSelect?.addEventListener('change', sortRows);
})();
