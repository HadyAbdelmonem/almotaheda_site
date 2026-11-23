@props(['order'])
{{-- M64: circuit status timeline. Completed steps are lit nodes joined by a
     lit wire; the current step gets a one-time finite emphasis (animate-pop,
     never an infinite pulse); upcoming steps stay gray. Terminal states
     (cancelled / returned) render as a badge instead of the circuit. --}}
@php
    $steps = [\App\Enums\OrderStatus::New, \App\Enums\OrderStatus::Confirmed, \App\Enums\OrderStatus::Processing, \App\Enums\OrderStatus::Shipped, \App\Enums\OrderStatus::Delivered];
    $currentIndex = array_search($order->status, $steps, true);
@endphp
@if($currentIndex === false)
    <span class="inline-flex items-center gap-2 rounded-full bg-danger/10 px-3 py-1 font-bold text-danger">{{ $order->status->label() }}</span>
@else
    <ol class="flex items-start gap-0" aria-label="{{ __('store.status_timeline') }}">
        @foreach($steps as $i => $step)
            <li class="flex flex-1 items-center gap-0 last:flex-none">
                <div class="flex flex-col items-center gap-1 text-center">
                    <span @class([
                        'block h-4 w-4 rounded-full border-2',
                        'border-primary bg-primary' => $i < $currentIndex,
                        'border-primary bg-spark motion-any:animate-pop' => $i === $currentIndex,
                        'border-wire bg-white' => $i > $currentIndex,
                    ])></span>
                    <span @class(['text-xs font-semibold', 'text-ink' => $i <= $currentIndex, 'text-muted' => $i > $currentIndex])>{{ $step->label() }}</span>
                </div>
                @if(! $loop->last)
                    <span @class(['mx-1 mb-5 h-0.5 flex-1', 'bg-primary' => $i < $currentIndex, 'bg-wire' => $i >= $currentIndex]) aria-hidden="true"></span>
                @endif
            </li>
        @endforeach
    </ol>
@endif
