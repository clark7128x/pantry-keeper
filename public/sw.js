self.addEventListener('push', event => {
    if (!event.data) return;
    let payload;
    try { payload = event.data.json(); } catch { return; }
    event.waitUntil(self.registration.showNotification(payload.title, {
        body: payload.body,
        tag: payload.tag,
        data: {url: '/'}
    }));
});

self.addEventListener('notificationclick', event => {
    event.notification.close();
    event.waitUntil((async () => {
        const windows = await clients.matchAll({type: 'window', includeUncontrolled: true});
        for (const windowClient of windows) {
            if (new URL(windowClient.url).origin === self.location.origin) {
                await windowClient.navigate('/');
                return windowClient.focus();
            }
        }
        return clients.openWindow('/');
    })());
});
