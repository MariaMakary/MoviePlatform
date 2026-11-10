<?php

namespace Tests;

use App\Integration\Clients\TmdbClient;
use App\Integration\Clients\TmdbUnavailable;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

class TmdbListCacheTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config(['services.tmdb.key' => 'test-secret-key-123', 'services.tmdb.url' => 'https://api.themoviedb.org/3']);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    private function client(): TmdbClient
    {
        return app(TmdbClient::class);
    }

    private function list(string $title = 'Inception'): array
    {
        return [
            'page' => 1,
            'total_pages' => 1,
            'total_results' => 1,
            'results' => [['id' => 27205, 'title' => $title, 'genre_ids' => [28]]],
        ];
    }

    public function test_the_second_request_for_the_same_list_does_not_call_tmdb(): void
    {
        Http::fake(['*' => Http::response($this->list())]);

        $first = $this->client()->popular();
        $second = $this->client()->popular();

        $this->assertSame($first, $second);
        Http::assertSentCount(1);
        $this->assertTrue(Cache::tags(TmdbClient::CACHE_TAG)->has('tmdb:popular:page:1'));
    }

    public function test_upcoming_search_and_discover_are_cached_too(): void
    {
        Http::fake(['*' => Http::response($this->list())]);

        foreach (range(1, 2) as $_) {
            $this->client()->upcoming();
            $this->client()->search('Inception');
            $this->client()->discover(878, 2010);
        }

        Http::assertSentCount(3);
    }

    public function test_a_search_is_cached_whatever_the_case_and_spaces(): void
    {
        Http::fake(['*' => Http::response($this->list())]);

        $this->client()->search('Inception');
        $this->client()->search('  inception ');

        Http::assertSentCount(1);
    }

    public function test_different_pages_and_filters_are_cached_separately(): void
    {
        Http::fake(['*' => Http::response($this->list())]);

        $this->client()->popular(1);
        $this->client()->popular(2);
        $this->client()->search('inception');
        $this->client()->search('interstellar');
        $this->client()->discover(878);
        $this->client()->discover(878, 2010);
        $this->client()->discover();

        Http::assertSentCount(7);
    }

    public function test_a_list_is_asked_again_after_6_hours(): void
    {
        Http::fake(['*' => Http::response($this->list())]);

        $this->client()->popular();
        Carbon::setTestNow(Carbon::now()->addHours(TmdbClient::LIST_CACHE_HOURS)->subMinute());
        $this->client()->popular();
        Http::assertSentCount(1);

        Carbon::setTestNow(Carbon::now()->addMinutes(2));
        $this->client()->popular();
        Http::assertSentCount(2);
    }

    public function test_a_tmdb_failure_is_not_cached(): void
    {
        Http::fake(['*' => Http::sequence()
            ->push(['status_message' => 'Internal error.'], 500)
            ->push($this->list())]);

        try {
            $this->client()->popular();
            $this->fail('TmdbUnavailable was not thrown.');
        } catch (TmdbUnavailable) {
        }

        $this->assertSame('Inception', $this->client()->popular()['results'][0]['movie']['title']);
        Http::assertSentCount(2);
    }

    public function test_movie_details_are_not_cached(): void
    {
        Http::fake(['*' => Http::response(['id' => 27205, 'title' => 'Inception'])]);

        $this->client()->movie(27205);
        $this->client()->movie(27205);

        Http::assertSentCount(2);
    }

    public function test_catalog_clear_cache_empties_the_tmdb_lists(): void
    {
        Http::fake(['*' => Http::sequence()
            ->push($this->list('Before'))
            ->push($this->list('After'))]);
        Cache::put('catalog:person-synced:other', true, 60);

        $this->client()->popular();

        $this->assertSame(0, $this->artisan('catalog:clear-cache'));
        $this->assertFalse(Cache::tags(TmdbClient::CACHE_TAG)->has('tmdb:popular:page:1'));
        $this->assertTrue(Cache::has('catalog:person-synced:other'), 'Other cached values are kept.');

        $this->assertSame('After', $this->client()->popular()['results'][0]['movie']['title']);
        Http::assertSentCount(2);
    }
}
