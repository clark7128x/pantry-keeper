const summary = document.querySelector('.error-summary');
if (summary) summary.focus();

const editor = document.querySelector('.editor form');
if (editor) {
    let dirty = false;
    editor.addEventListener('input', () => { dirty = true; });
    editor.addEventListener('submit', () => { dirty = false; });
    window.addEventListener('beforeunload', event => {
        if (dirty) { event.preventDefault(); event.returnValue = ''; }
    });
}

const pushButton = document.querySelector('#enable-push');
if (pushButton) {
    pushButton.addEventListener('click', async () => {
        const message = document.querySelector('#push-message');
        message.textContent = '';
        if (!window.isSecureContext || !('serviceWorker' in navigator) || !('PushManager' in window) || !('Notification' in window)) {
            message.textContent = pushButton.dataset.unsupported;
            return;
        }
        pushButton.disabled = true;
        try {
            const permission = await Notification.requestPermission();
            if (permission !== 'granted') {
                message.textContent = pushButton.dataset.denied;
                return;
            }
            await navigator.serviceWorker.register('/sw.js');
            const registration = await navigator.serviceWorker.ready;
            let subscription = await registration.pushManager.getSubscription();
            if (!subscription) {
                const key = pushButton.dataset.key;
                const bytes = Uint8Array.from(atob(key.replace(/-/g, '+').replace(/_/g, '/')), char => char.charCodeAt(0));
                subscription = await registration.pushManager.subscribe({userVisibleOnly: true, applicationServerKey: bytes});
            }
            const response = await fetch('/push.php', {
                method: 'POST',
                headers: {'Content-Type': 'application/json', 'X-CSRF-Token': document.querySelector('meta[name="csrf-token"]').content},
                body: JSON.stringify(subscription),
                signal: AbortSignal.timeout(15000)
            });
            const result = await response.json();
            if (!response.ok || !result.ok) throw new Error('Subscription was not saved');
            message.textContent = pushButton.dataset.success;
            // Reload adds the server-rendered opt-out control, including for users without JS later.
            window.location.assign('/');
        } catch {
            message.textContent = pushButton.dataset.error;
        } finally {
            pushButton.disabled = false;
        }
    });
}
