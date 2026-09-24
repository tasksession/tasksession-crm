/**
 * Sync estimated_time_seconds hidden input from preset/custom fields (add/edit task forms).
 * Estimated time uses the same Bootstrap dropdown pattern as assign staff (.field-btn).
 */
(function () {
    function parsePresetSeconds(root) {
        var container = root.querySelector('.tasksession-estimated-dropdown');
        var hidden = root.querySelector('.tasksession-estimated-seconds');
        if (!container || !hidden) {
            return;
        }
        var wrap = root.querySelector('.tasksession-estimated-custom-wrap');
        var ch = root.querySelector('.tasksession-custom-est-hours');
        var cm = root.querySelector('.tasksession-custom-est-minutes');
        var btnLabel = container.querySelector('.tasksession-estimated-dropdown-label');
        /** @type {string} '', seconds as string e.g. '900', or 'custom' */
        var currentMode = '';

        function setCustomOpen(open) {
            if (!wrap) {
                return;
            }
            if (open) {
                wrap.style.display = 'block';
                wrap.classList.add('tasksession-estimated-custom-wrap--open');
            } else {
                wrap.style.display = 'none';
                wrap.classList.remove('tasksession-estimated-custom-wrap--open');
            }
        }

        function sync() {
            if (currentMode === 'custom') {
                setCustomOpen(true);
                var h = parseInt(ch && ch.value ? ch.value : '0', 10) || 0;
                var m = parseInt(cm && cm.value ? cm.value : '0', 10) || 0;
                if (m > 59) {
                    m = 59;
                }
                var sec = Math.max(0, h * 3600 + m * 60);
                hidden.value = sec > 0 ? String(sec) : '';
            } else if (currentMode === '') {
                setCustomOpen(false);
                hidden.value = '';
            } else {
                setCustomOpen(false);
                hidden.value = currentMode;
            }
        }

        function initModeFromDom() {
            var hv = String(hidden.value || '').trim();
            var customOpen = wrap && wrap.classList.contains('tasksession-estimated-custom-wrap--open');
            if (!hv) {
                currentMode = '';
            } else if (customOpen) {
                currentMode = 'custom';
            } else {
                currentMode = hv;
            }
        }

        container.querySelectorAll('.tasksession-est-est-item').forEach(function (item) {
            item.addEventListener('click', function () {
                var v = item.getAttribute('data-est-value');
                if (v === null) {
                    v = '';
                }
                var text = item.textContent ? item.textContent.trim() : '';
                if (v === 'custom') {
                    currentMode = 'custom';
                } else if (v === '') {
                    currentMode = '';
                } else {
                    currentMode = String(v);
                }
                if (btnLabel && text) {
                    btnLabel.textContent = text;
                }
                sync();
            });
        });

        if (ch) {
            ch.addEventListener('input', sync);
        }
        if (cm) {
            cm.addEventListener('input', sync);
        }
        var form = container.closest('form');
        if (form) {
            form.addEventListener('submit', sync);
        }
        initModeFromDom();
        sync();
    }

    function initEstimatedTimeFields() {
        var roots = [];
        var seen = typeof WeakSet !== 'undefined' ? new WeakSet() : null;

        function bindRoot(el) {
            if (!el || !el.querySelector('.tasksession-estimated-dropdown')) {
                return;
            }
            if (seen) {
                if (seen.has(el)) {
                    return;
                }
                seen.add(el);
            } else if (roots.indexOf(el) !== -1) {
                return;
            }
            roots.push(el);
        }

        document.querySelectorAll('.tasksession-estimated-dropdown').forEach(function (dd) {
            bindRoot(
                dd.closest('.comon-task-estimated-row')
                    || dd.closest('.col-12')
                    || dd.closest('form')
            );
        });
        document.querySelectorAll('.comon-task-estimated-row').forEach(bindRoot);

        roots.forEach(parsePresetSeconds);
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initEstimatedTimeFields);
    } else {
        initEstimatedTimeFields();
    }
})();
