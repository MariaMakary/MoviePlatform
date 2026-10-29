<?php

use App\Livewire\Dev\ServiceStatus;
use App\Livewire\Home;
use Illuminate\Support\Facades\Route;

Route::get('/', Home::class)->name('home');

Route::get('/dev/components', function () {
    abort_unless(app()->isLocal(), 404);

    return view('dev.components', ['title' => 'Components']);
})->name('dev.components');

Route::get('/dev/services', ServiceStatus::class)->name('dev.services');
