@props(['blocks' => null])
{{-- "مميزات الشراء مننا" (spec §4.1 why_us_blocks): icon keys come from the
     config/icons.php whitelist; blocks are owner-editable in settings. --}}
@php $items = $blocks ?? setting('why_us_blocks', []); @endphp
@if(!empty($items))
    <section {{ $attributes->merge(['class' => '']) }} aria-label="{{ __('store.why_us') }}">
        <x-section-title :title="__('store.why_us')" />
        <div class="grid grid-cols-2 gap-3 lg:grid-cols-4">
            @foreach($items as $block)
                @php $iconKey = array_key_exists($block['icon'] ?? '', config('icons')) ? $block['icon'] : 'badge-check'; @endphp
                <div class="rounded-xl bg-surface p-4 shadow-sm" data-reveal>
                    <x-electric.tool-icon :name="$iconKey" animate="reveal" :size="36" class="text-primary-600" />
                    <h3 class="mt-2 font-bold text-ink">{{ $block['title'] ?? '' }}</h3>
                    <p class="mt-1 text-sm text-muted">{{ $block['text'] ?? '' }}</p>
                </div>
            @endforeach
        </div>
    </section>
@endif
