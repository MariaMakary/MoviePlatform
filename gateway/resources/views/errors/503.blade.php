@extends('layouts.app', ['title' => 'Unavailable'])

@section('content')
    <div class="mx-auto max-w-2xl py-12">
        <x-service-unavailable :service="$exception instanceof \App\Exceptions\ServiceUnavailable ? $exception->service : null" />

        <div class="mt-6 text-center">
            <x-button :href="route('home')" variant="ghost">Back to home</x-button>
        </div>
    </div>
@endsection
