@extends('layouts.app', ['title' => 'Not allowed'])

@section('content')
    <x-error-page code="403" title="You can't open this page"
        :message="$exception instanceof \App\Exceptions\ServiceRequestException && $exception->getMessage() !== '' ? $exception->getMessage() : 'Your account does not have access to it.'" />
@endsection
