(function () {
    var labels = {
        off: 'Does not repeat',
        after_completion: 'After completion',
        day: 'Every day',
        workday: 'Every workday',
        week: 'Every week',
        month: 'Every month',
        year: 'Every year',
        custom: 'Customize repeat',
        sameDay: 'Same day',
        nextDay: 'Next day',
        notSet: 'Not set',
        inDays: 'in %d days',
        dayUnit: 'Day',
        workdayUnit: 'Workday',
        weekUnit: 'Week',
        monthUnit: 'Month',
        yearUnit: 'Year',
        timeHelp: 'Creates a new task on a specific date, regardless of previous task completion.',
        afterHelp: 'Creates a new task once the previous one is completed.',
        never: 'Never',
        endRepeats: 'End repeats',
        todo: 'To do',
        inprogress: 'In progress',
        review: 'Review',
        done: 'Done'
    };

    var unitLabels = {
        day: labels.dayUnit,
        workday: labels.workdayUnit,
        week: labels.weekUnit,
        month: labels.monthUnit,
        year: labels.yearUnit
    };

    var statusColors = {
        todo: 'color-todo-bg',
        inprogress: 'color-inprogress-bg',
        review: 'color-review-bg',
        done: 'color-done-bg'
    };

    var state = {
        enabled: 0,
        mode: '',
        interval_unit: 'day',
        interval_count: 1,
        skip_weekends: 0,
        start_from: '',
        due_offset_days: 0,
        default_status: 'todo',
        estimated_time_seconds: null,
        after_status: 'todo',
        ends_at: ''
    };

    var calRange = { endY: 0, endM: 0, appending: false };
    var CAL_MAX_YEARS = 10;

    function el(id) {
        return document.getElementById(id);
    }

    function setText(id, text) {
        var node = el(id);
        if (node) {
            node.textContent = text;
        }
    }

    function todayIso() {
        var d = new Date();
        var m = String(d.getMonth() + 1).padStart(2, '0');
        var day = String(d.getDate()).padStart(2, '0');
        return d.getFullYear() + '-' + m + '-' + day;
    }

    function parseIso(iso) {
        if (!iso || !/^\d{4}-\d{2}-\d{2}$/.test(iso)) {
            return null;
        }
        var p = iso.split('-');
        return new Date(parseInt(p[0], 10), parseInt(p[1], 10) - 1, parseInt(p[2], 10));
    }

    function toIso(d) {
        var m = String(d.getMonth() + 1).padStart(2, '0');
        var day = String(d.getDate()).padStart(2, '0');
        return d.getFullYear() + '-' + m + '-' + day;
    }

    function shiftWeekend(d) {
        var n = d.getDay();
        if (n === 6) {
            d.setDate(d.getDate() + 2);
        } else if (n === 0) {
            d.setDate(d.getDate() + 1);
        }
        return d;
    }

    function addInterval(fromIso, unit, count, skipWeekends) {
        var d = parseIso(fromIso);
        if (!d) {
            return null;
        }
        count = Math.max(1, parseInt(count, 10) || 1);
        if (unit === 'workday') {
            var added = 0;
            while (added < count) {
                d.setDate(d.getDate() + 1);
                var n = d.getDay();
                if (n !== 0 && n !== 6) {
                    added += 1;
                }
            }
        } else if (unit === 'week') {
            d.setDate(d.getDate() + count * 7);
        } else if (unit === 'month') {
            d.setMonth(d.getMonth() + count);
        } else if (unit === 'year') {
            d.setFullYear(d.getFullYear() + count);
        } else {
            d.setDate(d.getDate() + count);
            if (skipWeekends) {
                shiftWeekend(d);
            }
        }
        if (skipWeekends && unit !== 'workday' && unit !== 'day') {
            shiftWeekend(d);
        }
        return toIso(d);
    }

    function dueLabel(offset) {
        if (offset === null || offset === '') {
            return labels.notSet;
        }
        var n = parseInt(offset, 10);
        if (n === 0) {
            return labels.sameDay;
        }
        if (n === 1) {
            return labels.nextDay;
        }
        return labels.inDays.replace('%d', String(n));
    }

    function triggerLabel() {
        if (!state.enabled) {
            return labels.off;
        }
        if (state.mode === 'after_completion') {
            return labels.after_completion;
        }
        if (parseInt(state.interval_count, 10) === 1 && labels[state.interval_unit]) {
            return labels[state.interval_unit];
        }
        return labels.custom;
    }

    function readHidden() {
        state.enabled = el('repeat_enabled') && el('repeat_enabled').value === '1' ? 1 : 0;
        state.mode = el('repeat_mode') ? el('repeat_mode').value : '';
        state.interval_unit = el('repeat_interval_unit') ? (el('repeat_interval_unit').value || 'day') : 'day';
        state.interval_count = el('repeat_interval_count') ? Math.max(1, parseInt(el('repeat_interval_count').value, 10) || 1) : 1;
        state.skip_weekends = el('repeat_skip_weekends') && el('repeat_skip_weekends').value === '1' ? 1 : 0;
        state.start_from = el('repeat_start_from') ? el('repeat_start_from').value : '';
        var due = el('repeat_due_offset_days') ? el('repeat_due_offset_days').value : '0';
        state.due_offset_days = due === '' ? null : parseInt(due, 10);
        state.default_status = el('repeat_default_status') ? (el('repeat_default_status').value || 'todo') : 'todo';
        var est = el('repeat_estimated_time_seconds') ? el('repeat_estimated_time_seconds').value : '';
        state.estimated_time_seconds = est === '' ? null : parseInt(est, 10);
        state.after_status = el('repeat_after_status') ? (el('repeat_after_status').value || 'todo') : 'todo';
        state.ends_at = el('repeat_ends_at') ? el('repeat_ends_at').value : '';
    }

    function writeHidden() {
        if (el('repeat_enabled')) el('repeat_enabled').value = state.enabled ? '1' : '0';
        if (el('repeat_mode')) el('repeat_mode').value = state.enabled ? state.mode : '';
        if (el('repeat_interval_unit')) el('repeat_interval_unit').value = state.interval_unit || 'day';
        if (el('repeat_interval_count')) el('repeat_interval_count').value = String(state.interval_count || 1);
        if (el('repeat_skip_weekends')) el('repeat_skip_weekends').value = state.skip_weekends ? '1' : '0';
        if (el('repeat_start_from')) el('repeat_start_from').value = state.start_from || '';
        if (el('repeat_due_offset_days')) {
            el('repeat_due_offset_days').value = state.due_offset_days === null || state.due_offset_days === '' ? '' : String(state.due_offset_days);
        }
        if (el('repeat_default_status')) el('repeat_default_status').value = state.default_status || 'todo';
        if (el('repeat_estimated_time_seconds')) {
            el('repeat_estimated_time_seconds').value = state.estimated_time_seconds === null ? '' : String(state.estimated_time_seconds);
        }
        if (el('repeat_after_status')) el('repeat_after_status').value = state.default_status || 'todo';
        if (el('repeat_ends_at')) el('repeat_ends_at').value = state.ends_at || '';
        setText('setRepeatsDropdownLabel', triggerLabel());
    }

    function applyPreset(preset) {
        if (preset === 'off') {
            state.enabled = 0;
            state.mode = '';
            writeHidden();
            return;
        }
        state.enabled = 1;
        if (preset === 'after_completion') {
            state.mode = 'after_completion';
            writeHidden();
            return;
        }
        state.mode = 'time_based';
        state.interval_unit = preset;
        state.interval_count = 1;
        if (!state.start_from) {
            var startInput = document.getElementById('start_date');
            state.start_from = startInput && startInput.value ? startInput.value : todayIso();
        }
        if (state.due_offset_days === undefined || state.due_offset_days === null) {
            state.due_offset_days = 0;
        }
        writeHidden();
    }

    function modalMode(mode) {
        var timeRadio = el('taskRepeatModeTime');
        var afterRadio = el('taskRepeatModeAfter');
        if (timeRadio) timeRadio.checked = mode !== 'after_completion';
        if (afterRadio) afterRadio.checked = mode === 'after_completion';
        var paneTime = el('taskRepeatPaneTime');
        var previewCol = el('taskRepeatPreviewCol');
        var fieldsCol = el('taskRepeatFieldsCol');
        var help = el('taskRepeatModeHelp');
        var isAfter = mode === 'after_completion';
        if (paneTime) paneTime.classList.toggle('d-none', isAfter);
        if (previewCol) {
            previewCol.classList.toggle('d-none', isAfter);
            previewCol.classList.toggle('d-flex', !isAfter);
        }
        if (fieldsCol) {
            fieldsCol.classList.toggle('col-lg-7', !isAfter);
            fieldsCol.classList.toggle('col-12', isAfter);
        }
        if (help) help.textContent = isAfter ? labels.afterHelp : labels.timeHelp;
        syncPreviewHeight();
    }

    function setEstLabel(seconds) {
        var hidden = el('taskRepeatEstSeconds');
        var label = el('taskRepeatEstLabel');
        var sec = seconds ? parseInt(seconds, 10) : 0;
        if (isNaN(sec) || sec <= 0) {
            sec = 0;
        }
        if (hidden) {
            hidden.value = sec > 0 ? String(sec) : '';
        }
        var item = document.querySelector('#taskRepeatEstMenu .js-task-repeat-est[data-est-value="' + (sec > 0 ? String(sec) : '') + '"]');
        if (label) {
            label.textContent = item && item.textContent ? item.textContent.trim() : (sec > 0 ? String(Math.round(sec / 3600)) + 'h' : '0h');
        }
    }

    function setStatusDisplay(status) {
        var key = status && statusColors[status] ? status : 'todo';
        setText('taskRepeatStatusLabel', statusLabels[key] || statusLabels.todo);
        var swatch = el('taskRepeatStatusSwatch');
        if (swatch) {
            swatch.className = 'd-inline-block flex-shrink-0 rounded ' + statusColors[key];
        }
    }

    function fillModalFromState() {
        modalMode(state.mode === 'after_completion' ? 'after_completion' : 'time_based');
        if (el('taskRepeatCount')) el('taskRepeatCount').value = String(state.interval_count || 1);
        setText('taskRepeatUnitLabel', unitLabels[state.interval_unit] || unitLabels.day);
        if (el('taskRepeatSkipWeekends')) el('taskRepeatSkipWeekends').checked = !!state.skip_weekends;
        if (el('taskRepeatStartFrom')) {
            el('taskRepeatStartFrom').value = state.start_from || todayIso();
        }
        setText('taskRepeatDueLabel', dueLabel(state.due_offset_days));
        setStatusDisplay(state.default_status);
        setEstLabel(state.estimated_time_seconds);
        if (el('taskRepeatEndsAt')) el('taskRepeatEndsAt').value = state.ends_at || '';
        setText('taskRepeatEndsLabel', endsLabel(state.ends_at));
        renderPreview();
    }

    function readModalIntoDraft() {
        var mode = el('taskRepeatModeAfter') && el('taskRepeatModeAfter').checked ? 'after_completion' : 'time_based';
        var count = el('taskRepeatCount') ? Math.max(1, parseInt(el('taskRepeatCount').value, 10) || 1) : 1;
        var estRaw = el('taskRepeatEstSeconds') ? el('taskRepeatEstSeconds').value : '';
        var estSec = estRaw === '' ? null : parseInt(estRaw, 10);
        if (estSec !== null && (isNaN(estSec) || estSec <= 0)) {
            estSec = null;
        }
        return {
            enabled: 1,
            mode: mode,
            interval_unit: state.interval_unit || 'day',
            interval_count: count,
            skip_weekends: el('taskRepeatSkipWeekends') && el('taskRepeatSkipWeekends').checked ? 1 : 0,
            start_from: el('taskRepeatStartFrom') ? el('taskRepeatStartFrom').value : todayIso(),
            due_offset_days: state.due_offset_days,
            default_status: state.default_status || 'todo',
            estimated_time_seconds: estSec,
            after_status: state.default_status || 'todo',
            ends_at: el('taskRepeatEndsAt') ? el('taskRepeatEndsAt').value : ''
        };
    }

    function endsLabel(iso) {
        if (!iso) {
            return labels.never;
        }
        var d = parseIso(iso);
        if (!d) {
            return labels.never;
        }
        try {
            return d.toLocaleDateString();
        } catch (e) {
            return iso;
        }
    }

    function dayCircle(day, iso, selected, startIso) {
        var n = String(day);
        if (!selected[iso]) {
            return n;
        }
        var cls = iso === startIso ? 'task-repeat-cal-dot is-start d-inline-flex align-items-center justify-content-center' : 'task-repeat-cal-dot is-repeat d-inline-flex align-items-center justify-content-center';
        return '<span class="' + cls + '">' + n + '</span>';
    }

    function collectDates(fromIso, unit, count, skip, ends, maxDates) {
        var dates = {};
        var cur = fromIso;
        dates[cur] = true;
        var i;
        for (i = 0; i < maxDates; i += 1) {
            cur = addInterval(cur, unit, count, skip);
            if (!cur) {
                break;
            }
            if (ends && cur > ends) {
                break;
            }
            dates[cur] = true;
        }
        return dates;
    }

    function monthName(y, m) {
        try {
            return new Date(y, m, 1).toLocaleString(undefined, { month: 'long', year: 'numeric' });
        } catch (e) {
            return y + '-' + String(m + 1);
        }
    }

    function renderMonth(year, month, selected, startIso) {
        var first = new Date(year, month, 1);
        var startDow = first.getDay();
        var daysInMonth = new Date(year, month + 1, 0).getDate();
        var html = '<div class="task-repeat-cal-month font-weight-bold">' + monthName(year, month) + '</div>';
        html += '<div class="task-repeat-cal-grid">';
        var i;
        for (i = 0; i < startDow; i += 1) {
            html += '<div class="task-repeat-cal-day d-flex align-items-center justify-content-center"></div>';
        }
        var day;
        var col = startDow;
        for (day = 1; day <= daysInMonth; day += 1) {
            var iso = year + '-' + String(month + 1).padStart(2, '0') + '-' + String(day).padStart(2, '0');
            var weekend = (col === 0 || col === 6) ? ' is-weekend' : '';
            html += '<div class="task-repeat-cal-day d-flex align-items-center justify-content-center' + weekend + '" data-date="' + iso + '" role="button" tabindex="0">' + dayCircle(day, iso, selected, startIso) + '</div>';
            col += 1;
            if (col === 7) {
                col = 0;
            }
        }
        html += '</div>';
        return html;
    }

    function renderPreview() {
        var box = el('taskRepeatCalendarPreview');
        if (!box) {
            return;
        }
        if (el('taskRepeatModeAfter') && el('taskRepeatModeAfter').checked) {
            box.innerHTML = '';
            return;
        }
        var start = el('taskRepeatStartFrom') ? el('taskRepeatStartFrom').value : todayIso();
        if (!start) {
            start = todayIso();
        }
        var count = el('taskRepeatCount') ? Math.max(1, parseInt(el('taskRepeatCount').value, 10) || 1) : 1;
        var skip = el('taskRepeatSkipWeekends') && el('taskRepeatSkipWeekends').checked;
        var ends = el('taskRepeatEndsAt') ? el('taskRepeatEndsAt').value : '';
        var startDate = parseIso(start) || new Date();
        var today = parseIso(todayIso()) || new Date();
        var from = startDate < today ? startDate : today;
        var y = from.getFullYear();
        var m = from.getMonth();
        var endY = today.getFullYear() + 2;
        var endM = 11;
        var horizonIso = endY + '-12-31';
        var selected = collectDates(start, state.interval_unit || 'day', count, skip, ends || horizonIso, 800);
        var weekdays = ['S', 'M', 'T', 'W', 'T', 'F', 'S'];
        var html = '<div class="task-repeat-cal w-100"><div class="task-repeat-cal-weekdays">';
        weekdays.forEach(function (d) {
            html += '<span class="d-flex align-items-center justify-content-center">' + d + '</span>';
        });
        html += '</div>';
        var n = 0;
        while (n < 36 && (y < endY || (y === endY && m <= endM))) {
            html += renderMonth(y, m, selected, start);
            calRange.endY = y;
            calRange.endM = m;
            m += 1;
            if (m > 11) {
                m = 0;
                y += 1;
            }
            n += 1;
        }
        html += '</div>';
        box.innerHTML = html;
        syncPreviewHeight();
    }

    function previewStartIso() {
        var start = el('taskRepeatStartFrom') ? el('taskRepeatStartFrom').value : todayIso();
        return start || todayIso();
    }

    function extendCalendarMonths(months) {
        var box = el('taskRepeatCalendarPreview');
        var cal = box ? box.querySelector('.task-repeat-cal') : null;
        if (!cal || calRange.appending || months < 1) {
            return;
        }
        var today = parseIso(todayIso()) || new Date();
        var maxY = today.getFullYear() + CAL_MAX_YEARS;
        var y = calRange.endY;
        var m = calRange.endM + 1;
        if (m > 11) {
            m = 0;
            y += 1;
        }
        if (y > maxY) {
            return;
        }
        var start = previewStartIso();
        var count = el('taskRepeatCount') ? Math.max(1, parseInt(el('taskRepeatCount').value, 10) || 1) : 1;
        var skip = el('taskRepeatSkipWeekends') && el('taskRepeatSkipWeekends').checked;
        var ends = el('taskRepeatEndsAt') ? el('taskRepeatEndsAt').value : '';
        var added = 0;
        var endY = y;
        var endM = m;
        while (added < months && endY <= maxY) {
            added += 1;
            if (added === months || (endY === maxY && endM === 11)) {
                break;
            }
            endM += 1;
            if (endM > 11) {
                endM = 0;
                endY += 1;
            }
        }
        var lastDay = new Date(endY, endM + 1, 0).getDate();
        var horizonIso = endY + '-' + String(endM + 1).padStart(2, '0') + '-' + String(lastDay).padStart(2, '0');
        var selected = collectDates(start, state.interval_unit || 'day', count, skip, ends || horizonIso, 4000);
        calRange.appending = true;
        while (y < endY || (y === endY && m <= endM)) {
            cal.insertAdjacentHTML('beforeend', renderMonth(y, m, selected, start));
            calRange.endY = y;
            calRange.endM = m;
            m += 1;
            if (m > 11) {
                m = 0;
                y += 1;
            }
        }
        calRange.appending = false;
    }

    function maybeExtendCalendar() {
        var box = el('taskRepeatCalendarPreview');
        if (!box) {
            return;
        }
        if (box.scrollHeight - box.scrollTop - box.clientHeight > 140) {
            return;
        }
        extendCalendarMonths(12);
    }

    function syncPreviewHeight() {
        var preview = el('taskRepeatCalendarPreview');
        var fields = el('taskRepeatFieldsCol');
        var previewCol = el('taskRepeatPreviewCol');
        var modalEl = el('taskRepeatModal');
        if (!preview) {
            return;
        }
        if (!previewCol || previewCol.classList.contains('d-none') || !fields) {
            preview.style.height = '';
            preview.style.maxHeight = '';
            preview.style.overflow = '';
            return;
        }
        preview.style.height = '0px';
        preview.style.maxHeight = '0px';
        preview.style.overflow = 'hidden';
        if (!modalEl || !modalEl.classList.contains('show')) {
            return;
        }
        void fields.offsetHeight;
        var title = previewCol.querySelector('h6');
        var titleH = title ? title.offsetHeight + 16 : 0;
        var h = fields.offsetHeight - titleH;
        if (h < 1) {
            return;
        }
        preview.style.height = h + 'px';
        preview.style.maxHeight = h + 'px';
        preview.style.overflow = '';
    }

    function openModal() {
        fillModalFromState();
        var modalEl = el('taskRepeatModal');
        if (modalEl && window.bootstrap && window.bootstrap.Modal) {
            window.bootstrap.Modal.getOrCreateInstance(modalEl).show();
        }
    }

    function bind() {
        var menu = el('setRepeatsDropdownMenu');
        if (!menu) {
            return;
        }
        var cfg = window.taskRepeatI18n || {};
        Object.keys(cfg).forEach(function (k) {
            if (cfg[k]) {
                labels[k] = cfg[k];
            }
        });
        unitLabels = {
            day: labels.dayUnit,
            workday: labels.workdayUnit,
            week: labels.weekUnit,
            month: labels.monthUnit,
            year: labels.yearUnit
        };
        statusLabels = {
            todo: labels.todo,
            inprogress: labels.inprogress,
            review: labels.review,
            done: labels.done
        };

        if (window.taskRepeatInitial && typeof window.taskRepeatInitial === 'object') {
            Object.keys(state).forEach(function (k) {
                if (Object.prototype.hasOwnProperty.call(window.taskRepeatInitial, k) || k === 'enabled') {
                    if (k === 'enabled') {
                        state.enabled = window.taskRepeatInitial.enabled ? 1 : 0;
                    } else if (window.taskRepeatInitial[k] !== undefined) {
                        state[k] = window.taskRepeatInitial[k];
                    }
                }
            });
            if (window.taskRepeatInitial.mode) {
                state.mode = window.taskRepeatInitial.mode;
            }
        }
        readHidden();
        writeHidden();

        menu.querySelectorAll('.js-task-repeat-preset').forEach(function (btn) {
            btn.addEventListener('click', function (e) {
                e.preventDefault();
                applyPreset(btn.getAttribute('data-repeat') || 'off');
            });
        });
        var afterTip = menu.querySelector('[data-repeat="after_completion"] [data-bs-toggle="tooltip"]');
        var dropdownRoot = menu.closest('.dropdown');
        if (dropdownRoot) {
            dropdownRoot.addEventListener('hidden.bs.dropdown', function () {
                if (!afterTip || !window.bootstrap || !window.bootstrap.Tooltip) {
                    return;
                }
                var tip = window.bootstrap.Tooltip.getInstance(afterTip);
                if (tip) {
                    tip.hide();
                }
            });
        }
        var customBtn = menu.querySelector('.js-task-repeat-customize');
        if (customBtn) {
            customBtn.addEventListener('click', function (e) {
                e.preventDefault();
                if (!state.enabled) {
                    state.enabled = 1;
                    state.mode = 'time_based';
                    state.interval_unit = 'day';
                    state.interval_count = 1;
                    if (!state.start_from) {
                        var startInput = document.getElementById('start_date');
                        state.start_from = startInput && startInput.value ? startInput.value : todayIso();
                    }
                    if (state.due_offset_days === undefined) {
                        state.due_offset_days = 0;
                    }
                }
                openModal();
            });
        }

        document.querySelectorAll('input[name="taskRepeatModalMode"]').forEach(function (radio) {
            radio.addEventListener('change', function () {
                modalMode(radio.value);
                renderPreview();
            });
        });

        var minus = el('taskRepeatCountMinus');
        var plus = el('taskRepeatCountPlus');
        var countInput = el('taskRepeatCount');
        if (minus && countInput) {
            minus.addEventListener('click', function () {
                countInput.value = String(Math.max(1, (parseInt(countInput.value, 10) || 1) - 1));
                renderPreview();
            });
        }
        if (plus && countInput) {
            plus.addEventListener('click', function () {
                countInput.value = String(Math.min(365, (parseInt(countInput.value, 10) || 1) + 1));
                renderPreview();
            });
        }
        if (countInput) {
            countInput.addEventListener('input', renderPreview);
        }
        if (el('taskRepeatSkipWeekends')) {
            el('taskRepeatSkipWeekends').addEventListener('change', renderPreview);
        }
        if (el('taskRepeatStartFrom')) {
            el('taskRepeatStartFrom').addEventListener('change', renderPreview);
        }
        if (el('taskRepeatEndsAt')) {
            el('taskRepeatEndsAt').addEventListener('change', function () {
                setText('taskRepeatEndsLabel', endsLabel(el('taskRepeatEndsAt').value));
                renderPreview();
            });
        }
        document.querySelectorAll('.js-task-repeat-est').forEach(function (btn) {
            btn.addEventListener('click', function (e) {
                e.preventDefault();
                var v = btn.getAttribute('data-est-value') || '';
                setEstLabel(v === '' ? 0 : v);
            });
        });
        var calBox = el('taskRepeatCalendarPreview');
        if (calBox) {
            calBox.addEventListener('click', function (e) {
                var dayEl = e.target.closest('[data-date]');
                if (!dayEl || !calBox.contains(dayEl)) {
                    return;
                }
                var iso = dayEl.getAttribute('data-date');
                if (!iso || !parseIso(iso)) {
                    return;
                }
                if (el('taskRepeatStartFrom')) {
                    el('taskRepeatStartFrom').value = iso;
                }
                renderPreview();
            });
            calBox.addEventListener('scroll', maybeExtendCalendar);
            calBox.addEventListener('keydown', function (e) {
                if (e.key !== 'Enter' && e.key !== ' ') {
                    return;
                }
                var dayEl = e.target.closest('[data-date]');
                if (!dayEl) {
                    return;
                }
                e.preventDefault();
                dayEl.click();
            });
        }
        document.querySelectorAll('.js-task-repeat-ends-never').forEach(function (btn) {
            btn.addEventListener('click', function (e) {
                e.preventDefault();
                if (el('taskRepeatEndsAt')) {
                    el('taskRepeatEndsAt').value = '';
                }
                setText('taskRepeatEndsLabel', labels.never);
                renderPreview();
            });
        });
        if (el('taskRepeatEndRepeats')) {
            el('taskRepeatEndRepeats').addEventListener('click', function () {
                state.enabled = 0;
                state.mode = '';
                writeHidden();
                var modalEl = el('taskRepeatModal');
                if (modalEl && window.bootstrap && window.bootstrap.Modal) {
                    var inst = window.bootstrap.Modal.getInstance(modalEl);
                    if (inst) {
                        inst.hide();
                    }
                }
            });
        }

        document.querySelectorAll('.js-task-repeat-unit').forEach(function (btn) {
            btn.addEventListener('click', function (e) {
                e.preventDefault();
                state.interval_unit = btn.getAttribute('data-unit') || 'day';
                setText('taskRepeatUnitLabel', unitLabels[state.interval_unit] || unitLabels.day);
                renderPreview();
            });
        });
        document.querySelectorAll('.js-task-repeat-due').forEach(function (btn) {
            btn.addEventListener('click', function (e) {
                e.preventDefault();
                var due = btn.getAttribute('data-due');
                state.due_offset_days = due === '' ? null : parseInt(due, 10);
                setText('taskRepeatDueLabel', dueLabel(state.due_offset_days));
            });
        });
        document.querySelectorAll('.js-task-repeat-status').forEach(function (btn) {
            btn.addEventListener('click', function (e) {
                e.preventDefault();
                state.default_status = btn.getAttribute('data-status') || 'todo';
                state.after_status = state.default_status;
                setStatusDisplay(state.default_status);
            });
        });

        var dueCustomBtn = el('taskRepeatDueCustomBtn');
        if (dueCustomBtn) {
            dueCustomBtn.addEventListener('click', function (e) {
                e.preventDefault();
                e.stopPropagation();
                if (el('taskRepeatCustomDueDays')) {
                    var cur = state.due_offset_days === null ? 1 : Math.max(0, parseInt(state.due_offset_days, 10) || 1);
                    el('taskRepeatCustomDueDays').value = String(cur);
                }
                var nested = el('taskRepeatDueCustomModal');
                if (nested && window.bootstrap && window.bootstrap.Modal) {
                    window.bootstrap.Modal.getOrCreateInstance(nested).show();
                }
            });
        }
        var dueCustomModal = el('taskRepeatDueCustomModal');
        if (dueCustomModal) {
            dueCustomModal.addEventListener('show.bs.modal', function () {
                var parent = el('taskRepeatModal');
                if (parent) {
                    parent.classList.add('task-repeat-modal--behind');
                }
            });
            dueCustomModal.addEventListener('hidden.bs.modal', function () {
                var parent = el('taskRepeatModal');
                if (parent) {
                    parent.classList.remove('task-repeat-modal--behind');
                }
            });
        }
        var cMinus = el('taskRepeatCustomDueMinus');
        var cPlus = el('taskRepeatCustomDuePlus');
        var cDays = el('taskRepeatCustomDueDays');
        if (cMinus && cDays) {
            cMinus.addEventListener('click', function () {
                cDays.value = String(Math.max(0, (parseInt(cDays.value, 10) || 0) - 1));
            });
        }
        if (cPlus && cDays) {
            cPlus.addEventListener('click', function () {
                cDays.value = String(Math.min(365, (parseInt(cDays.value, 10) || 0) + 1));
            });
        }
        if (el('taskRepeatCustomDueSave')) {
            el('taskRepeatCustomDueSave').addEventListener('click', function () {
                var n = cDays ? Math.max(0, parseInt(cDays.value, 10) || 0) : 1;
                state.due_offset_days = n;
                setText('taskRepeatDueLabel', dueLabel(n));
                var nested = el('taskRepeatDueCustomModal');
                if (nested && window.bootstrap && window.bootstrap.Modal) {
                    var inst = window.bootstrap.Modal.getInstance(nested);
                    if (inst) inst.hide();
                }
            });
        }

        if (el('taskRepeatModalSave')) {
            el('taskRepeatModalSave').addEventListener('click', function () {
                var draft = readModalIntoDraft();
                Object.keys(draft).forEach(function (k) {
                    state[k] = draft[k];
                });
                state.enabled = 1;
                writeHidden();
                var modalEl = el('taskRepeatModal');
                if (modalEl && window.bootstrap && window.bootstrap.Modal) {
                    var inst = window.bootstrap.Modal.getInstance(modalEl);
                    if (inst) inst.hide();
                }
            });
        }
        var repeatModal = el('taskRepeatModal');
        if (repeatModal) {
            repeatModal.addEventListener('shown.bs.modal', function () {
                window.requestAnimationFrame(function () {
                    window.requestAnimationFrame(syncPreviewHeight);
                });
            });
        }
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', bind);
    } else {
        bind();
    }
})();
