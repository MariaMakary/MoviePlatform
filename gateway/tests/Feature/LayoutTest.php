<?php

namespace Tests\Feature;

use App\Livewire\Home;
use Livewire\Livewire;
use Tests\TestCase;

class LayoutTest extends TestCase
{
    public function test_home_page_uses_the_layout(): void
    {
        $this->get('/')
            ->assertOk()
            ->assertSee('<title>Home · MoviePlatform</title>', false)
            ->assertSee('aria-label="Main"', false)
            ->assertSee('Search movies, people…')
            ->assertSee('aria-label="Notifications"', false)
            ->assertSee('Log in');
    }

    public function test_home_page_is_rendered_by_livewire(): void
    {
        $this->get('/')->assertSeeLivewire(Home::class);

        Livewire::test(Home::class)->assertOk()->assertSee('Discover movies');
    }

    public function test_footer_shows_the_tmdb_attribution(): void
    {
        $this->get('/')
            ->assertSee('This product uses the TMDB API but is not endorsed or certified by TMDB.')
            ->assertSee('images/tmdb-logo.svg');

        $this->assertFileExists(public_path('images/tmdb-logo.svg'));
    }

    public function test_flash_messages_are_shown(): void
    {
        $this->withSession(['success' => 'Saved.', 'error' => 'Something went wrong.'])
            ->get('/')
            ->assertSee('Saved.')
            ->assertSee('Something went wrong.')
            ->assertSee('role="alert"', false);
    }
}
