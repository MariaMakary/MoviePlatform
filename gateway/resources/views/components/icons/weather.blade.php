@props(['condition'])

<svg {{ $attributes }} viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
    @switch($condition)
        @case('clear')
            <circle cx="12" cy="12" r="4" />
            <path d="M12 2v2m0 16v2M4.93 4.93l1.41 1.41m11.32 11.32 1.41 1.41M2 12h2m16 0h2M4.93 19.07l1.41-1.41M17.66 6.34l1.41-1.41" />
            @break
        @case('rain')
            <path d="M7 15a4 4 0 0 1-.6-7.96A5.5 5.5 0 0 1 17 8a3.5 3.5 0 0 1 0 7H7Z" />
            <path d="m8 18-1 3m5-3-1 3m5-3-1 3" />
            @break
        @case('storm')
            <path d="M7 14a4 4 0 0 1-.6-7.96A5.5 5.5 0 0 1 17 7a3.5 3.5 0 0 1 0 7" />
            <path d="m12.5 11-3 5h4l-3 5" />
            @break
        @case('snow')
            <path d="M12 2v20M4.5 6.5l15 11m-15 0 15-11" />
            <path d="m9.5 3.5 2.5 2 2.5-2m-5 17 2.5-2 2.5 2" />
            @break
        @default
            <path d="M7 18a4.5 4.5 0 0 1-.7-8.95A6 6 0 0 1 17.6 9 4.5 4.5 0 0 1 17.5 18H7Z" />
    @endswitch
</svg>
