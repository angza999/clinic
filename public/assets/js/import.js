document.addEventListener('DOMContentLoaded', () => {
    document.querySelectorAll('.type-tile input[type="radio"]').forEach((input) => {
        input.addEventListener('change', () => {
            document.querySelectorAll('.type-tile').forEach((tile) => tile.classList.remove('active'));
            input.closest('.type-tile')?.classList.add('active');
        });
    });

    document.querySelectorAll('.confirm-import-form').forEach((form) => {
        form.addEventListener('submit', (event) => {
            const confirmed = window.confirm('ยืนยันนำเข้าข้อมูลชุดนี้เข้าฐานข้อมูล?');
            if (!confirmed) {
                event.preventDefault();
            }
        });
    });
});
