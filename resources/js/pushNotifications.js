// Helpers Web Push (PWA) — abonnement / désabonnement côté navigateur.

function urlBase64ToUint8Array(base64String) {
    const padding = '='.repeat((4 - (base64String.length % 4)) % 4)
    const base64 = (base64String + padding).replace(/-/g, '+').replace(/_/g, '/')
    const raw = window.atob(base64)
    const output = new Uint8Array(raw.length)
    for (let i = 0; i < raw.length; ++i) output[i] = raw.charCodeAt(i)
    return output
}

export function pushSupported() {
    return 'serviceWorker' in navigator && 'PushManager' in window && 'Notification' in window
}

export async function isSubscribed() {
    if (!pushSupported()) return false
    const reg = await navigator.serviceWorker.getRegistration()
    if (!reg) return false
    const sub = await reg.pushManager.getSubscription()
    return !!sub
}

function getCsrfToken() {
    return document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || ''
}

export async function subscribeToPush(vapidPublicKey) {
    if (!pushSupported()) throw new Error('Notifications non supportées par ce navigateur.')
    if (!vapidPublicKey) throw new Error('Clé VAPID manquante côté serveur.')

    const permission = await Notification.requestPermission()
    if (permission !== 'granted') throw new Error('Permission refusée.')

    const reg = await navigator.serviceWorker.ready
    const subscription = await reg.pushManager.subscribe({
        userVisibleOnly: true,
        applicationServerKey: urlBase64ToUint8Array(vapidPublicKey),
    })

    const res = await fetch('/push-subscriptions', {
        method: 'POST',
        credentials: 'same-origin',
        headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': getCsrfToken(), 'Accept': 'application/json' },
        body: JSON.stringify(subscription.toJSON()),
    })
    if (!res.ok) {
        const hint = res.status === 404 ? ' (route introuvable — videz le cache de routes)'
            : res.status === 419 ? ' (session expirée — rechargez la page)'
            : res.status === 401 ? ' (non connecté)'
            : ''
        throw new Error(`Enregistrement de l'abonnement échoué (${res.status})${hint}.`)
    }
    return true
}

export async function unsubscribeFromPush() {
    if (!pushSupported()) return
    const reg = await navigator.serviceWorker.getRegistration()
    if (!reg) return
    const subscription = await reg.pushManager.getSubscription()
    if (!subscription) return

    await fetch('/push-subscriptions', {
        method: 'DELETE',
        credentials: 'same-origin',
        headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': getCsrfToken(), 'Accept': 'application/json' },
        body: JSON.stringify({ endpoint: subscription.endpoint }),
    })
    await subscription.unsubscribe()
}
