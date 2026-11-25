@extends('layouts.app', ['title' => 'Page not found'])

@section('content')
    <x-error-page code="404" title="Page not found"
        :message="$exception instanceof \App\Exceptions\ServiceRequestException ? $exception->getMessage() : 'This page does not exist, or it was moved or deleted.'">
        <x-button :href="route('search')" variant="ghost">Search movies</x-button>
    </x-error-page>
@endsection
