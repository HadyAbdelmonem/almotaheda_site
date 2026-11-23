<div class="mt-6 rounded-xl bg-surface p-5 shadow-sm">
    @guest
        <p class="text-body">{{ __('store.login_to_review') }}
            <a href="{{ route('login') }}" wire:navigate class="font-bold text-link">{{ __('store.login') }}</a>
        </p>
    @else
        @if($notice === 'pending')
            <p class="font-bold text-success">{{ __('store.review_pending') }}</p>
        @elseif($notice === 'exists')
            <p class="font-bold text-danger">{{ __('store.review_already_exists') }}</p>
        @else
            <form wire:submit="submit" class="grid gap-3">
                <h3 class="font-extrabold text-ink">{{ __('store.write_review') }}</h3>
                <div>
                    <label for="rating-{{ $productId }}" class="mb-1 block font-semibold text-ink">{{ __('store.rating') }}</label>
                    <select id="rating-{{ $productId }}" wire:model="rating" class="min-h-11 rounded-lg border border-wire px-3">
                        @for($i = 5; $i >= 1; $i--)<option value="{{ $i }}">{{ $i }} ★</option>@endfor
                    </select>
                    @error('rating') <p class="mt-1 text-sm text-danger">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label for="comment-{{ $productId }}" class="mb-1 block font-semibold text-ink">{{ __('store.review_comment') }}</label>
                    <textarea id="comment-{{ $productId }}" wire:model="comment" rows="3" class="w-full rounded-lg border border-wire px-3 py-2"></textarea>
                    @error('comment') <p class="mt-1 text-sm text-danger">{{ $message }}</p> @enderror
                </div>
                <x-btn type="submit" class="w-fit">{{ __('store.submit_review') }}</x-btn>
            </form>
        @endif
    @endguest
</div>
