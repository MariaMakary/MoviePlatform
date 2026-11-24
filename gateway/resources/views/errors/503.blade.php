@extends('layouts.app', ['title' => 'Unavailable'])

@section('content')
    @php($service = $exception instanceof \App\Exceptions\ServiceUnavailable ? $exception->service : null)
    <x-error-page code="503" title="Temporarily unavailable"
        :message="$service ? 'The '.$service.' service isn\'t responding right now. Please try again in a moment.' : 'This part of the site isn\'t responding right now. Please try again in a moment.'">
        <x-button href="javascript:location.reload()" variant="ghost">Try again</x-button>
    </x-error-page>
@endsection
