<?php

use App\Http\Controllers\Auth\LogoutController;
use App\Livewire\Auth\Login;
use App\Livewire\Auth\Register;
use App\Livewire\Catalog\MoviePage;
use App\Livewire\Dev\ServiceStatus;
use App\Livewire\Engagement\History;
use App\Livewire\Engagement\Watchlist;
use App\Livewire\Home;
use App\Livewire\Intelligence\CinemaMap;
use App\Livewire\Notifications\Index as Notifications;
use Illuminate\Support\Facades\Route;

Route::get('/', Home::class)->name('home');

Route::get('/movies/{id}', MoviePage::class)->whereUuid('id')->name('movies.show');

Route::get('/cinemas',CinemaMap::class)->name('cinemas');

Route::get('/notifications', Notifications::class)->middleware('auth.jwt')->name('notifications');

Route::get('/watchlist', Watchlist::class)->middleware('auth.jwt')->name('watchlist');
Route::get('/history', History::class)->middleware('auth.jwt')->name('history');

Route::get('/login', Login::class)->name('login');
Route::get('/register', Register::class)->name('register');
Route::post('/logout', LogoutController::class)->name('logout');

Route::get('/dev/components', function () {
    abort_unless(app()->isLocal(), 404);

    return view('dev.components', ['title' => 'Components']);
})->name('dev.components');

Route::get('/dev/services', ServiceStatus::class)->name('dev.services');
