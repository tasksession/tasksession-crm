// Service Worker for Web Push Notifications

const CACHE_NAME = 'web-push-notifications-v44';
const PUSH_ASSET_CACHE_BUST = '44';

function swDebug() {}

function normalizePushPayload(data) {
    if (!data || typeof data !== 'object') {
        return null;
    }
    if (data.title || data.body) {
        return data;
    }
    if (data.notification && typeof data.notification === 'object') {
        return {
            title: data.notification.title || '',
            body: data.notification.body || '',
            icon: data.notification.icon || data.icon || null,
            badge: data.notification.badge || data.badge || null,
            tag: data.tag || null,
            renotify: data.renotify,
            data: data.data || {}
        };
    }
    return data;
}

function isUsablePayload(data) {
    if (!data || typeof data !== 'object') {
        return false;
    }
    var title = (data.title || '').trim();
    var body = (data.body || '').trim();
    if (!title && !body) {
        return false;
    }
    if (title === 'New Message' && (body === 'You have a new message' || body === '')) {
        return false;
    }
    return true;
}

function fetchPushNotificationFromServer(pushTag) {
    return self.registration.pushManager.getSubscription().then(function (subscription) {
        var fullUrl = self.location.origin + '/real-chat/push_notification_data.php';
        var qs = [];
        if (subscription && subscription.endpoint) {
            qs.push('endpoint=' + encodeURIComponent(subscription.endpoint));
        }
        if (pushTag) {
            qs.push('tag=' + encodeURIComponent(pushTag));
        }
        if (qs.length) {
            fullUrl += '?' + qs.join('&');
        }
        return fetch(fullUrl, {
            method: 'GET',
            credentials: 'include',
            cache: 'no-cache',
            headers: {
                'Accept': 'application/json',
                'X-Requested-With': 'XMLHttpRequest'
            }
        }).then(function (response) {
            return response.text().then(function (text) {
                try {
                    return JSON.parse(text);
                } catch (e) {
                    throw new Error('Invalid JSON from push_notification_data');
                }
            });
        });
    });
}

function parsePushEventData(event) {
    if (!event.data) {
        return Promise.resolve(null);
    }
    return event.data.json().catch(function () {
        return event.data.text().then(function (text) {
            if (!text) {
                return null;
            }
            try {
                return JSON.parse(text);
            } catch (e) {
                return null;
            }
        });
    }).catch(function () {
        return null;
    });
}

function buildNotificationOptions(data, withMedia) {
    var iconUrl = data.icon || null;
    var badgeUrl = data.badge || null;
    var assetBust = PUSH_ASSET_CACHE_BUST;
    if (!iconUrl) {
        iconUrl = self.location.origin + '/assets/images/push-notification-icon.png?v=' + assetBust;
    }
    if (!badgeUrl) {
        badgeUrl = self.location.origin + '/assets/images/push-notification-badge.png?v=' + assetBust;
    }

    var notificationTag = data.tag || 'message-notification';
    var renotify = data.renotify !== undefined ? data.renotify : false;
    if (notificationTag.indexOf('chat-') === 0) {
        renotify = true;
    }

    var options = {
        body: data.body || 'You have a new message',
        tag: notificationTag,
        renotify: renotify,
        requireInteraction: false,
        silent: false,
        timestamp: Date.now(),
        data: data.data || {}
    };

    if (withMedia) {
        if (iconUrl) {
            options.icon = iconUrl;
        }
        if (badgeUrl) {
            options.badge = badgeUrl;
        }
    }

    return options;
}

function showNotification(data) {
    var notificationTitle = data.title || 'New Message';

    return self.registration.showNotification(
        notificationTitle,
        buildNotificationOptions(data, true)
    ).catch(function (err) {
        swDebug('show_notification_media_error', { message: err && err.message ? err.message : String(err) });
        return self.registration.showNotification(
            notificationTitle,
            buildNotificationOptions(data, false)
        );
    }).catch(function (err) {
        swDebug('show_notification_error', { message: err && err.message ? err.message : String(err) });
        return self.registration.showNotification(notificationTitle, {
            body: data.body || 'You have a new message',
            tag: data.tag || 'message-notification',
            data: data.data || {}
        });
    });
}

function handlePushEvent(event) {
    swDebug('push_event_received', { hasData: !!event.data });
    return parsePushEventData(event).then(function (parsed) {
        swDebug('push_event_parsed', { parsed: parsed ? { title: parsed.title, body: parsed.body, tag: parsed.tag } : null });

        var normalizedParsed = isUsablePayload(parsed) ? normalizePushPayload(parsed) : null;

        if (normalizedParsed) {
            swDebug('push_show_from_payload', { tag: normalizedParsed.tag });
            return showNotification(normalizedParsed);
        }

        // Empty-body wake (Firefox): fetch cached title/body like Chrome/FCM path.
        var pushTag = parsed && parsed.tag ? parsed.tag : (parsed && parsed.data && parsed.data._push_tag ? parsed.data._push_tag : null);
        return fetchPushNotificationFromServer(pushTag).then(function (serverData) {
            swDebug('push_data_server', {
                status: serverData ? serverData.status : null,
                hasData: !!(serverData && serverData.data),
                title: serverData && serverData.data ? serverData.data.title : null
            });
            if (serverData && serverData.status === 'success' && serverData.data) {
                return showNotification(normalizePushPayload(serverData.data));
            }
            return showNotification({
                title: 'New Message',
                body: 'You have a new message',
                tag: 'message-notification',
                renotify: true,
                data: {}
            });
        }).catch(function (err) {
            swDebug('push_data_fetch_error', { message: err && err.message ? err.message : String(err) });
            return showNotification({
                title: 'New Message',
                body: 'You have a new message',
                tag: 'message-notification',
                renotify: true,
                data: {}
            });
        });
    });
}

self.addEventListener('install', function (event) {
    self.skipWaiting();
});

self.addEventListener('activate', function (event) {
    event.waitUntil(self.clients.claim());
});

self.addEventListener('push', function (event) {
    event.waitUntil(handlePushEvent(event));
});

self.addEventListener('notificationclick', function (event) {
    event.notification.close();

    if (event.action === 'close') {
        return;
    }

    var urlToOpen = '/index.php';
    if (event.notification.data && event.notification.data.url) {
        urlToOpen = event.notification.data.url;
    }

    if (urlToOpen.indexOf('http://') !== 0 && urlToOpen.indexOf('https://') !== 0) {
        if (urlToOpen.charAt(0) !== '/') {
            urlToOpen = '/' + urlToOpen;
        }
        urlToOpen = self.location.origin + urlToOpen;
    }

    event.waitUntil(
        clients.matchAll({
            type: 'window',
            includeUncontrolled: true
        }).then(function (clientList) {
            for (var i = 0; i < clientList.length; i++) {
                var client = clientList[i];
                try {
                    var clientUrl = new URL(client.url);
                    var targetUrl = new URL(urlToOpen);
                    if (clientUrl.origin === targetUrl.origin && 'focus' in client) {
                        client.focus();
                        if ('navigate' in client) {
                            return client.navigate(urlToOpen);
                        }
                        return client.focus();
                    }
                } catch (e) {
                    // continue
                }
            }
            if (clients.openWindow) {
                return clients.openWindow(urlToOpen);
            }
        })
    );
});

self.addEventListener('sync', function (event) {
    if (event.tag === 'sync-subscriptions') {
        event.waitUntil(Promise.resolve());
    }
});
