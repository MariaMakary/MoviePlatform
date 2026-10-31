<?php

use App\Livewire\Auth\Login;
use App\Livewire\Auth\Register;
use App\Livewire\Dev\ServiceStatus;
use App\Livewire\Home;
use App\Livewire\Intelligence\CinemaMap;
use Illuminate\Support\Facades\Route;

Route::get('/', Home::class)->name('home');

Route::get('/cinemas', CinemaMap::class)->name('cinemas');

Route::get('/login', Login::class)->name('login');
Route::get('/register', Register::class)->name('register');

Route::get('/dev/components', function () {
    abort_unless(app()->isLocal(), 404);

    return view('dev.components', ['title' => 'Components']);
})->name('dev.components');

Route::get('/dev/services', ServiceStatus::class)->name('dev.services');
