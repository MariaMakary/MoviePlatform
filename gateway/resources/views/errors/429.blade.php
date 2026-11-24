@extends('layouts.app', ['title' => 'Too many attempts'])

@section('content')
    <x-error-page code="429" title="Too many attempts" message="Wait a minute, then try again." />
@endsection
