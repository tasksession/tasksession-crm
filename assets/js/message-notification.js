(function() {
    // Beep sound setup using absolute path with baseUrl
    var audio = new Audio(window.baseUrl + 'assets/beep/beeps.mp3');
    // Unlock audio on first user interaction (but don't play the sound)
    document.addEventListener('click', function unlockAudio() {
        // Just unlock audio without playing - set volume to 0, play, then restore
        var originalVolume = audio.volume;
        audio.volume = 0;
        audio.play().then(() => {
            audio.pause();
            audio.currentTime = 0;
            audio.volume = originalVolume;
        }).catch(() => {});
        document.removeEventListener('click', unlockAudio);
    });
    
    // Track notification state properly
    var lastNotificationIds = new Set();
    var hasPolledOnce = false;
    var isInitialLoad = true;

    // Suppress beep if user just navigated from notification
    let suppressBeepOnLoad = false;
    if (sessionStorage.getItem('navigatedFromNotification')) {
        suppressBeepOnLoad = true;
        sessionStorage.removeItem('navigatedFromNotification');
    }

    // Progressive polling — start slower so first paint / charts are not starved
    var intervals = [15000, 20000, 30000, 30000];
    var intervalIndex = 0;
    var pollTimer = null;

    if (!window.unreadMessages) {
        window.unreadMessages = [];
    }
    try {
        window.keptReadMessages = JSON.parse(sessionStorage.getItem('keptReadMessages') || '[]');
        if (!Array.isArray(window.keptReadMessages)) {
            window.keptReadMessages = [];
        }
    } catch (e) {
        window.keptReadMessages = [];
    }

    function persistKeptReadMessages() {
        try {
            sessionStorage.setItem('keptReadMessages', JSON.stringify(window.keptReadMessages || []));
        } catch (e) {}
    }

    /** Stable key for a dropdown card (group / task / discussion / dm / email / reaction). */
    function getItemKey(msg) {
        if (!msg) return '';
        if (msg.is_email_notification) {
            return 'email_' + (msg.notification_id || msg.id || '');
        }
        // Reactions share the same card key as their chat (one card per chat, last msg updates)
        if (msg.is_chat_reaction) {
            if (msg.is_task_chat && msg.task_id) return 'task_' + msg.task_id;
            if (msg.is_group_chat && msg.group_id) return 'group_' + msg.group_id;
            if (msg.Project_id && msg.Project_id !== '0' && msg.Project_id !== 0) {
                return 'discussion_' + msg.Project_id;
            }
            if (msg.chat_type === 'one_to_one' || (!msg.is_task_chat && !msg.is_group_chat)) {
                return 'user_' + (msg.user_id || msg.context_id || '');
            }
            return 'chat_reaction_' + (msg.chat_type || 'x') + '_' + (msg.context_id || msg.notification_id || '');
        }
        if (msg.is_group_chat && msg.group_id) {
            return 'group_' + msg.group_id;
        }
        if (msg.is_task_chat && msg.task_id) {
            return 'task_' + msg.task_id;
        }
        if (msg.Project_id && msg.Project_id !== '0' && msg.Project_id !== 0) {
            return 'discussion_' + msg.Project_id;
        }
        if (msg.id != null && String(msg.id).indexOf('chat_') === 0) {
            return String(msg.id);
        }
        return 'user_' + (msg.user_id || msg.id || '');
    }

    function isMsgRead(msg) {
        if (!msg) return false;
        return msg.is_read === 1 || msg.is_read === true || String(msg.status || '').toLowerCase() === 'read';
    }

    function getDisplayMessages() {
        var unread = Array.isArray(window.unreadMessages) ? window.unreadMessages : [];
        var kept = Array.isArray(window.keptReadMessages) ? window.keptReadMessages : [];
        var map = {};
        unread.forEach(function(msg) {
            var key = getItemKey(msg);
            if (!key) return;
            map[key] = Object.assign({}, msg, { is_read: 0, status: 'unread' });
        });
        kept.forEach(function(msg) {
            var key = getItemKey(msg);
            if (!key) return;
            if (map[key] && !isMsgRead(map[key])) {
                return; // still unread on server — prefer unread
            }
            map[key] = Object.assign({}, msg, { is_read: 1, status: 'read' });
        });
        return Object.keys(map).map(function(k) { return map[k]; });
    }

    function countUnreadOnly(list) {
        var arr = list || getDisplayMessages();
        return arr.filter(function(msg) { return !isMsgRead(msg); }).length;
    }

    function updateEnvelopeBadge() {
        var messageCounter = document.querySelector('.msg-icon-img .head-counter');
        if (!messageCounter) return;
        var unreadCount = countUnreadOnly();
        if (unreadCount > 0) {
            messageCounter.textContent = unreadCount;
            messageCounter.style.display = '';
        } else {
            messageCounter.style.display = 'none';
        }
    }

    function keepMessageAsRead(msg) {
        if (!msg) return;
        var key = getItemKey(msg);
        if (!key) return;
        var readCopy = Object.assign({}, msg, { is_read: 1, status: 'read' });
        window.keptReadMessages = (window.keptReadMessages || []).filter(function(m) {
            return getItemKey(m) !== key;
        });
        window.keptReadMessages.push(readCopy);
        // Remove from unread snapshot so UI shows dimmed until poll refreshes
        window.unreadMessages = (window.unreadMessages || []).filter(function(m) {
            return getItemKey(m) !== key;
        });
        persistKeptReadMessages();
    }

    function pruneKeptReadAgainstUnread(serverUnread) {
        // Keep dimmed cards after the server clears unread.
        // While an id is still in serverUnread, getDisplayMessages() prefers the unread copy.
        persistKeptReadMessages();
    }

    // Helper: Create unique ID for notification
    function getNotificationId(msg) {
        // Create unique ID based on user_id, project_id, message content, and time
        return `${msg.user_id}_${msg.Project_id || '0'}_${msg.time || Date.now()}`;
    }

    // Helper: Check if there are truly new notifications (excluding email notifications)
    function hasNewNotifications(newMessages) {
        if (!hasPolledOnce || isInitialLoad) {
            return false; // Don't beep on first load or first poll
        }
        
        // Filter out email notifications - they should not trigger beep
        const nonEmailMessages = newMessages.filter(msg => !msg.is_email_notification);
        if (nonEmailMessages.length === 0) {
            return false; // Only email notifications, don't beep
        }
        
        const newIds = new Set();
        nonEmailMessages.forEach(msg => {
            newIds.add(getNotificationId(msg));
        });
        
        // Check if any new notification ID is not in the previous set
        for (let id of newIds) {
            if (!lastNotificationIds.has(id)) {
                return true;
            }
        }
        return false;
    }

    // Helper: Update the stored notification IDs
    function updateNotificationIds(messages) {
        lastNotificationIds.clear();
        messages.forEach(msg => {
            lastNotificationIds.add(getNotificationId(msg));
        });
    }

    // Helper: Format time as 'time ago'
    function timeAgo(unixTime) {
        const now = Date.now() / 1000;
        const diff = Math.floor(now - unixTime);
        if (diff < 60) return 'just now';
        if (diff < 3600) return Math.floor(diff / 60) + ' min ago';
        if (diff < 86400) return Math.floor(diff / 3600) + ' hr ago';
        const d = new Date(unixTime * 1000);
        return d.toLocaleDateString() + ' ' + d.toLocaleTimeString([], {hour: '2-digit', minute:'2-digit'});
    }

    // Replace attachment markers with readable notification preview text
    function formatMessagePreview(message) {
        if (!message) return '';
        var text = String(message);
        if (typeof window.chatFormatAttachmentPreview === 'function') {
            text = window.chatFormatAttachmentPreview(text);
        } else {
            var photos = (text.match(/\[photoAttachment-[^\]]+\]/g) || []).length;
            var files = (text.match(/\[fileAttachment-[^\]]+\]/g) || []).length;
            var voices = (text.match(/\[voiceAttachment-[^\]]+\]/g) || []).length;
            text = text.replace(/\[(?:photoAttachment|fileAttachment|voiceAttachment)-[^\]]+\]/g, '');
            var imgRepeat = text.match(/(?:Sent you an image)+/);
            if (imgRepeat) {
                photos = Math.max(photos, imgRepeat[0].split('Sent you an image').length - 1);
                text = text.replace(/(?:Sent you an image)+/, '');
            }
            var parts = [];
            if (photos === 1) parts.push('Sent you an image');
            else if (photos > 1) parts.push('Sent you ' + photos + ' images');
            if (files === 1) parts.push('Sent you a file');
            else if (files > 1) parts.push('Sent you ' + files + ' files');
            if (voices === 1) parts.push('Sent you an audio message');
            else if (voices > 1) parts.push('Sent you ' + voices + ' audio messages');
            var caption = text.replace(/\s+/g, ' ').trim();
            text = parts.length ? (caption ? parts.join(' · ') + ' — ' + caption : parts.join(' · ')) : caption;
        }
        if (typeof window.chatStripWhatsAppMarkers === 'function') {
            text = window.chatStripWhatsAppMarkers(text);
        }
        text = text.replace(/```[\s\S]*?```/g, function (m) { return m.replace(/```/g, ''); });
        text = text.replace(/`([^`]+)`/g, '$1');
        text = text.replace(/\*\*([^*]+)\*\*/g, '$1');
        text = text.replace(/\*([^*\n]+)\*/g, '$1');
        text = text.replace(/_([^_\n]+)_/g, '$1');
        text = text.replace(/~([^~\n]+)~/g, '$1');
        text = text.replace(/^>\s+/gm, '');
        return text.replace(/\s+/g, ' ').trim();
    }

    var showAllNotifications = false;

    /** Sort by `time` (unix) descending — chat, discussion, task, group, email all use `time`. */
    function sortUnreadMessagesNewestFirst(arr) {
        if (!arr || !arr.length) return [];
        return arr.slice().sort(function(a, b) {
            var ta = parseInt(a.time, 10) || 0;
            var tb = parseInt(b.time, 10) || 0;
            if (tb !== ta) return tb - ta;
            var ia = String(a.id != null ? a.id : '');
            var ib = String(b.id != null ? b.id : '');
            return ib.localeCompare(ia);
        });
    }

    // Helper: Render the unread + kept-read messages dropdown
    function headerMessageSkeletonHtml() {
        var row = function () {
            return '<div class="header-dd-skel-row">' +
                '<div class="reports-skel skel header-dd-skel-avatar"></div>' +
                '<div class="header-dd-skel-col">' +
                '<div class="reports-skel skel header-dd-skel-line header-dd-skel-line--lg"></div>' +
                '<div class="reports-skel skel header-dd-skel-line header-dd-skel-line--md"></div>' +
                '<div class="reports-skel skel header-dd-skel-line header-dd-skel-line--sm"></div>' +
                '</div></div>';
        };
        return '<li class="header-dropdown-skeleton header-dd-skel" role="status" aria-label="Loading messages" aria-hidden="true">' +
            row() + row() + row() + row() + row() +
            '</li>';
    }

    function showMessageSkeleton() {
        const ul = document.querySelector('.msg-envelope .unread-scrolls ul, .msg-envelope .unread-scroll ul, .unread-scroll ul, .unread-scrolls ul');
        if (!ul) return;
        ul.setAttribute('aria-busy', 'true');
        ul.innerHTML = headerMessageSkeletonHtml();
    }

    function updateMessageDropdownPosition() {
        // Static CSS placement — Popper update caused side→center jump on mobile.
        return;
    }

    function scheduleMessageDropdownReposition() {
        return;
    }

    function renderDropdown() {
        const ul = document.querySelector('.msg-envelope .unread-scrolls ul, .msg-envelope .unread-scroll ul, .unread-scroll ul, .unread-scrolls ul');
        if (!ul) return;
        ul.setAttribute('aria-busy', 'false');
        const displayMessages = getDisplayMessages();
        if (!displayMessages.length) {
            ul.innerHTML = '<div class="no-notifications">No unread Messages</div>';
            updateEnvelopeBadge();
            scheduleMessageDropdownReposition();
            return;
        }
            const messages = sortUnreadMessagesNewestFirst(displayMessages);
            const maxToShow = 8;
            let visibleMessages = showAllNotifications ? messages : messages.slice(0, maxToShow);
            ul.innerHTML = visibleMessages.map((msg, idx) => {
                const isRead = isMsgRead(msg);
                // Check if this is an email notification
                let isEmailNotification = msg.is_email_notification === true;
                
                // Determine type and target URL
                // Email notification: has is_email_notification flag
                // Group chat: has is_group_chat flag and group_id
                // Task chat: has is_task_chat flag and task_id
                // Project discussion: Project_id is not 0 and not is_task_chat and not is_group_chat
                // Internal chat: Project_id is 0 and not is_task_chat and not is_group_chat
                let isGroupChat = msg.is_group_chat === true && msg.group_id;
                let isTaskChat = msg.is_task_chat === true && msg.task_id;
                let isDiscussion = msg.Project_id && msg.Project_id !== '0' && !isTaskChat && !isGroupChat && !isEmailNotification;
                let url = '';
                
                if (isEmailNotification) {
                    // Email notification: redirect to inbox
                    url = window.baseUrl + 'mail/inbox?account=' + msg.email_account_id + '&folder=inbox';
                } else if (isGroupChat) {
                    url = window.baseUrl + 'chatting?group=' + msg.group_id;
                } else if (isTaskChat) {
                    url = 'TASK_CHAT_' + msg.task_id;
                } else if (isDiscussion) {
                    url = window.baseUrl + 'discussion?project_id=' + msg.Project_id;
                } else {
                    url = window.baseUrl + 'chatting?user=' + msg.user_id;
                }
                
                // Handle grouped messages vs individual messages vs email notifications
                let sender, imgSrc, badgeHtml;
                
                if (isEmailNotification) {
                    // Email notification: show envelope icon
                    sender = 'New email received';
                    imgSrc = `<svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.2" stroke="#2196F3" width="40" height="40">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M21.75 6.75v10.5a2.25 2.25 0 0 1-2.25 2.25h-15a2.25 2.25 0 0 1-2.25-2.25V6.75m19.5 0A2.25 2.25 0 0 0 19.5 4.5h-15a2.25 2.25 0 0 0-2.25 2.25m19.5 0v.243a2.25 2.25 0 0 1-1.07 1.916l-7.5 4.615a2.25 2.25 0 0 1-2.36 0L3.32 8.91a2.25 2.25 0 0 1-1.07-1.916V6.75"></path>
                    </svg>`;
                    badgeHtml = msg.message_count && msg.message_count > 1 ? `<div class="message-counter">${msg.message_count}</div>` : '';
                } else if (msg.is_group) {
                    // Group message - show group icon with counter
                    sender = msg.sender_name || 'Group';
                    imgSrc = `<svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="group-icon">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M18 18.72a9.094 9.094 0 0 0 3.741-.479 3 3 0 0 0-4.682-2.72m.94 3.198.001.031c0 .225-.012.447-.037.666A11.944 11.944 0 0 1 12 21c-2.17 0-4.207-.576-5.963-1.584A6.062 6.062 0 0 1 6 18.719m12 0a5.971 5.971 0 0 0-.941-3.197m0 0A5.995 5.995 0 0 0 12 12.75a5.995 5.995 0 0 0-5.058 2.772m0 0a3 3 0 0 0-4.681 2.72 8.986 8.986 0 0 0 3.74.477m.94-3.197a5.971 5.971 0 0 0-.94 3.197M15 6.75a3 3 0 1 1-6 0 3 3 0 0 1 6 0Zm6 3a2.25 2.25 0 1 1-4.5 0 2.25 2.25 0 0 1 4.5 0Zm-13.5 0a2.25 2.25 0 1 1-4.5 0 2.25 2.25 0 0 1 4.5 0Z" />
                    </svg>`;
                    badgeHtml = msg.message_count && msg.message_count > 1 ? `<div class="message-counter">${msg.message_count}</div>` : '';
                } else {
                    // Individual message or grouped user message - show user avatar
                    let status = msg.online_status === 'online' ? 'online' : 'offline';
                    let indicator = `<span class="${status}-indicator" title="${status.charAt(0).toUpperCase() + status.slice(1)}"></span>`;
                    sender = (msg.sender_name ? msg.sender_name : (msg.user_id ? 'User #' + msg.user_id : 'Unknown'));
                    // Use profile image if available, otherwise use placeholder
                    imgSrc = (msg.profile_img && msg.profile_img !== '') ? msg.profile_img : (window.baseUrl + 'assets/images/upload-img.jpg');
                    
                    // Add counter badge for grouped user messages (direct chat)
                    if (msg.is_grouped_user && msg.message_count > 1) {
                        badgeHtml = `<div class="message-counter">${msg.message_count}</div>`;
                    } else {
                        badgeHtml = '';
                    }
                    
                    // Add online/offline indicator to badgeHtml (will be placed in image area)
                    badgeHtml += indicator;
                }
                
                let time = msg.time ? timeAgo(msg.time) : '';
                
                // For email notifications, show account email instead of message text
                let messageText, preview;
                if (isEmailNotification) {
                    // Use account email from project_title
                    messageText = msg.project_title || 'Email Account';
                    preview = messageText.length > 35 ? messageText.substring(0, 35) + '...' : messageText;
                } else {
                    messageText = formatMessagePreview(msg.message || '');
                    preview = messageText.length > 35 ? messageText.substring(0, 35) + '...' : messageText;
                }
                
                // Show project title for discussions, task info for task chats, or group name for group chats
                let project = '';
                if (isGroupChat) {
                    // For group chat, show the group name (truncate to 40 chars)
                    let groupName = msg.group_name ? msg.group_name : 'Group Chat';
                    groupName = groupName.length > 35 ? groupName.substring(0, 35) + '...' : groupName;
                    project = `<div class="prottl">${groupName}</div>`;
                } else if (isDiscussion) {
                    // For discussion, show project title (truncate to 40 chars)
                    let projectTitle = msg.project_title ? msg.project_title : 'Project';
                    projectTitle = projectTitle.length > 35 ? projectTitle.substring(0, 35) + '...' : projectTitle;
                    project = `<div class="prottl">Title: ${projectTitle}</div>`;
                } else if (isTaskChat) {
                    // For task chat, show the task info (which includes task title and project name) (truncate to 40 chars)
                    let taskTitle = msg.project_title ? msg.project_title : 'Task';
                    taskTitle = taskTitle.length > 35 ? taskTitle.substring(0, 35) + '...' : taskTitle;
                    project = `<div class="prottl">${taskTitle}</div>`;
                }
                
                // Show message counter badge only for unread cards
                if (isRead) {
                    badgeHtml = (badgeHtml || '').replace(/<div class="message-counter">[\s\S]*?<\/div>/g, '');
                }

                var isChatReaction = !!msg.is_chat_reaction;
                var notifIdAttr = '';
                if (isEmailNotification || isChatReaction) {
                    notifIdAttr = String(msg.notification_id || msg.id || '');
                }
                return `
                <li class="notif-item${isRead ? ' notif-item-read' : ''}" data-item-key="${getItemKey(msg)}" data-url="${url}" data-is-email="${isEmailNotification ? '1' : '0'}" data-is-chat-reaction="${isChatReaction ? '1' : '0'}" data-email-account-id="${isEmailNotification ? msg.email_account_id : ''}" data-notification-id="${notifIdAttr}">
				  <div class="notif-wrap">
                    <div class="mbox-img ${isEmailNotification ? 'email-avatar' : (msg.is_group ? 'group-avatar' : (msg.is_grouped_user ? 'user-avatar-with-counter' : ''))}">
                        ${isEmailNotification ? imgSrc : (msg.is_group ? imgSrc : `<img src="${imgSrc}" class="rounded-circle" alt="User">`)}
                        ${badgeHtml}
                    </div>
                    <div class="mbox-txt">
                        <div class="dfex">
                            <div class="mbox-txt-name">${sender}</div>
                            <div class="mbox-info-time">${time}</div>
                        </div>
                        ${project}
                        <div class="mbox-txt-msg">${preview}</div>
                    </div>
				</div>
                </li>
                `;
            }).join('');
            // Add Load More button if needed
            if (!showAllNotifications && messages.length > maxToShow) {
                // Must be <li> inside <ul> — a raw <div> breaks the DOM and Bootstrap dropdown
                // (data-bs-auto-close="outside") treats the click as "outside" and closes the menu.
                ul.innerHTML += '<li class="notification-load-more-wrap" role="presentation">' +
                    '<button type="button" class="notif-load-more secondary-btn-a">Load More Messages</button></li>';
                var loadMoreBtn = ul.querySelector('.notif-load-more');
                if (loadMoreBtn) {
                    loadMoreBtn.addEventListener('click', function(e) {
                        e.preventDefault();
                        e.stopPropagation();
                        e.stopImmediatePropagation();
                        showAllNotifications = true;
                        renderDropdown();
                    });
                }
            }
            // Attach click handler
            ul.querySelectorAll('.notif-item').forEach(item => {
                item.addEventListener('click', function(e) {
                    e.stopPropagation();
                    const url = this.getAttribute('data-url');
                    const isEmail = this.getAttribute('data-is-email') === '1';
                    const isChatReactionClick = this.getAttribute('data-is-chat-reaction') === '1';
                    const notificationId = this.getAttribute('data-notification-id');
                    const itemKey = this.getAttribute('data-item-key');

                    // Mark card as read locally and keep it visible (dimmed)
                    var displayList = getDisplayMessages();
                    var matched = displayList.find(function(m) { return getItemKey(m) === itemKey; });
                    if (matched) {
                        keepMessageAsRead(matched);
                        renderDropdown();
                        updateEnvelopeBadge();
                    }

                    // Mark email / reaction notification as read on server
                    if ((isEmail || isChatReactionClick) && notificationId) {
                        fetch(window.baseUrl + 'ajax/unread-counter.php', {
                            method: 'POST',
                            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                            body: 'mark_notification_read=1&notification_id=' + encodeURIComponent(notificationId)
                        })
                        .then(res => {
                            const contentType = res.headers.get('content-type');
                            if (!contentType || !contentType.includes('application/json')) {
                                return res.text().then(text => {
                                    console.error('[MESSAGE NOTIFICATION] Mark read - Non-JSON response:', text.substring(0, 200));
                                    return {success: false};
                                });
                            }
                            return res.json();
                        })
                        .catch(error => {
                            console.error('[MESSAGE NOTIFICATION] Error marking notification as read:', error);
                        });
                    }

                    // Check if this is a task chat notification
                    if (url && url.startsWith('TASK_CHAT_')) {
                        const taskId = url.replace('TASK_CHAT_', '');

                        sessionStorage.setItem('navigatedFromNotification', '1');

                        // Use the same logic as notifications.js to open task sidebar
                        setTimeout(function() {
                            const sidebar = document.getElementById('task-sidebar');
                            const hasSidebar = sidebar !== null;

                            if (typeof openTaskSidebar === 'function' && hasSidebar) {
                                openTaskSidebar(taskId);
                            } else if (typeof window.openTaskSidebarDirect === 'function' && hasSidebar) {
                                window.openTaskSidebarDirect(taskId);
                            } else {
                                var rolePrefix = '';
                                if (typeof window.accountStatus !== 'undefined') {
                                    if (window.accountStatus == 1) rolePrefix = 'admin/';
                                    else if (window.accountStatus == 3) rolePrefix = 'staff/';
                                    else if (window.accountStatus == 2) rolePrefix = 'client/';
                                } else if (window.notificationRolePrefix) {
                                    rolePrefix = String(window.notificationRolePrefix).replace(/\/?$/, '/');
                                    if (rolePrefix === '/') rolePrefix = '';
                                }
                                window.location.href = window.baseUrl + rolePrefix + 'kanban?internal=1';
                            }
                        }, 200);
                    } else if (url) {
                        sessionStorage.setItem('navigatedFromNotification', '1');
                        window.location.href = url;
                    }
                });
            });
            updateEnvelopeBadge();
            scheduleMessageDropdownReposition();
    }

    function scheduleNextPoll() {
        clearTimeout(pollTimer);
        pollTimer = setTimeout(fetchUnreadMessagesAndUpdate, intervals[intervalIndex]);
    }

    function fetchUnreadMessagesAndUpdate() {
        if (isInitialLoad) {
            var ul = document.querySelector('.msg-envelope .unread-scrolls ul, .msg-envelope .unread-scroll ul, .unread-scroll ul, .unread-scrolls ul');
            if (ul && !ul.querySelector('.header-dropdown-skeleton') && !getDisplayMessages().length) {
                showMessageSkeleton();
            }
        }
        fetch(window.baseUrl + 'ajax/unread-counter.php')
            .then(response => {
                return response.text().then(text => {
                    const trimmed = (text || '').trim();
                    if (!response.ok || !trimmed) {
                        console.warn('[Message Notification] unread-counter HTTP', response.status, trimmed.substring(0, 200));
                        return [];
                    }
                    const contentType = response.headers.get('content-type') || '';
                    if (!contentType.includes('application/json') && trimmed.charAt(0) !== '[' && trimmed.charAt(0) !== '{') {
                        console.error('[Message Notification] Non-JSON response from unread-counter.php:', trimmed.substring(0, 500));
                        return [];
                    }
                    try {
                        const data = JSON.parse(trimmed);
                        return Array.isArray(data) ? data : [];
                    } catch (e) {
                        console.error('[Message Notification] Invalid JSON from unread-counter.php:', trimmed.substring(0, 500));
                        return [];
                    }
                });
            })
            .then(data => {
                var newMessages = Array.isArray(data) ? data : [];
                
                
                // Check for truly new notifications
                var shouldPlayBeep = false;
                var hasNewUnread = hasPolledOnce && !isInitialLoad && hasNewNotifications(newMessages);
                if (
                    !window.suppressNotificationSound &&
                    hasPolledOnce &&
                    !suppressBeepOnLoad &&
                    !isInitialLoad &&
                    (!window.isChatOrDiscussionPage) &&
                    hasNewUnread
                ) {
                    shouldPlayBeep = true;
                    intervalIndex = 0; // Reset to fastest interval
                } else {
                    if (intervalIndex < intervals.length - 1) {
                        intervalIndex++;
                    }
                }

                // Background-tab title blink (works even on chat pages when tab is hidden)
                if (hasNewUnread && window.chatTabNotify && typeof window.chatTabNotify.notify === 'function') {
                    var newest = null;
                    for (var i = 0; i < newMessages.length; i++) {
                        var m = newMessages[i];
                        if (!m || m.is_email_notification) continue;
                        if (!newest || (parseInt(m.time, 10) || 0) > (parseInt(newest.time, 10) || 0)) {
                            newest = m;
                        }
                    }
                    if (newest) {
                        window.chatTabNotify.notify(newest.sender_name || newest.name || 'Someone');
                    }
                }
                
                // Play beep if needed
                if (shouldPlayBeep) {
                    audio.play().catch((error) => {
                        // Audio play error - silent fail
                    });
                }
                
                // Update state: server unread + merge with kept-read cards
                hasPolledOnce = true;
                suppressBeepOnLoad = false;
                isInitialLoad = false;
                window.unreadMessages = newMessages;
                pruneKeptReadAgainstUnread(newMessages);
                updateNotificationIds(newMessages);
                showAllNotifications = false;
                renderDropdown();
                updateEnvelopeBadge();
                scheduleNextPoll();
            })
            .catch((error) => {
                console.error('[Message Notification] Error fetching messages:', error);
                // On error, use max interval
                intervalIndex = intervals.length - 1;
                scheduleNextPoll();
            });
    }

    window.markMessagesAsReadForCurrentContext = function(userId, projectId) {
        var list = getDisplayMessages();
        list.forEach(function(msg) {
            if (msg.is_email_notification || msg.is_chat_reaction || msg.is_group_chat || msg.is_task_chat) return;
            var isDiscussion = msg.Project_id && msg.Project_id !== '0' && msg.Project_id !== 0;
            var matches = false;
            if (!isDiscussion) {
                matches = String(msg.user_id) === String(userId);
            } else {
                matches = String(msg.Project_id) === String(projectId);
            }
            if (matches) {
                keepMessageAsRead(msg);
            }
        });
        // Drop matching entries from server-unread snapshot so badge updates immediately
        window.unreadMessages = (window.unreadMessages || []).filter(function(msg) {
            if (msg.is_email_notification || msg.is_chat_reaction || msg.is_group_chat || msg.is_task_chat) return true;
            var isDiscussion = msg.Project_id && msg.Project_id !== '0' && msg.Project_id !== 0;
            if (!isDiscussion) {
                return String(msg.user_id) !== String(userId);
            }
            return String(msg.Project_id) !== String(projectId);
        });
        renderDropdown();
        updateEnvelopeBadge();
    };

    document.addEventListener('DOMContentLoaded', function() {
        // Initialize notification IDs with current messages
        if (window.unreadMessages && window.unreadMessages.length > 0) {
            updateNotificationIds(window.unreadMessages);
        }

        // Prefer SSR JSON immediately; otherwise keep skeleton until first poll
        if (getDisplayMessages().length > 0) {
            renderDropdown();
            isInitialLoad = false;
        } else {
            showMessageSkeleton();
        }
        // Inject styles if not already present
        if (!document.getElementById('message-notification-styles')) {
            const style = document.createElement('style');
            style.id = 'message-notification-styles';
            style.textContent = `
                .notification-header {
                    display: flex;
                    justify-content: space-between;
                    align-items: center;
                    padding: 15px 20px;
                    border-bottom: 1px solid #e9ecef;
                }
                
                .notification-item {
                    padding: 15px 20px;
                    border-bottom: 1px solid #f8f9fa;
                    cursor: pointer;
                    transition: background-color 0.2s ease;
                }
                
                .notification-item:hover {
                    background-color: #f8f9fa;
                }
                
                .notification-item.unread {
                    background-color: #f0f8ff;
                }
                
                .notification-avatar {
                    width: 40px;
                    height: 40px;
                    border-radius: 50%;
                    margin-right: 12px;
                }
                
                .notification-content {
                    flex: 1;
                    min-width: 0;
                }
                
                .notification-title {
                    font-weight: 600;
                    color: #333;
                    margin-bottom: 4px;
                    white-space: nowrap;
                    overflow: hidden;
                    text-overflow: ellipsis;
                }
                
                .notification-message {
                    color: #666;
                    font-size: 13px;
                    line-height: 1.4;
                    margin-bottom: 4px;
                }
                
                .notification-time {
                    color: #999;
                    font-size: 12px;
                }
                
                .no-notifications {
                    padding: 40px 20px;
                    text-align: center;
                    color: #999;
                    font-style: italic;
                }

                .notif-item-read {
                    opacity: 0.55;
                }

                .notif-item-read .mbox-txt-name {
                    font-weight: 400;
                }

                .notif-item-read .message-counter {
                    display: none;
                }
                
                .notification-loading {
                    padding: 20px;
                    text-align: center;
                    color: #666;
                }
            `;
            document.head.appendChild(style);
        }

        // Open envelope before first poll finishes → keep shimmer visible
        document.querySelectorAll('.msg-envelope').forEach(function (el) {
            function clearPopperInline() {
                var menu = el.querySelector('.dropdown-menu.msg-menu');
                if (!menu) return;
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
            el.addEventListener('show.bs.dropdown', function () {
                if (isInitialLoad && !getDisplayMessages().length) {
                    showMessageSkeleton();
                }
                clearPopperInline();
            });
            el.addEventListener('shown.bs.dropdown', clearPopperInline);
        });
        
        if (typeof window.comonAfterPageQuiet === 'function') {
            window.comonAfterPageQuiet(function () {
                fetchUnreadMessagesAndUpdate();
            }, 2500);
        } else {
            window.addEventListener('load', function () {
                setTimeout(fetchUnreadMessagesAndUpdate, 2500);
            });
        }

        // Mark all as read button logic - only for messages (envelope icon)
        var markAllBtn = document.querySelector('.mark-all-messages-read');
        if (markAllBtn) {
            markAllBtn.addEventListener('click', function(e) {
                e.stopPropagation(); // Prevent triggering notification handler
                fetch(window.baseUrl + 'ajax/unread-counter.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: 'mark_all_read=1'
                })
                .then(response => {
                    // Check if response is actually JSON
                    const contentType = response.headers.get('content-type');
                    if (!contentType || !contentType.includes('application/json')) {
                        return response.text().then(text => {
                            console.error('[MESSAGE NOTIFICATION] Mark all read - Non-JSON response:', text.substring(0, 200));
                            throw new Error('Invalid JSON response');
                        });
                    }
                    return response.json();
                })
                .then(data => {
                    if (data && data.success) {
                        // Keep all cards visible but dimmed as read
                        getDisplayMessages().forEach(function(msg) {
                            keepMessageAsRead(msg);
                        });
                        window.unreadMessages = [];
                        updateNotificationIds([]);
                        renderDropdown();
                        updateEnvelopeBadge();
                    } else {
                        console.error('[MESSAGE NOTIFICATION] Mark all read failed:', data);
                    }
                })
                .catch(error => {
                    console.error('[MESSAGE NOTIFICATION] Error marking all as read:', error);
                });
            });
        }
    });
    
    // Function to open task sidebar directly (from notifications.js)
    window.openTaskSidebarDirect = function(taskId) {
        const sidebar = document.getElementById('task-sidebar');
        const loading = document.getElementById('task-loading');
        const content = document.getElementById('task-content');
        const error = document.getElementById('task-error');
        
        if (!sidebar || !loading || !content || !error) {
            console.error('Task sidebar elements not found');
            return;
        }
        
        // Show the sidebar
        sidebar.classList.add('open');
        
        // Show loading state
        loading.style.display = 'block';
        content.style.display = 'none';
        error.style.display = 'none';
        
        // Fetch task details
        const baseUrl = typeof window.baseUrl !== 'undefined' ? window.baseUrl : '';
        fetch(baseUrl + 'includes/task_details.php?id=' + taskId)
            .then(response => response.text())
            .then(text => {
                try {
                    const data = JSON.parse(text);
                    
                    if (data.status === 'ok' || data.task) {
                        // Update sidebar content
                        const titleElem = document.getElementById('formatted-task-title');
                        const descElem = document.getElementById('task-description');
                        const startDateElem = document.getElementById('task-start-date');
                        const dueDateElem = document.getElementById('task-due-date');
                        
                        if (titleElem) titleElem.textContent = data.task?.title || 'Task Details';
                        if (descElem) descElem.innerHTML = data.task?.description || 'No description provided';
                        
                        if (data.task?.start_date && startDateElem) {
                            startDateElem.textContent = data.task.start_date;
                        }
                        
                        if (data.task?.due_date && dueDateElem) {
                            dueDateElem.textContent = data.task.due_date;
                        }
                        
                        // Update task creator
                        if (data.creator) {
                            const creatorElem = document.getElementById('task-creator');
                            if (creatorElem) {
                                creatorElem.innerHTML = '';
                                const avatarWrapper = document.createElement('div');
                                avatarWrapper.className = 'avatar-wrapper';
                                
                                const img = document.createElement('img');
                                img.src = data.creator.image;
                                img.alt = data.creator.name;
                                img.title = data.creator.name;
                                img.className = 'avatar';
                                
                                const name = document.createElement('span');
                                name.className = 'avatar-name';
                                name.textContent = data.creator.name;
                                
                                avatarWrapper.appendChild(img);
                                avatarWrapper.appendChild(name);
                                creatorElem.appendChild(avatarWrapper);
                            }
                        }
                        
                        // Update assigned staff
                        if (data.assigned_staff && data.assigned_staff.length > 0) {
                            const assignedElem = document.getElementById('task-assigned-by');
                            if (assignedElem) {
                                assignedElem.innerHTML = '';
                                
                                data.assigned_staff.forEach((staff, index) => {
                                    if (index < 4) {
                                        if (staff.image) {
                                            const img = document.createElement('img');
                                            img.src = staff.image;
                                            img.alt = staff.name;
                                            img.title = staff.name;
                                            img.className = 'avatar';
                                            assignedElem.appendChild(img);
                                        } else {
                                            const initials = document.createElement('div');
                                            initials.className = 'avatar-circle';
                                            initials.textContent = staff.initials || 'U';
                                            assignedElem.appendChild(initials);
                                        }
                                    }
                                });
                                
                                if (data.assigned_staff.length > 4) {
                                    const more = document.createElement('div');
                                    more.className = 'more-avatars';
                                    more.textContent = '+' + (data.assigned_staff.length - 4) + ' more';
                                    assignedElem.appendChild(more);
                                }
                            }
                        }
                        
                        // Display task content
                        loading.style.display = 'none';
                        content.style.display = 'block';
                    } else {
                        loading.style.display = 'none';
                        error.style.display = 'block';
                        const errorMsg = document.getElementById('error-message');
                        if (errorMsg) errorMsg.textContent = data.error || 'Error loading task details';
                    }
                } catch (parseError) {
                    loading.style.display = 'none';
                    error.style.display = 'block';
                    const errorMsg = document.getElementById('error-message');
                    if (errorMsg) errorMsg.textContent = 'Error parsing server response';
                }
            })
            .catch(error => {
                loading.style.display = 'none';
                error.style.display = 'block';
                const errorMsg = document.getElementById('error-message');
                if (errorMsg) errorMsg.textContent = 'Network error occurred';
            });
    };
})(); 