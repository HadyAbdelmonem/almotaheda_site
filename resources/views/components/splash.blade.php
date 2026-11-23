{{--
    Loading splash (OWNER OVERRIDE, 2026-11-23): spec §11.2 rule 9 and §12
    originally forbade splash screens / preloaders; the store owner explicitly
    requested this screen, so it is built as a lightweight overlay only:
    - rendered once in the storefront layout on full page loads,
    - hidden by resources/js/motion/splash.js as soon as `load` fires
      (hard cap 2.5s, immediate on DOMContentLoaded at motion level off),
    - never traps content: it is display:none without JS (html.js gate),
      and a CSS animation fades it away at 2.6s even if the JS hide fails.
    Decorative only: aria-hidden, no focusable elements, pointer events are
    dropped the moment hiding starts.
--}}
<div id="splash" class="splash" data-splash aria-hidden="true">
    <div class="splash-inner">
        <div class="splash-logo-wrap">
            <span class="splash-glow"></span>
            <span class="splash-ring"></span>
            <img src="{{ store_logo_url() }}" alt="" width="96" height="96" class="splash-logo" fetchpriority="high">
        </div>
        <p class="splash-name">{{ setting('store_name', __('store.name')) }}</p>
        {{-- Wire with a traveling current pulse (E01 mechanics): the pulse
             path runs only in full motion via x-live + .wire-pulse, and the
             svg carries .ambient so lite/off safety nets stop it. --}}
        <svg class="splash-wire ambient" x-live width="200" height="12" viewBox="0 0 200 12" fill="none" aria-hidden="true" focusable="false">
            <path d="M200 6 L0 6" stroke="var(--color-wire-dark)" stroke-width="2" stroke-linecap="round"/>
            <path class="wire-pulse" pathLength="600" d="M200 6 L0 6" stroke="var(--color-primary)" stroke-width="2" stroke-linecap="round"/>
            <circle cx="100" cy="6" r="3" fill="var(--color-spark)"/>
        </svg>
    </div>
</div>
