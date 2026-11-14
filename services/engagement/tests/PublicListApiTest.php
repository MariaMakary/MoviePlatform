<?php

namespace Tests;

use App\Models\ListItem;
use App\Models\MovieList;
use Illuminate\Support\Carbon;
use Laravel\Lumen\Testing\DatabaseTransactions;

class PublicListApiTest extends TestCase
{
    use DatabaseTransactions;

    // A made-up word, so lists already in the local engagement-db never match.
    private const WORD = 'Zorblaxian';

    private const AUTHOR = '0193a7c0-1111-7aaa-8bbb-000000000001';

    private function list(string $title, array $attributes = []): MovieList
    {
        return MovieList::forceCreate(array_merge([
            'user_id' => self::AUTHOR, 'title' => $title, 'description' => 'My picks.', 'is_public' => true,
        ], $attributes));
    }

    private function addMovies(MovieList $list, int $count): array
    {
        $ids = [];
        foreach (range(1, $count) as $i) {
            $ids[] = $movieId = sprintf('0193a7c1-3333-7aaa-8bbb-%012d', $i);
            ListItem::forceCreate(['list_id' => $list->id, 'movie_id' => $movieId, 'created_at' => Carbon::parse("2026-11-0{$i}T10:00:00Z")]);
        }

        return $ids;
    }

    private function search(array $query = []): array
    {
        $this->get('/api/lists/public?'.http_build_query($query + ['q' => self::WORD]));

        return json_decode($this->response->getContent(), true);
    }

    public function test_a_guest_gets_public_lists_with_their_card_fields(): void
    {
        Carbon::setTestNow('2026-11-14T18:00:00Z');
        $list = $this->list(self::WORD.' classics');
        $movies = $this->addMovies($list, 2);
        Carbon::setTestNow();

        $body = $this->search();

        $this->seeStatusCode(200);
        $this->assertSame([[
            'id' => $list->id, 'user_id' => self::AUTHOR, 'title' => self::WORD.' classics', 'description' => 'My picks.',
            'movie_count' => 2, 'first_movie_ids' => $movies,
            'created_at' => '2026-11-14T18:00:00Z', 'updated_at' => '2026-11-14T18:00:00Z',
        ]], $body['data']);
        $this->assertSame(['current_page' => 1, 'last_page' => 1, 'total' => 1], $body['meta']);
    }

    public function test_private_and_author_blocked_lists_never_appear(): void
    {
        $this->list(self::WORD.' public');
        $this->list(self::WORD.' private', ['is_public' => false]);
        $this->list(self::WORD.' blocked author', ['author_blocked' => true]);

        $this->assertSame([self::WORD.' public'], array_column($this->search()['data'], 'title'));
    }

    public function test_the_title_search_is_case_insensitive_and_literal(): void
    {
        $this->list('The best of '.self::WORD);
        $this->list(self::WORD.' 100% feel-good');
        $this->list(self::WORD.' 100 feel-good');

        $this->assertCount(3, $this->search(['q' => strtoupper(self::WORD)])['data']);
        $this->assertSame([self::WORD.' 100% feel-good'], array_column($this->search(['q' => self::WORD.' 100%'])['data'], 'title'));
    }

    public function test_newest_lists_come_first_and_pages_hold_20(): void
    {
        foreach (range(1, 22) as $i) {
            $this->list(self::WORD." $i", ['created_at' => Carbon::parse('2026-11-01T10:00:00Z')->addMinutes($i)]);
        }

        $first = $this->search();
        $this->assertSame([self::WORD.' 22', self::WORD.' 21'], array_slice(array_column($first['data'], 'title'), 0, 2));
        $this->assertSame(['current_page' => 1, 'last_page' => 2, 'total' => 22], $first['meta']);

        $second = $this->search(['page' => 2]);
        $this->assertSame([self::WORD.' 2', self::WORD.' 1'], array_column($second['data'], 'title'));
    }

    public function test_first_movie_ids_holds_at_most_4_in_list_order_and_the_count_holds_all(): void
    {
        $list = $this->list(self::WORD.' big');
        $movies = $this->addMovies($list, 6);
        $this->list(self::WORD.' empty', ['created_at' => Carbon::parse('2026-01-01T10:00:00Z')]);

        $body = $this->search();

        $this->assertSame(6, $body['data'][0]['movie_count']);
        $this->assertSame(array_slice($movies, 0, 4), $body['data'][0]['first_movie_ids']);
        $this->assertSame(0, $body['data'][1]['movie_count']);
        $this->assertSame([], $body['data'][1]['first_movie_ids']);
    }

    public function test_without_q_every_public_list_is_listed(): void
    {
        $this->list(self::WORD.' one');

        $this->get('/api/lists/public')->seeStatusCode(200);

        $this->assertContains(self::WORD.' one', array_column(json_decode($this->response->getContent(), true)['data'], 'title'));
    }

    public function test_a_too_long_search_gets_422(): void
    {
        $this->get('/api/lists/public?q='.str_repeat('a', 151))->seeStatusCode(422);

        $this->assertArrayHasKey('q', json_decode($this->response->getContent(), true)['errors']);
    }
}
