$(document).ready(function() {
    let notificationCount = 0;
    let notifications = [];
    let notificationOffset = 0;
    let notificationLimit = 10;
    let allNotificationsLoaded = false;
    let notificationsInitialLoaded = false;

    function headerNotificationSkeletonHtml() {
        var row = function () {
            return '<div class="header-dd-skel-row">' +
                '<div class="reports-skel skel header-dd-skel-avatar"></div>' +
                '<div class="header-dd-skel-col">' +
                '<div class="reports-skel skel header-dd-skel-line header-dd-skel-line--lg"></div>' +
                '<div class="reports-skel skel header-dd-skel-line header-dd-skel-line--md"></div>' +
                '<div class="reports-skel skel header-dd-skel-line header-dd-skel-line--sm"></div>' +
                '</div></div>';
        };
        return '<div class="header-dropdown-skeleton header-dd-skel" role="status" aria-label="Loading notifications" aria-hidden="true">' +
            row() + row() + row() + row() + row() +
            '</div>';
    }

    function showNotificationSkeleton() {
        var $list = $('.all-notify');
        if (!$list.length) return;
        $list.attr('aria-busy', 'true');
        $list.html(headerNotificationSkeletonHtml());
    }

    // Show skeleton immediately so the bell dropdown is never a blank white box
    showNotificationSkeleton();

    function updateHeaderDropdownPosition(toggleEl) {
        // Header menus use data-bs-display="static" + CSS right:0 — Popper update causes a visible jump on mobile.
        return;
    }

    function scheduleHeaderDropdownReposition(toggleEl) {
        return;
    }

    function notificationToggleEl() {
        return document.querySelector('.notification-icons [data-bs-toggle="dropdown"], .notification-icons .notification-icon');
    }

    function clearHeaderDropdownPopperStyles(root) {
        var menu = root && root.querySelector ? root.querySelector('.dropdown-menu.msg-menu') : null;
        if (!menu) return;
        // Force CSS static anchor (right of icon) — clear any Popper inline coords that cause the side→jump glitch
        menu.style.removeProperty('transform');
        menu.style.removeProperty('translate');
        menu.style.removeProperty('inset');
        menu.style.removeProperty('left');
        menu.style.removeProperty('right');
        menu.style.removeProperty('top');
        menu.style.removeProperty('bottom');
        menu.style.removeProperty('margin');
        menu.style.removeProperty('position');
    }

    // If user opens the bell before the deferred fetch finishes, keep skeleton + kick load now
    $(document).on('show.bs.dropdown', '.notification-icons', function () {
        if (!notificationsInitialLoaded) {
            showNotificationSkeleton();
            loadNotifications(true);
        }
        clearHeaderDropdownPopperStyles(this);
    });
    $(document).on('shown.bs.dropdown', '.notification-icons', function () {
        clearHeaderDropdownPopperStyles(this);
    });

    function getAppBaseUrl() {
        if (typeof window.baseUrl === 'string' && window.baseUrl !== '') {
            return window.baseUrl;
        }
        if (typeof baseUrl !== 'undefined' && baseUrl) {
            return baseUrl;
        }
        if (typeof url !== 'undefined' && url) {
            return url;
        }
        var origin = window.location.origin || (window.location.protocol + '//' + window.location.host);
        var path = window.location.pathname || '/';
        // Prefer app root (/comon/) over role folder (/comon/admin/) for central modules.
        var m = path.match(/^(.*?\/)(?:admin|staff|client)(?:\/|$)/i);
        if (m && m[1]) {
            return origin + m[1];
        }
        var dir = path.replace(/\/[^/]*$/, '/');
        if (dir.slice(-1) !== '/') {
            dir += '/';
        }
        return origin + dir;
    }

    var baseUrl = getAppBaseUrl();
    // Re-assert after footer may have set window.baseUrl from PHP.
    if (typeof window.baseUrl === 'string' && window.baseUrl !== '') {
        baseUrl = window.baseUrl;
    }
    window.baseUrl = baseUrl;

    function escapeNotificationHtml(value) {
        return $('<div>').text(value == null ? '' : String(value)).html();
    }

    if (typeof url === 'undefined' || !url) {
        var url = baseUrl;
    }
    
    if (typeof lang === 'undefined') {
        var lang = {
            no_notifications: 'No notifications',
            loading: 'Loading...'
        };
    }

    /**
     * Role URL segment for buildRoleUrl(). Order:
     * 1) window.notificationRolePrefix if set by a page
     * 2) Path segment (admin|staff|client) — works on /admin/..., /staff/..., /client/...
     * 3) window.accountStatus from session — required for /mail/inbox, /mail/new, etc.
     *    (those URLs have no role folder; inbox already sets accountStatus; footer may set fallback)
     */
    function getRolePrefix() {
        if (typeof window.notificationRolePrefix === 'string' && window.notificationRolePrefix !== '') {
            return window.notificationRolePrefix;
        }
        const parts = window.location.pathname.split('/').filter(Boolean);
        for (let i = 0; i < parts.length; i++) {
            const seg = parts[i].toLowerCase();
            if (seg === 'admin' || seg === 'staff' || seg === 'client') {
                return seg + '/';
            }
        }
        var st = window.accountStatus;
        if (st === 1 || st === '1') {
            return 'admin/';
        }
        if (st === 2 || st === '2') {
            return 'client/';
        }
        if (st === 3 || st === '3') {
            return 'staff/';
        }
        return '';
    }

    function buildRoleUrl(pathWithQuery) {
        var p = String(pathWithQuery || '');
        // Pretty page paths: leads.php → leads (query preserved)
        p = p.replace(/^([^?#]+)\.php\b/i, '$1');
        return baseUrl + getRolePrefix() + p;
    }

    function buildAppPageUrl(pathWithQuery) {
        var p = String(pathWithQuery || '');
        p = p.replace(/^([^?#]+)\.php\b/i, '$1');
        return baseUrl + p.replace(/^\//, '');
    }

    /** Parse MySQL datetime for sorting (newest first). */
    function notificationSortTimestamp(createdAt) {
        if (!createdAt) return 0;
        var s = String(createdAt).replace(' ', 'T');
        var t = Date.parse(s);
        return isNaN(t) ? 0 : t;
    }

    /** Keep bell list strictly newest-first (matches DB ORDER BY created_at DESC, id DESC). */
    function sortNotificationsNewestFirst(arr) {
        if (!arr || !arr.length) return arr;
        arr.sort(function(a, b) {
            var tb = notificationSortTimestamp(b.created_at);
            var ta = notificationSortTimestamp(a.created_at);
            if (tb !== ta) return tb - ta;
            return (parseInt(b.id, 10) || 0) - (parseInt(a.id, 10) || 0);
        });
        return arr;
    }
    
    // Defer first bell fetch + polling until after page load (dashboard TTFB / charts first)
    function startBellPolling() {
        loadNotifications(true);
        setInterval(function() {
            refreshBellNotifications();
        }, 30000);
    }
    if (typeof window.comonAfterPageQuiet === 'function') {
        window.comonAfterPageQuiet(startBellPolling, 2000);
    } else if (document.readyState === 'complete') {
        setTimeout(startBellPolling, 2000);
    } else {
        window.addEventListener('load', function () {
            setTimeout(startBellPolling, 2000);
        });
    }

    /**
     * Soft refresh for the bell dropdown.
     * Keeps already-loaded older notifications (after Load More) instead of resetting to page 1.
     */
    function refreshBellNotifications() {
        var keep = Math.max(notificationLimit, notifications.length || 0);
        if (keep > 100) {
            keep = 100;
        }
        var $list = $('.all-notify');
        var prevScroll = $list.length ? $list.scrollTop() : 0;
        var wasFullyLoaded = allNotificationsLoaded;

        $.ajax({
            url: baseUrl + 'ajax/notifications_ajax.php',
            type: 'GET',
            data: {
                action: 'get_notifications',
                limit: keep,
                offset: 0
            },
            dataType: 'json',
            success: function(response) {
                if (!response.success) {
                    return;
                }
                notifications = response.notifications || [];
                sortNotificationsNewestFirst(notifications);
                notificationCount = response.unread_count;
                notificationOffset = notifications.length;
                // If user had reached the end, keep that unless the page is clearly full (more may exist)
                if (wasFullyLoaded && notifications.length < keep) {
                    allNotificationsLoaded = true;
                } else {
                    allNotificationsLoaded = notifications.length < keep;
                }
                updateNotificationCount();
                updateNotificationList();
                if (prevScroll > 0 && $list.length) {
                    $list.scrollTop(prevScroll);
                }
            }
        });
    }
    
    function loadNotifications(reset) {
        if (reset === undefined) reset = false;
        if (reset) {
            notificationOffset = 0;
            allNotificationsLoaded = false;
        }
        if (allNotificationsLoaded) return;
        // First paint / hard reset with no rows yet → keep skeleton visible while fetching
        if (reset && !notificationsInitialLoaded && notifications.length === 0) {
            showNotificationSkeleton();
        }
        $.ajax({
            url: baseUrl + 'ajax/notifications_ajax.php',
            type: 'GET',
            data: {
                action: 'get_notifications',
                limit: notificationLimit,
                offset: notificationOffset
            },
            dataType: 'json',
            success: function(response) {
                notificationsInitialLoaded = true;
                if (response.success) {
                    if (reset) {
                        notifications = response.notifications;
                    } else {
                        notifications = notifications.concat(response.notifications);
                    }
                    sortNotificationsNewestFirst(notifications);
                    notificationCount = response.unread_count;
                    notificationOffset = notifications.length;
                    updateNotificationCount();
                    updateNotificationList();
                    if (response.notifications.length < notificationLimit) {
                        allNotificationsLoaded = true;
                    }
                } else {
                    updateNotificationListError(response.error || lang.no_notifications);
                }
            },
            error: function() {
                notificationsInitialLoaded = true;
                updateNotificationListError('Error loading notifications');
            }
        });
    }
    
    function updateNotificationCount() {
        $('.notification-count').text(notificationCount);
        if (notificationCount > 0) {
            $('.notification-count').show();
        } else {
            $('.notification-count').hide();
        }
    }
    
    function updateNotificationList() {
        const $list = $('.all-notify');
        $list.attr('aria-busy', 'false');
        if (notifications.length === 0) {
            $list.html('<div class="no-notifications">' + escapeNotificationHtml(lang.no_notifications) + '</div>');
            scheduleHeaderDropdownReposition(notificationToggleEl());
            return;
        }
        let html = '';
        notifications.forEach(function(notification) {
            const isUnread = notification.is_read == 0;
            const unreadClass = isUnread ? 'unread' : '';
            const relatedType = String(notification.related_type || '').toLowerCase();
            const isEcommerceOrder = notification.type === 'ecommerce_order_received'
                && relatedType === 'ecommerce_order'
                && parseInt(notification.related_id, 10) > 0;
            let svgIcon = '';
            let bgColor = '';
            if (notification.type && notification.type.indexOf('project') === 0) {
                bgColor = '#e3f0ff';
                svgIcon = '<span style="background:'+bgColor+';border-radius:50%;display:flex;align-items:center;justify-content:center;width:40px;height:40px;"><svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="#2196f3" width="28" height="28"><path stroke-linecap="round" stroke-linejoin="round" d="M2.25 12.75V12A2.25 2.25 0 014.5 9.75h15A2.25 2.25 0 0121.75 12v.75m-8.69-6.44l-2.12-2.12a1.5 1.5 0 00-1.061-.44H4.5A2.25 2.25 0 002.25 6v12a2.25 2.25 0 002.25 2.25h15A2.25 2.25 0 0021.75 18V9a2.25 2.25 0 00-2.25-2.25h-5.379a1.5 1.5 0 01-1.06-.44z"></path></svg></span>';
            } else if (notification.type && notification.type.indexOf('media_') === 0) {
                bgColor = '#e3f0ff';
                svgIcon = '<span style="background:'+bgColor+';border-radius:50%;display:flex;align-items:center;justify-content:center;width:40px;height:40px;"><svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="#2196f3" width="28" height="28"><path stroke-linecap="round" stroke-linejoin="round" d="M2.25 12.75V12A2.25 2.25 0 014.5 9.75h15A2.25 2.25 0 0121.75 12v.75m-8.69-6.44l-2.12-2.12a1.5 1.5 0 00-1.061-.44H4.5A2.25 2.25 0 002.25 6v12a2.25 2.25 0 002.25 2.25h15A2.25 2.25 0 0021.75 18V9a2.25 2.25 0 00-2.25-2.25h-5.379a1.5 1.5 0 01-1.06-.44z"></path></svg></span>';
            } else if (notification.type && notification.type.indexOf('invoice') === 0) {
                bgColor = '#e6f9ed';
                svgIcon = '<span style="background:'+bgColor+';border-radius:50%;display:flex;align-items:center;justify-content:center;width:40px;height:40px;"><svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.2" stroke="#43a047" width="28" height="28"><path d="M20.016 2C18.903 2 18 4.686 18 8h2.016c.972 0 1.457 0 1.758-.335c.3-.336.248-.778.144-1.661C21.64 3.67 20.894 2 20.016 2"></path><path d="M18 8.054v10.592c0 1.511 0 2.267-.462 2.565c-.755.486-1.922-.534-2.509-.904c-.485-.306-.727-.458-.996-.467c-.291-.01-.538.137-1.062.467l-1.911 1.205c-.516.325-.773.488-1.06.488s-.545-.163-1.06-.488l-1.91-1.205c-.486-.306-.728-.458-.997-.467c-.587.37-1.754 1.39-2.51.904C2 20.913 2 20.158 2 18.646V8.054c0-2.854 0-4.28.879-5.167C3.757 2 5.172 2 8 2h12"></path><path d="M10 8c-1.105 0-2 .672-2 1.5s.895 1.5 2 1.5s2 .672 2 1.5s-.895 1.5-2 1.5m0-6c.87 0 1.612.417 1.886 1M10 8V7m0 7c-.87 0-1.612-.417-1.886-1M10 14v1"></path></svg></span>';
            } else if (notification.type && (notification.type.indexOf('task') === 0 || notification.type.indexOf('subtask_') === 0)) {
                svgIcon = '<span class="dash-activity-icon-badge dash-activity-icon-badge--task"><span class="ts-icon ts-icon-tasks-sidebar h-6" aria-hidden="true"></span></span>';
            } else if (notification.type && notification.type.indexOf('lead') === 0) {
                // lead_created, lead_assignees_changed, lead_status_changed, etc.
                bgColor = '#ede7f6';
                svgIcon = '<span style="background:'+bgColor+';border-radius:50%;display:flex;align-items:center;justify-content:center;width:40px;height:40px;"><svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="#673ab7" width="28" height="28"><circle cx="12" cy="12" r="3"/><circle cx="12" cy="12" r="8"/><path stroke-linecap="round" d="M12 2v4M12 18v4M2 12h4M18 12h4"/></svg></span>';
            } else if (
                (notification.type && notification.type.indexOf('marketing_campaign_') === 0) ||
                (notification.related_type && String(notification.related_type).toLowerCase() === 'marketing_campaign')
            ) {
                svgIcon = '<span class="dash-activity-icon-badge dash-activity-icon-badge--marketing"><span class="ts-icon ts-icon-marketing h-6" aria-hidden="true"></span></span>';
            } else if (notification.type === 'ecommerce_order_received' || (notification.related_type && ['ecommerce_order', 'ecommerce_orders'].indexOf(String(notification.related_type).toLowerCase()) !== -1)) {
                bgColor = '#fff8e1';
                svgIcon = '<span style="background:'+bgColor+';border-radius:50%;display:flex;align-items:center;justify-content:center;width:40px;height:40px;"><svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="#f57c00" width="28" height="28"><path stroke-linecap="round" stroke-linejoin="round" d="M2.25 3h1.386c.51 0 .955.343 1.087.835l.383 1.437M7.5 14.25a3 3 0 0 0-3 3h15.75m-12.75-3h11.218c1.121-2.3 2.1-4.684 2.924-7.138a60.114 60.114 0 0 0-16.536-1.84M7.5 14.25 5.106 5.272M6 20.25a.75.75 0 1 1-1.5 0 .75.75 0 0 1 1.5 0Zm12.75 0a.75.75 0 1 1-1.5 0 .75.75 0 0 1 1.5 0Z" /></svg></span>';
            } else if (notification.type && notification.type.indexOf('attendance_') === 0) {
                bgColor = '#e0f2f1';
                svgIcon = '<span style="background:'+bgColor+';border-radius:50%;display:flex;align-items:center;justify-content:center;width:40px;height:40px;"><svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="#00897b" width="28" height="28"><path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6h4.5m4.5 0a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" /></svg></span>';
            } else if (notification.type === 'ai_agent_alert' || notification.type === 'ai_agent_digest' || notification.type === 'ai_agent_auto_reply' || (notification.type && notification.type.indexOf('ai_agent_') === 0)) {
                bgColor = '#e8f0fe';
                svgIcon = '<span style="background:'+bgColor+';border-radius:50%;display:flex;align-items:center;justify-content:center;width:40px;height:40px;"><svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="#3b82f6" width="28" height="28"><path stroke-linecap="round" stroke-linejoin="round" d="M10.2 16.8 9.25 20.1l-.95-3.3A5.1 5.1 0 0 0 4.8 13.3L1.5 12.35l3.3-.95A5.1 5.1 0 0 0 8.3 7.9L9.25 4.6l.95 3.3A5.1 5.1 0 0 0 13.7 11.4l3.3.95-3.3.95a5.1 5.1 0 0 0-3.5 3.5Z"/><path stroke-linecap="round" stroke-linejoin="round" d="M18.55 8.35 18.25 9.55l-.3-1.2a3.6 3.6 0 0 0-2.55-2.55l-1.2-.3 1.2-.3a3.6 3.6 0 0 0 2.55-2.55l.3-1.2.3 1.2a3.6 3.6 0 0 0 2.55 2.55l1.2.3-1.2.3a3.6 3.6 0 0 0-2.55 2.55Z"/></svg></span>';
            } else {
                bgColor = '#e0e0e0';
                svgIcon = '<span style="background:'+bgColor+';border-radius:50%;display:flex;align-items:center;justify-content:center;width:40px;height:40px;"><svg width="32" height="32" viewBox="0 0 32 32" fill="none" xmlns="http://www.w3.org/2000/svg"><circle cx="16" cy="16" r="16" fill="'+bgColor+'"/><path d="M10 22V10h12v12H10zm2-2h8V12h-8v8z" fill="#757575"/></svg></span>';
            }
            let titleHtml = escapeNotificationHtml(notification.title);
            let messageHtml = escapeNotificationHtml(notification.message);
            let timeHtml = '<div class="notification-time">' + escapeNotificationHtml(notification.time_ago) + '</div>';
            if (isEcommerceOrder) {
                const messageLines = String(notification.message || '').split(/\r?\n/);
                titleHtml = escapeNotificationHtml(notification.title);
                messageHtml = escapeNotificationHtml(messageLines[0] || '');
                const orderMeta = escapeNotificationHtml(messageLines[1] || '');
                timeHtml = '<div class="notification-time">'
                    + orderMeta
                    + (orderMeta ? ' <span aria-hidden="true">·</span> ' : '')
                    + escapeNotificationHtml(notification.time_ago)
                    + '</div>';
            } else if (notification.type === 'ai_agent_alert' || notification.type === 'ai_agent_digest' || notification.type === 'ai_agent_auto_reply') {
                const messageLines = String(notification.message || '').split(/\r?\n/);
                titleHtml = escapeNotificationHtml(notification.title);
                messageHtml = escapeNotificationHtml(messageLines[0] || '');
                const agentMeta = escapeNotificationHtml(messageLines[1] || '');
                timeHtml = '<div class="notification-time">'
                    + (agentMeta ? agentMeta + ' <span aria-hidden="true">·</span> ' : '')
                    + escapeNotificationHtml(notification.time_ago)
                    + '</div>';
            }
            html += '<div class="notification-item ' + unreadClass + '" data-id="' + notification.id + '">' +
                '<div class="notification-avatar" style="width:40px;height:40px;display:flex;align-items:center;justify-content:center;">' +
                svgIcon +
                '</div>' +
                '<div class="notification-content">' +
                '<div class="notification-title">' + titleHtml + '</div>' +
                '<div class="notification-message">' + messageHtml + '</div>' +
                timeHtml +
                '</div>' +
                '</div>';
        });
        if (notifications.length >= notificationLimit && !allNotificationsLoaded) {
            html += '<div class="notification-load-more-wrap" style="pointer-events:auto;"><button type="button" class="notification-load-more secondary-btn-a">Load More Notification</button></div>';
        }
        $list.html(html);
        scheduleHeaderDropdownReposition(notificationToggleEl());
    }

    function updateNotificationListError(msg) {
        $('.all-notify').attr('aria-busy', 'false').html('<div class="no-notifications">' + escapeNotificationHtml(msg) + '</div>');
        scheduleHeaderDropdownReposition(notificationToggleEl());
    }
    
    $(document).on('click', '.all-notify .notification-item', function() {
        const notificationId = $(this).data('id');
        const $item = $(this);
        $.ajax({
            url: baseUrl + 'ajax/notifications_ajax.php',
            type: 'POST',
            data: {
                action: 'mark_as_read',
                notification_id: notificationId
            },
            dataType: 'json',
            success: function(response) {
                if (response.success) {
                    $item.removeClass('unread');
                    notificationCount = Math.max(0, notificationCount - 1);
                    updateNotificationCount();
                }
            }
        });

        const notification = notifications.find(function(n){ return n.id == notificationId; });
        if (notification) {
            setTimeout(function() {
                handleNotificationNavigation(notification);
            }, 200);
        }
    });

    $(document).on('mouseenter', '.all-notify .notification-item', function() {
        $(this).css('cursor', 'pointer');
    });
    
    $(document).on('click', '.notification-view-all', function(e) {
        e.stopPropagation();
    });

    $(document).on('click', '.mark-all-read', function(e) {
        e.preventDefault();
        e.stopPropagation();
        $.ajax({
            url: baseUrl + 'ajax/notifications_ajax.php',
            type: 'POST',
            data: {
                action: 'mark_all_as_read'
            },
            dataType: 'json',
            success: function(response) {
                if (response.success) {
                    $('.all-notify .notification-item').removeClass('unread');
                    notificationCount = 0;
                    updateNotificationCount();
                }
            }
        });
    });
    
    function handleNotificationNavigation(notification) {
        // Lead board: notifyLeadUsers() stores related_id = lead id and related_type = 'lead'
        // (lead_created, lead_assignees_changed, lead_status_changed, lead_note_updated, tags, comments, etc.)
        var leadRelatedId = notification.related_id;
        var relType = String(notification.related_type || '').toLowerCase();
        var nType = String(notification.type || '');
        if (nType === 'ai_agent_auto_reply') {
            var accId = parseInt(notification.account_id, 10) || 0;
            var threadId = String(notification.thread_id || '').trim();
            if (!threadId && notification.related_id) {
                threadId = String(notification.related_id);
            }
            if (accId > 0 && threadId) {
                window.location.href = baseUrl + 'mail/inbox?account=' + encodeURIComponent(String(accId))
                    + '&folder=inbox&thread=' + encodeURIComponent(threadId);
            } else if (accId > 0) {
                window.location.href = baseUrl + 'mail/inbox?account=' + encodeURIComponent(String(accId)) + '&folder=inbox';
            } else {
                window.location.href = baseUrl + 'ai/agents';
            }
            return;
        }
        if (nType === 'ai_agent_digest' || (nType === 'ai_agent_alert' && !notification.agent_type)) {
            window.location.href = baseUrl + 'ai/agents/alerts';
            return;
        }
        if (nType === 'ai_agent_alert' || (relType === 'ai_agent' && nType.indexOf('ai_agent_') === 0)) {
            var agentType = String(notification.agent_type || '').replace(/[^a-z_]/g, '');
            if (agentType) {
                window.location.href = baseUrl + 'ai/agents?agent=' + encodeURIComponent(agentType);
            } else {
                window.location.href = baseUrl + 'ai/agents/alerts';
            }
            return;
        }
        if (nType.indexOf('marketing_campaign_') === 0 || relType === 'marketing_campaign') {
            if (notification.related_id) {
                window.location.href = buildAppPageUrl('marketing/report.php?id=' + encodeURIComponent(String(notification.related_id)));
            } else {
                window.location.href = buildAppPageUrl('marketing/campaigns.php');
            }
            return;
        }
        if (relType === 'ecommerce_order' && notification.related_id) {
            window.location.href = buildAppPageUrl('ecommerce/order-view.php?id='
                + encodeURIComponent(String(notification.related_id))
                + '&return=orders');
            return;
        }
        if (nType === 'ecommerce_order_received' || relType === 'ecommerce_orders') {
            window.location.href = buildAppPageUrl('ecommerce/orders.php');
            return;
        }
        if (relType === 'lead' || nType.indexOf('lead_') === 0) {
            if (leadRelatedId) {
                window.location.href = buildRoleUrl('leads.php?open_lead=' + encodeURIComponent(String(leadRelatedId)));
            } else {
                window.location.href = buildRoleUrl('leads.php');
            }
            return;
        }
        if (nType === 'attendance_leave_requested' || nType === 'attendance_leave_approved' || nType === 'attendance_leave_rejected' || relType === 'attendance_leave') {
            window.location.href = buildRoleUrl('attendance-leave.php');
            return;
        }
        if (nType === 'attendance_regularization_requested' || nType === 'attendance_regularization_approved' || nType === 'attendance_regularization_rejected' || relType === 'attendance_regularization') {
            window.location.href = buildRoleUrl('attendance-regularization.php');
            return;
        }

        if (nType === 'media_folder_shared' || nType === 'media_shared_folder_new_upload') {
            var ridF = notification.related_id ? parseInt(notification.related_id, 10) : 0;
            var pidF = notification.related_project_id ? parseInt(notification.related_project_id, 10) : 0;
            if (pidF) {
                var urlF = 'media.php?projectId=' + encodeURIComponent(String(pidF));
                if (ridF) {
                    urlF += '&folder=' + encodeURIComponent(String(ridF));
                }
                window.location.href = buildRoleUrl(urlF);
            } else {
                var urlV = 'media-vault.php';
                if (ridF) {
                    urlV += '?folder=' + encodeURIComponent(String(ridF));
                }
                window.location.href = buildRoleUrl(urlV);
            }
            return;
        }
        if (nType === 'media_file_shared') {
            var pidM = notification.related_project_id ? parseInt(notification.related_project_id, 10) : 0;
            if (pidM) {
                window.location.href = buildRoleUrl('media.php?projectId=' + encodeURIComponent(String(pidM)));
            } else {
                window.location.href = buildRoleUrl('media-vault.php');
            }
            return;
        }
        if (nType === 'media_vault_extended_share') {
            var fromUid = notification.from_user_id != null ? parseInt(notification.from_user_id, 10) : 0;
            var recipientUid = notification.user_id != null ? parseInt(notification.user_id, 10) : 0;
            var ridE = notification.related_id ? parseInt(notification.related_id, 10) : 0;
            var pidE = notification.related_project_id ? parseInt(notification.related_project_id, 10) : 0;
            if (relType === 'mv_profile_share' && recipientUid) {
                var urlP = 'profile.php?user_id=' + encodeURIComponent(String(recipientUid)) + '&tab=media&view=grid&page=1';
                if (ridE) {
                    urlP += '&folder=' + encodeURIComponent(String(ridE));
                }
                window.location.href = buildRoleUrl(urlP);
            } else if (relType === 'mv_project_link' && pidE) {
                window.location.href = buildRoleUrl('media.php?projectId=' + encodeURIComponent(String(pidE)));
            } else {
                window.location.href = buildRoleUrl('media-vault.php');
            }
            return;
        }

        switch (notification.type) {
            case 'project_created':
            case 'project_updated':
                if (notification.related_id) {
                    window.location.href = buildRoleUrl('overview.php?projectId=' + notification.related_id);
                }
                break;
            case 'task_reminder_1':
            case 'task_reminder_2':
            case 'task_reminder_3':
                // Reminder/due-today clicks should land on project overview directly.
                if (notification.related_project_id) {
                    window.location.href = buildRoleUrl('overview.php?projectId=' + encodeURIComponent(String(notification.related_project_id)));
                } else if (notification.related_id) {
                    setTimeout(function() {
                        const sidebar = document.getElementById('task-sidebar');
                        const hasSidebar = sidebar !== null;
                        if (typeof openTaskSidebar === 'function' && hasSidebar) {
                            openTaskSidebar(notification.related_id);
                        } else if (hasSidebar) {
                            openTaskSidebarDirect(notification.related_id);
                        } else {
                            window.location.href = buildRoleUrl('kanban.php?internal=1');
                        }
                    }, 200);
                }
                break;
            case 'task_created':
            case 'task_updated':
            case 'task_status_changed':
            case 'subtask_created':
            case 'subtask_completed':
                if (notification.related_id) {
                    setTimeout(function() {
                        const sidebar = document.getElementById('task-sidebar');
                        const hasSidebar = sidebar !== null;
                        if (typeof openTaskSidebar === 'function' && hasSidebar) {
                            openTaskSidebar(notification.related_id);
                        } else if (hasSidebar) {
                            openTaskSidebarDirect(notification.related_id);
                        } else {
                            if (notification.related_project_id) {
                                window.location.href = buildRoleUrl('kanban.php?projectId=' + notification.related_project_id);
                            } else {
                                window.location.href = buildRoleUrl('kanban.php?internal=1');
                            }
                        }
                    }, 200);
                }
                break;
            case 'invoice_created':
            case 'invoice_updated':
            case 'invoice_paid':
            case 'invoice_not_clear':
                if (notification.related_project_id) {
                    window.location.href = buildRoleUrl('payments?projectId=' + notification.related_project_id);
                } else if (notification.related_id) {
                    window.location.href = buildRoleUrl('invoices');
                }
                break;
            case 'project_media_file_uploaded':
                if (notification.related_project_id) {
                    var pmUrl = 'media.php?projectId=' + encodeURIComponent(String(notification.related_project_id));
                    if (notification.related_id) {
                        pmUrl += '&folder=' + encodeURIComponent(String(notification.related_id)) + '&page=1';
                    }
                    window.location.href = buildRoleUrl(pmUrl);
                } else if (notification.related_id) {
                    window.location.href = buildRoleUrl('media.php?projectId=' + notification.related_id);
                } else {
                    window.location.href = buildRoleUrl('media-vault.php');
                }
                break;
        }
    }
    
    $(document).on('click', '.notification-load-more', function(e) {
        e.preventDefault();
        e.stopPropagation();
        e.stopImmediatePropagation();
        // Continue from currently loaded count (avoids gaps after soft poll refresh)
        notificationOffset = notifications.length;
        loadNotifications(false);
    });
    
    function openTaskSidebarDirect(taskId) {
        if (typeof ensureMessagesCss === 'function') {
            ensureMessagesCss();
        }
        const sidebar = document.getElementById('task-sidebar');
        const loading = document.getElementById('task-loading');
        const content = document.getElementById('task-content');
        const error = document.getElementById('task-error');
        
        if (!sidebar || !loading || !content || !error) {
            return;
        }
        
        sidebar.classList.add('open');
        loading.style.display = 'block';
        content.style.display = 'none';
        error.style.display = 'none';
        
        fetch(baseUrl + 'includes/task_details.php?id=' + taskId)
            .then(function(response){ return response.text(); })
            .then(function(text){
                try {
                    const data = JSON.parse(text);
                    if (data.status === 'ok' || data.task) {
                        const titleElem = document.getElementById('formatted-task-title');
                        const descElem = document.getElementById('task-description');
                        if (titleElem) titleElem.textContent = (data.task && data.task.title) ? data.task.title : 'Task Details';
                        if (descElem) descElem.innerHTML = (data.task && data.task.description) ? (typeof window.tasksessionSanitizeHtml === 'function' ? window.tasksessionSanitizeHtml(data.task.description) : $('<div>').text(data.task.description).html()) : 'No description provided';
                        loading.style.display = 'none';
                        content.style.display = 'block';
                    } else {
                        loading.style.display = 'none';
                        error.style.display = 'block';
                    }
                } catch (parseError) {
                    loading.style.display = 'none';
                    error.style.display = 'block';
                }
            })
            .catch(function(){
                loading.style.display = 'none';
                error.style.display = 'block';
            });
    }
    
});