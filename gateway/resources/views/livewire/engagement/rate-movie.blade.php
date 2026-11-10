@php
    $average = $summary['average'] ?? null;
    $count = $summary['count'] ?? 0;
    $myScore = $summary['my_score'] ?? null;
@endphp

<div class="inline-flex flex-col items-start gap-1">
    @if ($this->isUnavailable('rating'))
        <x-service-unavailable :service="$unavailable['rating']" :retry="true" compact />
    @else
        <div class="inline-flex flex-wrap items-center gap-x-5 gap-y-2 rounded-lg bg-white/5 px-3 py-2 text-sm ring-1 ring-white/10">
            {{-- Average of every user --}}
            <span class="inline-flex items-center gap-2">
                @if ($count > 0)
                    <x-rating-stars :value="$average" :max="$maxScore" size="sm" />
                    <span class="font-semibold text-white">{{ number_format($average, 1) }}</span>
                    <span class="text-gray-400">({{ $count }} {{ Str::plural('rating', $count) }})</span>
                @else
                    <span class="text-gray-400">No ratings yet</span>
                @endif
            </span>

            @if ($loggedIn)
                {{-- The key changes with the score, so Alpine starts again from the saved score after each rating --}}
                <span wire:key="my-score-{{ $myScore ?? 0 }}" x-data="{ hover: 0, score: {{ $myScore ?? 0 }} }"
                      class="inline-flex items-center gap-2" role="group" aria-label="Your rating">
                    <span class="text-gray-400">{{ $myScore ? 'Your rating' : 'Rate it' }}</span>
                    <span class="inline-flex" @mouseleave="hover = 0">
                        @for ($i = 1; $i <= $maxScore; $i++)
                            <button type="button"
                                    wire:click="rate({{ $i }})" @click="score = {{ $i }}" wire:loading.attr="disabled"
                                    @mouseenter="hover = {{ $i }}" @focus="hover = {{ $i }}" @blur="hover = 0"
                                    aria-label="Rate {{ $i }} out of {{ $maxScore }}" aria-pressed="{{ $myScore === $i ? 'true' : 'false' }}"
                                    class="rounded p-0.5 transition focus-visible:ring-2 focus-visible:ring-cerulean focus-visible:outline-none disabled:opacity-50"
                                    :class="(hover || score) >= {{ $i }} ? 'text-neon-rose' : 'text-white/25'">
                                <x-icons.star class="h-5 w-5" />
                            </button>
                        @endfor
                    </span>
                </span>
            @else
                <button type="button" wire:click="logIn" class="font-semibold text-cerulean hover:underline">Log in to rate</button>
            @endif
        </div>
    @endif

    @if ($error)
        <p role="alert" class="text-xs text-neon-rose">{{ $error }}</p>
    @endif
</div>
