(function () {
    'use strict';

    function initDescriptionReadMore() {
        var wraps = document.querySelectorAll('[data-po-desc]');
        if (!wraps.length) {
            return;
        }

        wraps.forEach(function (wrap) {
            var content = wrap.querySelector('.po-description-content');
            var toggle = wrap.querySelector('.po-description-toggle');
            if (!content || !toggle) {
                return;
            }

            var readMoreLabel = toggle.textContent.trim() || 'Read more';
            var readLessLabel = toggle.getAttribute('data-read-less') || 'Read less';

            function syncToggle() {
                if (content.classList.contains('po-description-content--expanded')) {
                    return;
                }
                toggle.hidden = content.scrollHeight <= content.clientHeight + 2;
            }

            toggle.addEventListener('click', function () {
                var expanded = content.classList.toggle('po-description-content--expanded');
                content.classList.toggle('po-description-content--clamp', !expanded);
                toggle.textContent = expanded ? readLessLabel : readMoreLabel;
                toggle.setAttribute('aria-expanded', expanded ? 'true' : 'false');
                if (!expanded) {
                    syncToggle();
                } else {
                    toggle.hidden = false;
                }
            });

            requestAnimationFrame(function () {
                syncToggle();
            });
        });
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initDescriptionReadMore);
    } else {
        initDescriptionReadMore();
    }
})();
