<?php

namespace Tests;

use App\Models\UserTasteProfile;
use Database\Seeders\DemoSeeder;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Laravel\Lumen\Testing\DatabaseTransactions;
use RuntimeException;

class DemoSeederTest extends TestCase
{
    use DatabaseTransactions;

    private const LINA = '0193a7c0-de00-7aaa-8bbb-000000000001';

    private const SCI_FI = '0193a7b0-de00-7aaa-8bbb-000000000878';

    private const INCEPTION = '0193a7c1-de00-7aaa-8bbb-000000000101';

    private const INTERSTELLAR = '0193a7c1-de00-7aaa-8bbb-000000000103';

    private const MATRIX = '0193a7c1-de00-7aaa-8bbb-000000000108';

    protected function setUp(): void
    {
        parent::setUp();

        config(['internal.services.catalog' => 'http://catalog.test', 'internal.service_token' => 'test-service-token']);
    }

    private function fakeCatalog(): void
    {
        Http::fake(function (Request $request) {
            $path = parse_url($request->url(), PHP_URL_PATH);

            return match (true) {
                str_ends_with($path, '/features') => Http::response(['data' => ['genre_ids' => [self::SCI_FI], 'cast_ids' => [], 'director_ids' => []]]),
                $path === '/api/genres' => Http::response(['data' => [['id' => self::SCI_FI, 'name' => 'Science Fiction']]]),
                default => Http::response(['data' => []]),
            };
        });
    }

    public function test_it_builds_the_taste_profiles_and_sentiment_and_can_run_twice(): void
    {
        $this->fakeCatalog();

        $this->app->make(DemoSeeder::class)->run();
        $this->app->make(DemoSeeder::class)->run();

        $this->assertSame(5, UserTasteProfile::where('user_id', 'like', '0193a7c0-de00-%')->count());
        $lina = UserTasteProfile::where('user_id', self::LINA)->firstOrFail();
        $this->assertEqualsCanonicalizing([self::INCEPTION => 5, self::INTERSTELLAR => 5, self::MATRIX => 4], $lina->seen_movie_ids);
        $this->assertCount(3, $lina->favorite_genre_ids);
        // Each movie: +1 watched, then score - 3 for the rating: 3 + 3 + 2.
        $this->assertSame([self::SCI_FI => 8], $lina->genre_weights);
        $this->assertSame(4, DB::table('sentiment_results')->where('review_id', 'like', '0193a7d1-de00-%')->count());
    }

    public function test_it_says_to_start_catalog_when_catalog_is_down(): void
    {
        Http::fake(fn () => Http::response(['message' => 'Server error.'], 500));

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Catalog is not answering');

        $this->app->make(DemoSeeder::class)->run();
    }
}
