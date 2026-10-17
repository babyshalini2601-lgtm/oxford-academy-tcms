// ============================================================
// Oxford Academy TCMS — Base JavaScript
// ============================================================

// Confirm before any delete or deactivate action
// Usage: add class "confirm-action" and data-message="..." to a link or button
document.addEventListener('DOMContentLoaded', function () {
    var confirmLinks = document.querySelectorAll('.confirm-action');
    confirmLinks.forEach(function (link) {
        link.addEventListener('click', function (e) {
            var message = link.getAttribute('data-message') || 'Are you sure?';
            if (!confirm(message)) {
                e.preventDefault();
            }
        });
    });
});

// Trigger browser print dialog
// Usage: add id="print-btn" to a button
document.addEventListener('DOMContentLoaded', function () {
    var printBtn = document.getElementById('print-btn');
    if (printBtn) {
        printBtn.addEventListener('click', function () {
            window.print();
        });
    }
});
