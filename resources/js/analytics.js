// GA4 analytics (spec §9A). Loaded ONLY when the owner configured a
// measurement ID (the layout emits <meta name="ga4-id"> and this module
// is imported conditionally by app.js — when the meta is absent this
// file is never fetched). Payloads are built server-side by
// App\Support\Analytics from an allowlist: no PII, no cost prices, no
// payment data ever reaches this module.
//
// Events arrive two ways:
//  - server-rendered events in <script type="application/json"
//    data-ga-events> (view_item, begin_checkout, purchase) — fired once
//    per element; `purchase` is additionally deduped by transaction_id
//    in sessionStorage so reloads / back-navigation never double-count.
//  - Livewire browser events dispatched AFTER a server action succeeds
//    (ga-add-to-cart on the `cart-added` flow).
// page_view is sent on the initial load and on every
// livewire:navigated (wire:navigate does not reload the page).

const meta = document.querySelector('meta[name="ga4-id"]');
const measurementId = meta ? meta.getAttribute('content') : null;

if (measurementId) {
    const script = document.createElement('script');
    script.async = true;
    script.src = 'https://www.googletagmanager.com/gtag/js?id=' + encodeURIComponent(measurementId);
    document.head.appendChild(script);

    window.dataLayer = window.dataLayer || [];
    window.gtag = window.gtag || function gtag() { window.dataLayer.push(arguments); };
    window.gtag('js', new Date());
    window.gtag('config', measurementId, { send_page_view: false });

    const sendPageView = () => {
        window.gtag('event', 'page_view', {
            page_location: window.location.href,
            page_title: document.title,
        });
    };

    const fireServerEvents = () => {
        document.querySelectorAll('script[data-ga-events]').forEach((el) => {
            if (el.dataset.gaFired) return;
            el.dataset.gaFired = '1';
            let events = [];
            try { events = JSON.parse(el.textContent || '[]'); } catch (e) { return; }
            events.forEach((entry) => {
                if (!entry || !entry.event) return;
                if (entry.event === 'purchase' && entry.params && entry.params.transaction_id) {
                    const key = 'ga-purchase-' + entry.params.transaction_id;
                    try {
                        if (window.sessionStorage.getItem(key)) return;
                        window.sessionStorage.setItem(key, '1');
                    } catch (e) { /* storage unavailable: server flag already dedupes */ }
                }
                window.gtag('event', entry.event, entry.params || {});
            });
        });
    };

    sendPageView();
    fireServerEvents();
    document.addEventListener('livewire:navigated', () => {
        sendPageView();
        fireServerEvents();
    });

    window.addEventListener('ga-add-to-cart', (event) => {
        const payload = (event.detail && (event.detail.payload || event.detail)) || null;
        if (payload && payload.event) {
            window.gtag('event', payload.event, payload.params || {});
        }
    });
}
