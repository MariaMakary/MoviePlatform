<?php

namespace Tests\Feature;

use Tests\TestCase;

class ComponentsTest extends TestCase
{
    private array $movie = [
        'id' => '0193a7c1-3333-7aaa-8bbb-000000000100',
        'title' => 'Inception',
        'poster_path' => '/poster.jpg',
        'release_date' => '2010-07-16',
        'vote_average' => 8.4,
    ];

    public function test_movie_card_shows_poster_title_year_and_rating(): void
    {
        $this->blade('<x-movie-card :movie="$movie" href="/movies/1" />', ['movie' => $this->movie])
            ->assertSee('href="/movies/1"', false)
            ->assertSee('src="https://image.tmdb.org/t/p/w342/poster.jpg"', false)
            ->assertSee('Inception')
            ->assertSee('2010')
            ->assertSee('8.4');
    }

    public function test_movie_card_without_poster_rating_or_link(): void
    {
        $movie = ['title' => 'Local movie', 'poster_path' => null, 'release_date' => null, 'vote_average' => null];

        $this->blade('<x-movie-card :movie="$movie" />', ['movie' => $movie])
            ->assertSee('Local movie')
            ->assertDontSee('<img', false)
            ->assertDontSee('<a ', false)
            ->assertDontSee('TMDB rating');
    }

    public function test_movie_card_keeps_a_full_poster_url(): void
    {
        $movie = [...$this->movie, 'poster_path' => 'https://cdn.example.com/p.jpg'];

        $this->blade('<x-movie-card :movie="$movie" />', ['movie' => $movie])
            ->assertSee('src="https://cdn.example.com/p.jpg"', false);
    }

    public function test_rating_stars_fill_and_label(): void
    {
        $this->blade('<x-rating-stars :value="3.5" />')
            ->assertSee('aria-label="3.5 out of 5 stars"', false)
            ->assertSee('width: 70%', false);
    }

    public function test_rating_stars_are_clamped(): void
    {
        $this->blade('<x-rating-stars :value="9" />')->assertSee('aria-label="5 out of 5 stars"', false);
        $this->blade('<x-rating-stars :value="-1" />')->assertSee('aria-label="0 out of 5 stars"', false);
    }

    public function test_avatar_shows_initials_without_an_image(): void
    {
        $this->blade('<x-avatar name="Maria Makary" />')
            ->assertSee('aria-label="Maria Makary"', false)
            ->assertSee('MM');
    }

    public function test_avatar_shows_the_image(): void
    {
        $this->blade('<x-avatar name="Joy" src="https://example.com/a.png" />')
            ->assertSee('src="https://example.com/a.png"', false)
            ->assertSee('alt="Joy"', false);
    }

    public function test_button_renders_a_button_or_a_link(): void
    {
        $this->blade('<x-button type="submit">Save</x-button>')
            ->assertSee('<button type="submit"', false)
            ->assertSee('Save');

        $this->blade('<x-button href="/movies" variant="secondary">Browse</x-button>')
            ->assertSee('<a href="/movies"', false)
            ->assertSee('bg-cerulean', false);
    }

    public function test_modal_listens_for_its_name(): void
    {
        $this->blade('<x-modal name="confirm" title="Are you sure?">Body text</x-modal>')
            ->assertSee('role="dialog"', false)
            ->assertSee('Are you sure?')
            ->assertSee('Body text')
            ->assertSee("'confirm'", false);
    }

    public function test_empty_state_shows_title_description_and_action(): void
    {
        $this->blade('<x-empty-state title="No reviews yet" description="Be the first."><a href="/x">Write one</a></x-empty-state>')
            ->assertSee('No reviews yet')
            ->assertSee('Be the first.')
            ->assertSee('Write one');
    }

    public function test_service_unavailable_names_the_service(): void
    {
        $this->blade('<x-service-unavailable service="Catalog" />')
            ->assertSee('role="status"', false)
            ->assertSee("The Catalog service isn't responding.")
            ->assertDontSee('Try again</', false);

        $this->blade('<x-service-unavailable :retry="true" />')
            ->assertSee('wire:click="$refresh"', false);
    }

    public function test_components_page_is_available_locally(): void
    {
        $this->app['env'] = 'local';

        $this->get('/dev/components')
            ->assertOk()
            ->assertSee('<title>Components · MoviePlatform</title>', false)
            ->assertSee('Inception')
            ->assertSee('Your watchlist is empty');
    }

    public function test_components_page_is_hidden_outside_local(): void
    {
        $this->app['env'] = 'production';

        $this->get('/dev/components')->assertNotFound();
    }
}
