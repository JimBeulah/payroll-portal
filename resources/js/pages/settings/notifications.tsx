import { Head, usePage } from '@inertiajs/react';
import { useEffect, useState } from 'react';
import { toast } from 'sonner';
import Heading from '@/components/heading';
import { Button } from '@/components/ui/button';
import { getCsrfToken } from '@/lib/csrf';
import { edit as editNotifications } from '@/routes/notifications';
import { destroy as destroySubscription, store as storeSubscription } from '@/routes/push-subscriptions';

function urlBase64ToUint8Array(base64String: string): Uint8Array {
    const padding = '='.repeat((4 - (base64String.length % 4)) % 4);
    const base64 = (base64String + padding).replace(/-/g, '+').replace(/_/g, '/');
    const rawData = window.atob(base64);
    const outputArray = new Uint8Array(rawData.length);

    for (let i = 0; i < rawData.length; i++) {
        outputArray[i] = rawData.charCodeAt(i);
    }

    return outputArray;
}

export default function Notifications() {
    const { props } = usePage<{ vapidPublicKey: string | null; hasPushSubscription: boolean }>();
    const [supported, setSupported] = useState(true);
    const [subscribed, setSubscribed] = useState(false);
    const [busy, setBusy] = useState(false);

    useEffect(() => {
        if (!('serviceWorker' in navigator) || !('PushManager' in window)) {
            setSupported(false);

            return;
        }

        navigator.serviceWorker.ready
            .then((registration) => registration.pushManager.getSubscription())
            .then((subscription) => setSubscribed(subscription !== null && props.hasPushSubscription));
    }, [props.hasPushSubscription]);

    async function enable() {
        if (!props.vapidPublicKey) {
            toast.error('Push notifications are not configured for this server.');

            return;
        }

        setBusy(true);

        try {
            const permission = await Notification.requestPermission();

            if (permission !== 'granted') {
                toast.error('Notification permission was denied.');

                return;
            }

            const registration = await navigator.serviceWorker.ready;
            const subscription = await registration.pushManager.subscribe({
                userVisibleOnly: true,
                applicationServerKey: urlBase64ToUint8Array(props.vapidPublicKey),
            });

            const response = await fetch(storeSubscription().url, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-XSRF-TOKEN': getCsrfToken(),
                },
                credentials: 'same-origin',
                body: JSON.stringify(subscription.toJSON()),
            });

            if (!response.ok) {
                await subscription.unsubscribe();

                throw new Error('Failed to save push subscription on the server.');
            }

            setSubscribed(true);
            toast.success('Push notifications enabled.');
        } catch {
            toast.error('Could not enable push notifications. Please try again.');
        } finally {
            setBusy(false);
        }
    }

    async function disable() {
        setBusy(true);

        try {
            const registration = await navigator.serviceWorker.ready;
            const subscription = await registration.pushManager.getSubscription();

            if (subscription) {
                const response = await fetch(destroySubscription().url, {
                    method: 'DELETE',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-XSRF-TOKEN': getCsrfToken(),
                    },
                    credentials: 'same-origin',
                    body: JSON.stringify({ endpoint: subscription.endpoint }),
                });

                if (!response.ok) {
                    throw new Error('Failed to remove push subscription on the server.');
                }

                await subscription.unsubscribe();
            }

            setSubscribed(false);
            toast.success('Push notifications disabled.');
        } catch {
            toast.error('Could not disable push notifications. Please try again.');
        } finally {
            setBusy(false);
        }
    }

    return (
        <>
            <Head title="Notification settings" />

            <h1 className="sr-only">Notification settings</h1>

            <div className="space-y-6">
                <Heading
                    variant="small"
                    title="Notification settings"
                    description="Get a push notification on this device for new requests and review decisions"
                />

                {!supported && (
                    <p className="text-muted-foreground text-sm">
                        Push notifications aren't supported in this browser.
                    </p>
                )}

                {supported && (
                    <Button
                        disabled={busy}
                        onClick={subscribed ? disable : enable}
                    >
                        {subscribed ? 'Disable push notifications' : 'Enable push notifications'}
                    </Button>
                )}
            </div>
        </>
    );
}

Notifications.layout = {
    breadcrumbs: [
        {
            title: 'Notification settings',
            href: editNotifications(),
        },
    ],
};
