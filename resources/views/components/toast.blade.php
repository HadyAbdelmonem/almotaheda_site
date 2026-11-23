{{-- M06 toast (spec §11.8): slides in, auto-dismiss 3.5s, pauses on hover,
     announced politely. Listens for the `toast` browser event dispatched
     after a server-confirmed action (never optimistically). An optional
     action (e.g. "عرض السلة") may ride along in the event detail. --}}
<div x-data="{ show: false, message: '', actionLabel: '', actionUrl: '', timer: null }"
     x-on:toast.window="message = $event.detail.message || ''; actionLabel = $event.detail.actionLabel || ''; actionUrl = $event.detail.actionUrl || ''; show = true; clearTimeout(timer); timer = setTimeout(() => show = false, 3500)"
     x-on:mouseenter="clearTimeout(timer)" x-on:mouseleave="timer = setTimeout(() => show = false, 1500)"
     class="pointer-events-none fixed inset-x-4 top-4 z-[70] flex justify-center sm:inset-x-auto sm:bottom-6 sm:top-auto sm:start-6"
     aria-live="polite">
    <div x-show="show" x-cloak
         x-transition:enter="motion-any:transition motion-any:duration-300"
         x-transition:enter-start="opacity-0 -translate-y-2"
         x-transition:enter-end="opacity-100 translate-y-0"
         class="pointer-events-auto flex items-center gap-3 rounded-xl bg-ink px-4 py-3 font-bold text-white shadow-md">
        <x-icons.zap :size="18" class="text-primary" />
        <span x-text="message"></span>
        <a x-show="actionUrl" x-bind:href="actionUrl" class="shrink-0 rounded-lg bg-primary px-3 py-1.5 text-sm font-extrabold text-ink" x-text="actionLabel"></a>
    </div>
</div>
