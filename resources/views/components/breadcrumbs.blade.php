@props(['items' => []])
{{-- Breadcrumbs (spec §6.2). Items: [['label' => ..., 'url' => ...|null], ...] --}}
<nav aria-label="breadcrumb" {{ $attributes->merge(['class' => 'text-sm text-muted']) }}>
    <ol class="flex flex-wrap items-center gap-1.5">
        <li>
            <a href="{{ url('/') }}" wire:navigate class="font-semibold text-link hover:underline">{{ __('store.home') }}</a>
        </li>
        @foreach($items as $item)
            <li aria-hidden="true" class="rtl:-scale-x-100">›</li>
            <li>
                @if(!empty($item['url']))
                    <a href="{{ $item['url'] }}" wire:navigate class="font-semibold text-link hover:underline">{{ $item['label'] }}</a>
                @else
                    <span aria-current="page" class="font-semibold text-body">{{ $item['label'] }}</span>
                @endif
            </li>
        @endforeach
    </ol>
</nav>
