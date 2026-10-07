import axios from 'axios';
import { ref } from 'vue';

const VAPID_PUBLIC_KEY = import.meta.env.VITE_VAPID_PUBLIC_KEY ?? '';

function urlBase64ToUint8Array(base64String: string): Uint8Array<ArrayBuffer> {
    const padding = '='.repeat((4 - (base64String.length % 4)) % 4);
    const base64 = (base64String + padding).replace(/-/g, '+').replace(/_/g, '/');
    const rawData = atob(base64);
    return Uint8Array.from([...rawData].map((c) => c.charCodeAt(0)));
}

export function usePushNotifications() {
    const supported = 'serviceWorker' in navigator && 'PushManager' in window;
    const subscribed = ref(false);
    const loading = ref(false);

    async function getRegistration(): Promise<ServiceWorkerRegistration | null> {
        if (!supported) return null;
        return navigator.serviceWorker.ready;
    }

    async function checkSubscription() {
        const reg = await getRegistration();
        if (!reg) return;
        const sub = await reg.pushManager.getSubscription();
        subscribed.value = !!sub;
    }

    async function subscribe() {
        if (!supported || !VAPID_PUBLIC_KEY) return;
        loading.value = true;
        try {
            const reg = await getRegistration();
            if (!reg) return;

            const sub = await reg.pushManager.subscribe({
                userVisibleOnly: true,
                applicationServerKey: urlBase64ToUint8Array(VAPID_PUBLIC_KEY),
            });

            const json = sub.toJSON();
            await axios.post('/push/subscribe', {
                endpoint: json.endpoint,
                public_key: json.keys?.p256dh ?? null,
                auth_token: json.keys?.auth ?? null,
            });

            subscribed.value = true;
        } catch (err) {
            console.error('[push] subscribe failed:', err);
        } finally {
            loading.value = false;
        }
    }

    async function unsubscribe() {
        const reg = await getRegistration();
        if (!reg) return;
        loading.value = true;
        try {
            const sub = await reg.pushManager.getSubscription();
            if (sub) {
                await axios.delete('/push/subscribe', { data: { endpoint: sub.endpoint } });
                await sub.unsubscribe();
                subscribed.value = false;
            }
        } catch (err) {
            console.error('[push] unsubscribe failed:', err);
        } finally {
            loading.value = false;
        }
    }

    async function requestAndSubscribe() {
        const permission = await Notification.requestPermission();
        if (permission === 'granted') await subscribe();
    }

    return { supported, subscribed, loading, checkSubscription, requestAndSubscribe, unsubscribe };
}
