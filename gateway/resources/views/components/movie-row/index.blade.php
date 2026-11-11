@props(['label', 'more' => null])

<div {{ $attributes->class('relative') }}
     x-data="{
         atStart: true,
         atEnd: false,
         init() {
             this.$nextTick(() => this.update());
             new MutationObserver(() => this.update()).observe(this.$refs.track, { childList: true });
         },
         update() {
             const track = this.$refs.track;
             this.atStart = track.scrollLeft <= 4;
             this.atEnd = track.scrollLeft + track.clientWidth >= track.scrollWidth - 4;
         },
         page(direction) {
             this.$refs.track.scrollBy({ left: direction * this.$refs.track.clientWidth * 0.85, behavior: 'smooth' });
         },
     }"
     x-on:resize.window.debounce.200ms="update()">
    <button type="button" x-show="! atStart" x-cloak x-on:click="page(-1)" aria-label="Scroll {{ $label }} left"
            class="absolute top-[38%] left-0 z-10 hidden h-10 w-10 -translate-x-1/3 -translate-y-1/2 items-center justify-center rounded-full bg-jet/90 text-white shadow-lg ring-1 ring-white/15 transition hover:bg-cerulean md:flex">
        <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M15.75 19.5 8.25 12l7.5-7.5"/></svg>
    </button>

    <div x-ref="track" x-on:scroll.throttle.100ms="update()"
         role="list" aria-label="{{ $label }}"
         class="flex gap-4 overflow-x-auto overscroll-x-contain pb-1 [scrollbar-width:none] [&::-webkit-scrollbar]:hidden">
        {{ $slot }}

        @if ($more)
            <div role="listitem" x-intersect="$wire.{{ $more }}" class="w-36 shrink-0 sm:w-40 lg:w-44" aria-label="Loading more movies">
                <div class="aspect-2/3 animate-pulse rounded-xl bg-white/5 ring-1 ring-white/10"></div>
                <div class="mt-2 h-4 w-3/4 animate-pulse rounded bg-white/5"></div>
            </div>
        @endif
    </div>

    <button type="button" x-show="! atEnd" x-cloak x-on:click="page(1)" aria-label="Scroll {{ $label }} right"
            class="absolute top-[38%] right-0 z-10 hidden h-10 w-10 translate-x-1/3 -translate-y-1/2 items-center justify-center rounded-full bg-jet/90 text-white shadow-lg ring-1 ring-white/15 transition hover:bg-cerulean md:flex">
        <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="m8.25 4.5 7.5 7.5-7.5 7.5"/></svg>
    </button>
</div>
