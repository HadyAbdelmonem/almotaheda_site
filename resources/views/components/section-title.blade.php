@props(['title', 'href' => null, 'linkText' => null])
{{-- E08: section title with one-time zap flicker and wire underline (spec 11.6). --}}
<div {{ $attributes->merge(['class' => 'mb-5 flex items-end justify-between gap-4']) }} data-reveal>
    <h2 class="text-[22px] font-extrabold leading-[1.35] text-ink md:text-[28px]">
        <span class="inline-flex items-center gap-2">
            <x-icons.zap :size="22" class="text-primary-600" />
            {{ $title }}
        </span>
        <span class="section-wire mt-2 block h-0.5 origin-right bg-wire" aria-hidden="true"></span>
    </h2>
    @if($href)
        <a href="{{ $href }}" wire:navigate class="shrink-0 text-sm font-semibold text-link">{{ $linkText ?? __('store.view_all') }}</a>
    @endif
</div>
