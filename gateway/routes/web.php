<?php

use App\Http\Controllers\Auth\LogoutController;
use App\Livewire\Auth\ForgotPassword;
use App\Livewire\Auth\Login;
use App\Livewire\Auth\Profile;
use App\Livewire\Auth\Register;
use App\Livewire\Auth\ResetPassword;
use App\Livewire\Catalog\MoviePage;
use App\Livewire\Catalog\Search;
use App\Livewire\Dev\ServiceStatus;
use App\Livewire\Engagement\History;
use App\Livewire\Engagement\Watchlist;
use App\Livewire\Home;
use App\Livewire\Intelligence\CinemaMap;
use App\Livewire\Notifications\Index as Notifications;
use App\Livewire\Social\FindPeople;
use App\Livewire\Social\FollowList;
use Illuminate\Support\Facades\Route;

Route::get('/', Home::class)->name('home');

Route::get('/movies/{id}', MoviePage::class)->whereUuid('id')->name('movies.show');

Route::get('/search', Search::class)->name('search');

Route::get('/cinemas',CinemaMap::class)->name('cinemas');

Route::get('/users/{id}/followers', FollowList::class)->whereUuid('id')->name('users.followers');
Route::get('/users/{id}/following', FollowList::class)->whereUuid('id')->name('users.following');
Route::get('/people', FindPeople::class)->middleware('auth.jwt')->name('people');

Route::get('/notifications', Notifications::class)->middleware('auth.jwt')->name('notifications');

Route::get('/profile', Profile::class)->middleware('auth.jwt')->name('profile');

Route::get('/watchlist', Watchlist::class)->middleware('auth.jwt')->name('watchlist');
Route::get('/history', History::class)->middleware('auth.jwt')->name('history');

Route::get('/login', Login::class)->name('login');
Route::get('/register', Register::class)->name('register');
Route::get('/forgot-password', ForgotPassword::class)->name('password.request');
// The link in Identity's reset email points here (GATEWAY_URL/reset-password?token=…&email=…).
Route::get('/reset-password', ResetPassword::class)->name('password.reset');
Route::post('/logout', LogoutController::class)->name('logout');

Route::get('/dev/components', function () {
    abort_unless(app()->isLocal(), 404);

    return view('dev.components', ['title' => 'Components']);
})->name('dev.components');

Route::get('/dev/services', ServiceStatus::class)->name('dev.services');
