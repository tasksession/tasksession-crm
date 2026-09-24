/**
 * Add bulk task page only: staff multi-select closes on outside click, Bootstrap instance refresh.
 * Loads after task.js; does not change global task behaviour on other pages.
 */
(function () {
    'use strict';

    document.addEventListener('DOMContentLoaded', function () {
        if (!document.querySelector('form[data-task-page="bulk"]')) {
            return;
        }

        function fixStaffDropdown() {
            var btn = document.getElementById('staffDropdownBtn');
            if (!btn || typeof bootstrap === 'undefined' || !bootstrap.Dropdown) {
                return;
            }
            btn.setAttribute('data-bs-auto-close', 'outside');
            var inst = bootstrap.Dropdown.getInstance(btn);
            if (inst) {
                inst.dispose();
            }
            bootstrap.Dropdown.getOrCreateInstance(btn);
        }

        // Run after task.js initializeTaskManagement (100ms) so dropdown exists once.
        setTimeout(fixStaffDropdown, 200);

        var params = new URLSearchParams(window.location.search);
        if (params.get('bulk_success') === '1') {
            var follow = document.getElementById('bulk-task-success-followup');
            if (follow) {
                follow.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
            }
        }
    });
})();
