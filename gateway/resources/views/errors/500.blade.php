<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="h-full">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Something went wrong · {{ config('app.name') }}</title>
    <link rel="icon" type="image/png" href="{{ asset('images/brand/kaia-favicon.png') }}">
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=instrument-sans:400,500,600,700" rel="stylesheet">
    @vite(['resources/css/app.css'])
</head>
<body class="flex min-h-full flex-col bg-jet font-sans text-gray-100 antialiased">
    <header class="border-b border-white/10">
        <div class="mx-auto flex h-16 max-w-7xl items-center px-4 sm:px-6 lg:px-8">
            <x-logo />
        </div>
    </header>

    <main class="mx-auto w-full max-w-7xl flex-1 px-4 py-6 sm:px-6 lg:px-8">
        <x-error-page code="500" title="Something went wrong" message="An error happened on our side. It has been logged; please try again in a moment.">
            <x-button href="javascript:location.reload()" variant="ghost">Try again</x-button>
        </x-error-page>
    </main>
</body>
</html>
