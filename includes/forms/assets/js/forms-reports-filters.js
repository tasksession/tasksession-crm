(function () {
    'use strict';

    var rootSelector = '.forms-reports-filters-wrap';

    function closeDropdowns(root) {
        root.querySelectorAll('.task-reports-toolbar-menu').forEach(function (menu) {
            menu.classList.remove('active');
            var search = menu.querySelector('.task-reports-toolbar-menu-search');
            if (search) {
                search.value = '';
            }
            menu.querySelectorAll('.task-reports-toolbar-menu-list button').forEach(function (btn) {
                btn.style.display = '';
            });
        });
        root.querySelectorAll('.task-reports-toolbar-toggle').forEach(function (btn) {
            btn.setAttribute('aria-expanded', 'false');
        });
    }

    function toggleDropdown(root, menuId, event) {
        if (event) {
            event.preventDefault();
            event.stopPropagation();
        }
        var menu = document.getElementById(menuId);
        if (!menu) {
            return;
        }
        var willOpen = !menu.classList.contains('active');
        closeDropdowns(root);
        if (willOpen) {
            menu.classList.add('active');
            var toggle = root.querySelector('[data-reports-dropdown="' + menuId + '"]');
            if (toggle) {
                toggle.setAttribute('aria-expanded', 'true');
            }
        }
    }

    function setFilterField(form, field, value, label, root) {
        var input = form.querySelector('[name="' + field + '"]');
        if (input) {
            input.value = value;
        }
        var wrapper = null;
        root.querySelectorAll('[data-filter-field="' + field + '"]').forEach(function (menuItem) {
            if (menuItem.getAttribute('data-filter-value') === String(value)) {
                wrapper = menuItem.closest('.toolbar-dropdown-wrapper');
            }
        });
        if (wrapper) {
            var btnText = wrapper.querySelector('.task-reports-filter-selected');
            if (btnText) {
                btnText.textContent = label;
            }
            var menu = wrapper.querySelector('.task-reports-toolbar-menu');
            if (menu) {
                menu.querySelectorAll('[data-filter-field="' + field + '"]').forEach(function (menuItem) {
                    menuItem.classList.toggle('active', menuItem.getAttribute('data-filter-value') === String(value));
                });
            }
        }
        closeDropdowns(root);
    }

    document.addEventListener('DOMContentLoaded', function () {
        document.querySelectorAll(rootSelector).forEach(function (root) {
            root.querySelectorAll('.task-reports-toolbar-toggle').forEach(function (btn) {
                btn.addEventListener('click', function (e) {
                    var menuId = btn.getAttribute('data-reports-dropdown');
                    if (menuId) {
                        toggleDropdown(root, menuId, e);
                    }
                });
            });

            root.querySelectorAll('.task-reports-filters-form').forEach(function (form) {
                form.addEventListener('click', function (e) {
                    var item = e.target.closest('[data-filter-field]');
                    if (!item) {
                        return;
                    }
                    e.preventDefault();
                    var field = item.getAttribute('data-filter-field');
                    var value = item.getAttribute('data-filter-value');
                    var labelNode = item.querySelector('span');
                    var label = labelNode ? labelNode.textContent.trim() : item.textContent.trim();
                    setFilterField(form, field, value, label, root);
                });
            });
        });

        document.addEventListener('click', function (e) {
            if (!e.target.closest(rootSelector + ' .task-reports-filter-dropdown')) {
                document.querySelectorAll(rootSelector).forEach(function (root) {
                    closeDropdowns(root);
                });
            }
        });
    });
})();
