@extends('layouts.app')

@section('content')
{{-- Local-only UI kit (spec 11.11). No real store/customer data appears here. --}}
<section class="mx-auto max-w-7xl px-4 py-8" x-data="{
    level: document.documentElement.dataset.motion || 'full',
    diag: { level: '-', reducedMotion: false, ambientActive: 0 },
    setLevel(l) { this.level = l; document.documentElement.dataset.motion = l; },
    refreshDiag() { if (window.motionDiagnostics) { this.diag = window.motionDiagnostics(); } }
}" x-init="setInterval(() => refreshDiag(), 600); refreshDiag()">
    <h1 class="text-2xl font-extrabold text-ink">UI Kit — Electric Motion (local only)</h1>

    <div class="mt-4 flex flex-wrap items-center gap-2" role="group" aria-label="{{ __('store.motion') }}">
        <span class="font-bold">{{ __('store.motion') }}:</span>
        @foreach(['full', 'lite', 'off'] as $lvl)
            <button type="button" class="min-h-11 rounded-lg border px-4 font-semibold"
                :class="level === '{{ $lvl }}' ? 'bg-primary text-ink border-primary' : 'bg-surface text-body'"
                x-on:click="setLevel('{{ $lvl }}')">{{ __('store.motion_'.$lvl) }}</button>
        @endforeach
    </div>

    <div class="mt-4 rounded-xl border bg-surface p-4 text-sm" data-diagnostics>
        <p>Active level: <strong x-text="diag.level"></strong></p>
        <p>prefers-reduced-motion: <strong x-text="diag.reducedMotion ? 'yes' : 'no'"></strong></p>
        <p>Active ambient loops: <strong x-text="diag.ambientActive"></strong> (max 3)</p>
        <p>Server-rendered level: <strong>{{ setting('motion_level', 'full') }}</strong> — the browser may only downgrade it.</p>
    </div>

    <h2 class="mt-8 text-xl font-bold text-ink">Design tokens</h2>
    <div class="mt-3 grid grid-cols-2 gap-3 sm:grid-cols-4">
        @foreach(['primary' => '#FFC107', 'primary-600' => '#E0A800', 'ink' => '#111827', 'body' => '#1F2937', 'muted' => '#6B7280', 'surface' => '#FFFFFF', 'canvas' => '#F5F6F8', 'success' => '#16A34A', 'danger' => '#DC2626', 'link' => '#1D4ED8', 'wire' => '#CBD5E1', 'spark' => '#FFE066'] as $token => $hex)
            <div class="rounded-xl border bg-surface p-3">
                <div class="h-10 rounded-lg border" style="background: {{ $hex }}"></div>
                <p class="mt-2 text-sm font-semibold" dir="ltr">--color-{{ $token }}</p>
                <p class="text-xs text-muted" dir="ltr">{{ $hex }}</p>
            </div>
        @endforeach
    </div>

    <h2 class="mt-8 text-xl font-bold text-ink">E01–E16 components</h2>
    <div class="mt-3 grid gap-4 md:grid-cols-2">
        <div class="rounded-xl border bg-surface p-4"><p class="mb-2 font-bold" dir="ltr">E01 wire</p><x-electric.wire :pulse="true" /></div>
        <div class="rounded-xl border bg-ink p-4"><p class="mb-2 font-bold text-white" dir="ltr">E02 circuit</p><x-electric.circuit variant="banner" :pulses="3" class="h-20" /></div>
        <div class="rounded-xl border bg-surface p-4"><p class="mb-2 font-bold" dir="ltr">E03 bulb</p>
            <x-electric.bulb :on="false" /><x-electric.bulb :on="true" :flicker="true" /></div>
        <div class="rounded-xl border bg-surface p-4"><p class="mb-2 font-bold" dir="ltr">E04 plug-socket</p>
            <x-electric.plug-socket state="apart" :interactive="true" /></div>
        <div class="rounded-xl border bg-surface p-4"><p class="mb-2 font-bold" dir="ltr">E05 switch</p>
            <x-electric.switch :checked="false" label="متوفر فقط" /></div>
        <div class="rounded-xl border bg-surface p-4"><p class="mb-2 font-bold" dir="ltr">E06 bolt-loader</p>
            <x-electric.bolt-loader :size="24" /><x-electric.bolt-loader :size="40" /></div>
        <div class="rounded-xl border bg-surface p-4"><p class="mb-2 font-bold" dir="ltr">E08 section-title</p>
            <x-section-title title="قسم تجريبي" href="#" /></div>
        <div class="rounded-xl border bg-surface p-4"><p class="mb-2 font-bold" dir="ltr">E09 / E10 badges</p>
            <x-sale-badge :percent="20" /> <x-stock-badge state="in" /> <x-stock-badge state="low" /> <x-stock-badge state="out" /></div>
        <div class="rounded-xl border bg-surface p-4"><p class="mb-2 font-bold" dir="ltr">E11 free-shipping-bar</p>
            <div x-data="{ v: 40 }">
                <input type="range" min="0" max="100" x-model.number="v" class="w-full" aria-label="Demo progress">
                <template x-if="true"><div></div></template>
                <div :style="`--p:${v}`">
                    <x-free-shipping-bar progress="0.4" remaining="1,800 ج.م" />
                </div>
                <p class="text-xs text-muted">Demo slider value: <span x-text="v"></span>% (static demo bar above at 40%)</p>
            </div></div>
        <div class="rounded-xl border bg-surface p-4"><p class="mb-2 font-bold" dir="ltr">E13 btn / E14 cart-icon</p>
            <x-btn variant="primary">زر أساسي</x-btn>
            <x-btn variant="secondary">ثانوي</x-btn>
            <x-btn variant="outline">محدد</x-btn>
            <x-btn variant="whatsapp">واتساب</x-btn>
            <x-btn variant="primary" :loading="true">جاري</x-btn>
            <x-cart-icon :count="3" /></div>
        <div class="rounded-xl border bg-surface p-4"><p class="mb-2 font-bold" dir="ltr">E15 navigate progress</p>
            <p class="text-sm text-muted">Livewire <span dir="ltr">wire:navigate</span> progress bar is styled in app.css (yellow wire + spark head). Visible during SPA navigations.</p></div>
        <div class="rounded-xl border bg-surface p-4"><p class="mb-2 font-bold" dir="ltr">E07 fx.spark</p>
            <button type="button" class="min-h-11 rounded-lg bg-primary px-4 font-bold text-ink"
                x-on:click="window.fx && window.fx.spark($el)">Spark</button></div>
    </div>

    <h2 class="mt-8 text-xl font-bold text-ink">E16 scenes</h2>
    <div class="mt-3 grid gap-4 md:grid-cols-3">
        @foreach(['404', '500', 'empty-cart', 'empty-search', 'empty-wishlist', 'success'] as $scene)
            <div class="rounded-xl border bg-surface p-4">
                <p class="mb-2 font-bold" dir="ltr">{{ $scene }}</p>
                <x-electric.scene :name="$scene"><p class="text-sm text-muted">نص المشهد يظهر هنا</p></x-electric.scene>
            </div>
        @endforeach
    </div>

    <h2 class="mt-8 text-xl font-bold text-ink">E12 tool icons</h2>
    <div class="mt-3 grid grid-cols-3 gap-3 sm:grid-cols-6">
        @foreach(config('icons') as $key => $label)
            <div class="group rounded-xl border bg-surface p-3 text-center">
                <x-electric.tool-icon :name="$key" :size="36" />
                <p class="mt-2 text-xs font-semibold">{{ $label }}</p>
                <p class="text-[11px] text-muted" dir="ltr">{{ $key }}</p>
            </div>
        @endforeach
    </div>
</section>
@endsection
