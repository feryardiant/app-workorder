type PollerNotification = {
    id: string
    title: string
    url: string
}

type PollerResponse = {
    notification: PollerNotification | null
    pending: string[]
    count: number
    text: string
    linkLabel: string
    closeLabel: string
}

type ToastOptions = {
    heading?: string
    text?: string
    variant?: string
    duration?: number
    link?: {
        label: string
        href: string
        navigate?: boolean
    }
}

declare global {
    interface Window {
        Flux?: {
            toast: (options: ToastOptions) => void
        }
        __notificationPollerStarted?: boolean
    }
}

const interval = 5000

const isActive = (): boolean => document.visibilityState === 'visible' && document.hasFocus()

const poller = (): HTMLElement | null => document.querySelector<HTMLElement>('[data-notification-poller]')

export function startNotificationPoller(): void {
    if (window.__notificationPollerStarted) {
        return
    }

    window.__notificationPollerStarted = true

    let timer: ReturnType<typeof setInterval> | null = null
    let inFlight = false

    const check = async (): Promise<void> => {
        const element = poller()
        const url = element?.dataset.url

        if (!element || !url || !isActive() || inFlight) {
            return
        }

        inFlight = true

        try {
            const response = await fetch(url, {
                headers: { Accept: 'application/json' },
                credentials: 'same-origin',
            })

            if (!response.ok || !isActive()) {
                return
            }

            const data = (await response.json()) as PollerResponse

            if (!isActive()) {
                return
            }

            window.dispatchEvent(new CustomEvent('notifications-updated', { detail: { count: data.count } }))

            if (!data.notification || !window.Flux) {
                return
            }

            window.Flux.toast({
                heading: data.notification.title,
                text: data.text,
                variant: 'info',
                duration: 8000,
                link: {
                    label: data.linkLabel,
                    href: data.notification.url,
                    navigate: true,
                },
            })

            const toasts = document.querySelectorAll<HTMLElement>('[data-flux-toast-dialog]')
            toasts[toasts.length - 1]?.querySelector('button')?.setAttribute('aria-label', data.closeLabel)

            const ackUrl = element.dataset.ackUrl
            const csrf = element.dataset.csrf

            if (ackUrl && csrf && data.pending.length > 0) {
                void fetch(ackUrl, {
                    method: 'POST',
                    keepalive: true,
                    headers: {
                        Accept: 'application/json',
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': csrf,
                    },
                    body: JSON.stringify({ ids: data.pending }),
                })
            }
        } catch {
            // Retry on the next interval.
        } finally {
            inFlight = false
        }
    }

    const start = (): void => {
        if (timer || !poller() || !isActive()) {
            return
        }

        void check()
        timer = setInterval(() => void check(), interval)
    }

    const stop = (): void => {
        if (timer) {
            clearInterval(timer)
        }

        timer = null
    }

    document.addEventListener('visibilitychange', () => (isActive() ? start() : stop()))
    window.addEventListener('focus', start)
    window.addEventListener('blur', stop)
    document.addEventListener('livewire:navigated', start)

    start()
}
