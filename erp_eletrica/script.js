/**
 * ERP Elétrica - Core Client Logic
 */
document.addEventListener('DOMContentLoaded', function () {
    // Sidebar toggle is handled by corporate.js — do not duplicate here

    // Init Tooltips
    const tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
    tooltipTriggerList.map(function (tooltipTriggerEl) {
        return new bootstrap.Tooltip(tooltipTriggerEl);
    });
});