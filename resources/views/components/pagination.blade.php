@props(['paginator'])
{{-- RTL pagination (spec §10.5) for Livewire listings: page changes go
     through the WithPagination trait so the #[Url] filter/sort state in
     the query string is preserved exactly. --}}
@if($paginator->lastPage() > 1)
    <nav {{ $attributes->merge(['class' => 'mt-8 flex items-center justify-center gap-1.5']) }} aria-label="pagination">
        @if($paginator->onFirstPage())
            <span class="inline-flex min-h-11 min-w-11 items-center justify-center rounded-lg px-3 font-bold text-muted/50">{{ __('store.previous') }}</span>
        @else
            <button type="button" wire:click="previousPage" class="inline-flex min-h-11 min-w-11 items-center justify-center rounded-lg bg-surface px-3 font-bold shadow-sm hover:bg-canvas">{{ __('store.previous') }}</button>
        @endif

        @foreach(range(max(1, $paginator->currentPage() - 2), min($paginator->lastPage(), $paginator->currentPage() + 2)) as $page)
            @if($page == $paginator->currentPage())
                <span aria-current="page" class="inline-flex min-h-11 min-w-11 items-center justify-center rounded-lg bg-primary px-3 font-extrabold tabular-nums text-ink">{{ $page }}</span>
            @else
                <button type="button" wire:click="gotoPage({{ $page }})" class="inline-flex min-h-11 min-w-11 items-center justify-center rounded-lg bg-surface px-3 font-bold tabular-nums shadow-sm hover:bg-canvas">{{ $page }}</button>
            @endif
        @endforeach

        @if($paginator->hasMorePages())
            <button type="button" wire:click="nextPage" class="inline-flex min-h-11 min-w-11 items-center justify-center rounded-lg bg-surface px-3 font-bold shadow-sm hover:bg-canvas">{{ __('store.next') }}</button>
        @else
            <span class="inline-flex min-h-11 min-w-11 items-center justify-center rounded-lg px-3 font-bold text-muted/50">{{ __('store.next') }}</span>
        @endif
    </nav>
@endif
