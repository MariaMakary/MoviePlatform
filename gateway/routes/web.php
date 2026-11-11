<?php

use App\Http\Controllers\Auth\LogoutController;
use App\Livewire\Admin\Dashboard;
use App\Livewire\Auth\FavoriteGenres;
use App\Livewire\Auth\ForgotPassword;
use App\Livewire\Auth\Login;
use App\Livewire\Auth\Profile;
use App\Livewire\Auth\Register;
use App\Livewire\Auth\ResetPassword;
use App\Livewire\Catalog\GenrePage;
use App\Livewire\Catalog\MoviePage;
use App\Livewire\Catalog\PersonPage;
use App\Livewire\Catalog\Search;
use App\Livewire\Dev\ServiceStatus;
use App\Livewire\Engagement\History;
use App\Livewire\Engagement\ListForm;
use App\Livewire\Engagement\ListPage;
use App\Livewire\Engagement\MyLists;
use App\Livewire\Engagement\Watchlist;
use App\Livewire\Home;
use App\Livewire\Intelligence\CinemaMap;
use App\Livewire\Notifications\Index as Notifications;
use App\Livewire\Social\CreateMovieNight;
use App\Livewire\Social\MovieNightPage;
use App\Livewire\Social\MovieNights;
use Illuminate\Support\Facades\Route;

Route::get('/', Home::class)->name('home');

Route::get('/movies/{id}', MoviePage::class)->whereUuid('id')->name('movies.show');

Route::get('/genres/{id}', GenrePage::class)->whereUuid('id')->name('genres.show');

Route::get('/people/{id}', PersonPage::class)->whereUuid('id')->name('people.show');

Route::get('/search', Search::class)->name('search');

Route::get('/cinemas',CinemaMap::class)->name('cinemas');

Route::get('/notifications', Notifications::class)->middleware('auth.jwt')->name('notifications');

Route::get('/profile', Profile::class)->middleware('auth.jwt')->name('profile');
Route::get('/profile/genres', FavoriteGenres::class)->middleware('auth.jwt')->name('profile.genres');

Route::get('/watchlist', Watchlist::class)->middleware('auth.jwt')->name('watchlist');
Route::get('/history', History::class)->middleware('auth.jwt')->name('history');

Route::middleware('auth.jwt')->group(function () {
    Route::get('/lists', MyLists::class)->name('lists.index');
    Route::get('/lists/create', ListForm::class)->name('lists.create');
    Route::get('/lists/{id}/edit', ListForm::class)->whereUuid('id')->name('lists.edit');
});
Route::get('/lists/{id}', ListPage::class)->whereUuid('id')->name('lists.show');

Route::middleware(['auth.jwt', 'admin.page'])->prefix('admin')->name('admin.')->group(function () {
    Route::get('/', Dashboard::class)->name('dashboard');
});

Route::get('/movie-nights', MovieNights::class)->middleware('auth.jwt')->name('movie-nights.index');
Route::get('/movie-nights/create', CreateMovieNight::class)->middleware('auth.jwt')->name('movie-nights.create');
Route::get('/movie-nights/{id}', MovieNightPage::class)->whereUuid('id')->middleware('auth.jwt')->name('movie-nights.show');

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
