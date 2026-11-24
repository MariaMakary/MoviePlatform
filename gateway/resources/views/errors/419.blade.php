@extends('layouts.app', ['title' => 'Page expired'])

@section('content')
    <x-error-page code="419" title="This page expired" message="You were away for a while. Go back, reload the page and try again.">
        <x-button :href="url()->previous()" variant="ghost">Go back</x-button>
    </x-error-page>
@endsection
