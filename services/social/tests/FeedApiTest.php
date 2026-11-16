<?php

namespace Tests;

use App\Models\Activity;
use App\Models\Follow;
use App\Models\PrivacySetting;
use Firebase\JWT\JWT;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Laravel\Lumen\Testing\DatabaseTransactions;

class FeedApiTest extends TestCase
{
    use DatabaseTransactions;

    private const ME = '0193a7c0-1111-7aaa-8bbb-000000000001';

    private const MARIA = '0193a7c0-2222-7aaa-8bbb-000000000002';

    private const JOE = '0193a7c0-3333-7aaa-8bbb-000000000003';

    private const STRANGER = '0193a7c0-4444-7aaa-8bbb-000000000004';

    private const MOVIE = '0193a7c1-3333-7aaa-8bbb-000000000100';

    protected function setUp(): void
    {
        parent::setUp();

        config(['jwt.public_key_path' => base_path('tests/Fixtures/jwt/test-public.pem')]);
    }

    private function auth(string $userId = self::ME): array
    {
        $token = JWT::encode(
            ['sub' => $userId, 'role' => 'user', 'name' => 'Tonio', 'exp' => time() + 1800],
            file_get_contents(base_path('tests/Fixtures/jwt/test-private.pem')),
            'RS256',
        );

        return ['Authorization' => "Bearer $token"];
    }

    private function follow(string $userId): void
    {
        Follow::query()->insert(['follower_id' => self::ME, 'following_id' => $userId, 'created_at' => Carbon::now()]);
    }

    private function activity(string $userId, string $type, string $at, array $data = []): Activity
    {
        return Activity::forceCreate([
            'event_id' => (string) Str::uuid(), 'user_id' => $userId, 'type' => $type, 'subject_id' => (string) Str::uuid(),
            'movie_id' => $type === 'list' ? null : self::MOVIE, 'data' => $data, 'created_at' => Carbon::parse($at),
        ]);
    }

    private function ids(): array
    {
        return array_column(json_decode($this->response->getContent(), true)['data'], 'id');
    }

    public function test_the_feed_needs_a_token(): void
    {
        $this->get('/api/feed')->seeStatusCode(401);
    }

    public function test_the_feed_shows_only_people_i_follow_newest_first(): void
    {
        $this->follow(self::MARIA);
        $this->follow(self::JOE);
        $old = $this->activity(self::MARIA, 'rating', '2026-11-16T09:00:00Z', ['score' => 5]);
        $new = $this->activity(self::JOE, 'review', '2026-11-16T11:00:00Z', ['excerpt' => 'Loved it.']);
        $this->activity(self::STRANGER, 'rating', '2026-11-16T12:00:00Z');
        $this->activity(self::ME, 'rating', '2026-11-16T12:30:00Z');

        $this->get('/api/feed', $this->auth())->seeStatusCode(200);

        $this->assertSame([$new->id, $old->id], $this->ids());
        $this->seeJson([
            'id' => $new->id, 'user_id' => self::JOE, 'type' => 'review', 'subject_id' => $new->subject_id,
            'movie_id' => self::MOVIE, 'data' => ['excerpt' => 'Loved it.'], 'created_at' => '2026-11-16T11:00:00Z',
        ]);
    }

    public function test_the_feed_respects_each_authors_privacy(): void
    {
        $this->follow(self::MARIA);
        PrivacySetting::create(['user_id' => self::MARIA, 'show_reviews' => true, 'show_ratings' => false, 'show_lists' => true, 'show_history' => false]);
        $shown = [
            $this->activity(self::MARIA, 'review', '2026-11-16T09:00:00Z'),
            $this->activity(self::MARIA, 'list', '2026-11-16T08:00:00Z', ['title' => 'Best of Nolan']),
        ];
        $this->activity(self::MARIA, 'rating', '2026-11-16T10:00:00Z');
        $this->activity(self::MARIA, 'watched', '2026-11-16T11:00:00Z');

        $this->get('/api/feed', $this->auth())->seeStatusCode(200);

        $this->assertSame(array_column($shown, 'id'), $this->ids());
    }

    public function test_the_feed_is_paginated(): void
    {
        $this->follow(self::MARIA);
        foreach (range(1, 5) as $hour) {
            $this->activity(self::MARIA, 'rating', "2026-11-16T0{$hour}:00:00Z");
        }

        $this->get('/api/feed?per_page=2&page=2', $this->auth())
            ->seeStatusCode(200)
            ->seeJson(['meta' => ['current_page' => 2, 'last_page' => 3, 'total' => 5]]);
    }

    public function test_a_users_activity_is_public_and_follows_their_privacy(): void
    {
        PrivacySetting::create(['user_id' => self::MARIA, 'show_reviews' => false, 'show_ratings' => true, 'show_lists' => true, 'show_history' => true]);
        $rating = $this->activity(self::MARIA, 'rating', '2026-11-16T10:00:00Z');
        $this->activity(self::MARIA, 'review', '2026-11-16T11:00:00Z');
        $this->activity(self::JOE, 'rating', '2026-11-16T12:00:00Z');

        $this->get('/api/users/'.self::MARIA.'/activity')->seeStatusCode(200);
        $this->assertSame([$rating->id], $this->ids(), 'Guest');

        $this->get('/api/users/'.self::MARIA.'/activity', $this->auth())->seeStatusCode(200);
        $this->assertSame([$rating->id], $this->ids(), 'Another user');
    }

    public function test_users_see_all_of_their_own_activity(): void
    {
        PrivacySetting::create(['user_id' => self::ME, 'show_reviews' => false, 'show_ratings' => false, 'show_lists' => false, 'show_history' => false]);
        $mine = [$this->activity(self::ME, 'review', '2026-11-16T11:00:00Z'), $this->activity(self::ME, 'rating', '2026-11-16T10:00:00Z')];

        $this->get('/api/users/'.self::ME.'/activity', $this->auth())->seeStatusCode(200);

        $this->assertSame(array_column($mine, 'id'), $this->ids());
    }

    public function test_without_saved_settings_everything_is_shown(): void
    {
        foreach (['rating', 'review', 'watched', 'list'] as $i => $type) {
            $this->activity(self::JOE, $type, "2026-11-16T1{$i}:00:00Z");
        }

        $this->get('/api/users/'.self::JOE.'/activity')->seeStatusCode(200);

        $this->assertCount(4, $this->ids());
    }

    public function test_a_malformed_user_id_gets_422(): void
    {
        $this->get('/api/users/not-a-uuid/activity')->seeStatusCode(422);

        $this->assertArrayHasKey('user_id', json_decode($this->response->getContent(), true)['errors']);
    }
}
