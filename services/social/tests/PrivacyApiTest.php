<?php

namespace Tests;

use App\Models\PrivacySetting;
use Firebase\JWT\JWT;
use Illuminate\Support\Facades\DB;
use Laravel\Lumen\Testing\DatabaseTransactions;

class PrivacyApiTest extends TestCase
{
    use DatabaseTransactions;

    private const ME = '0193a7c0-1111-7aaa-8bbb-000000000001';

    private const MARIA = '0193a7c0-2222-7aaa-8bbb-000000000002';

    private const ALL_SHOWN = ['show_reviews' => true, 'show_ratings' => true, 'show_lists' => true, 'show_history' => true];

    protected function setUp(): void
    {
        parent::setUp();

        config(['jwt.public_key_path' => base_path('tests/Fixtures/jwt/test-public.pem')]);
    }

    private function auth(string $userId = self::ME): array
    {
        $token = JWT::encode(
            ['sub' => $userId, 'role' => 'user', 'name' => 'Joe Rohban', 'exp' => time() + 1800],
            file_get_contents(base_path('tests/Fixtures/jwt/test-private.pem')),
            'RS256',
        );

        return ['Authorization' => "Bearer $token"];
    }

    public function test_the_table_matches_the_erd(): void
    {
        $columns = collect(DB::select(
            "select column_name, data_type, is_nullable, column_default from information_schema.columns
             where table_schema = current_schema() and table_name = 'privacy_settings' order by ordinal_position"
        ))->mapWithKeys(fn ($c) => [$c->column_name => $c->data_type.($c->is_nullable === 'YES' ? ' NULL' : '').($c->column_default ? " = {$c->column_default}" : '')])->all();

        $this->assertSame([
            'user_id' => 'uuid',
            'show_reviews' => 'boolean = true',
            'show_ratings' => 'boolean = true',
            'show_lists' => 'boolean = true',
            'show_history' => 'boolean = true',
            'created_at' => 'timestamp without time zone NULL',
            'updated_at' => 'timestamp without time zone NULL',
        ], $columns);

        $indexes = collect(DB::select("select indexname from pg_indexes where tablename = 'privacy_settings'"))->pluck('indexname');
        $this->assertContains('privacy_settings_pkey', $indexes);
    }

    public function test_a_user_who_never_saved_gets_the_defaults_without_a_row(): void
    {
        $this->json('GET', '/api/privacy', [], $this->auth())
            ->seeStatusCode(200)
            ->seeJsonEquals(['data' => ['user_id' => self::ME, ...self::ALL_SHOWN]]);

        $this->assertSame(0, PrivacySetting::where('user_id', self::ME)->count());
    }

    public function test_saving_changes_the_settings(): void
    {
        $settings = ['show_reviews' => false, 'show_ratings' => true, 'show_lists' => false, 'show_history' => true];

        $this->json('PUT', '/api/privacy', $settings, $this->auth())
            ->seeStatusCode(200)
            ->seeJsonEquals(['data' => ['user_id' => self::ME, ...$settings]]);

        $this->json('GET', '/api/privacy', [], $this->auth())
            ->seeJsonEquals(['data' => ['user_id' => self::ME, ...$settings]]);

        // Saving again updates the same row.
        $this->json('PUT', '/api/privacy', self::ALL_SHOWN, $this->auth())->seeStatusCode(200);
        $this->assertSame(1, PrivacySetting::where('user_id', self::ME)->count());
        $this->assertTrue(PrivacySetting::find(self::ME)->show_reviews);
    }

    public function test_every_field_is_required_and_boolean(): void
    {
        $this->json('PUT', '/api/privacy', ['show_reviews' => 'maybe'], $this->auth())->seeStatusCode(422);

        $errors = json_decode($this->response->getContent(), true)['errors'];
        $this->assertSame(['show_reviews', 'show_ratings', 'show_lists', 'show_history'], array_keys($errors));
        $this->assertSame(0, PrivacySetting::count());
    }

    public function test_my_settings_need_a_token(): void
    {
        $this->json('GET', '/api/privacy')->seeStatusCode(401);
        $this->json('PUT', '/api/privacy', self::ALL_SHOWN)->seeStatusCode(401);
    }

    public function test_anyone_can_read_another_users_settings(): void
    {
        PrivacySetting::create(['user_id' => self::MARIA, 'show_history' => false]);

        $this->json('GET', '/api/users/'.self::MARIA.'/privacy')
            ->seeStatusCode(200)
            ->seeJsonEquals(['data' => ['user_id' => self::MARIA, ...self::ALL_SHOWN, 'show_history' => false]]);

        $this->json('GET', '/api/users/'.self::ME.'/privacy')
            ->seeStatusCode(200)
            ->seeJsonEquals(['data' => ['user_id' => self::ME, ...self::ALL_SHOWN]]);
    }

    public function test_another_users_id_must_be_a_uuid(): void
    {
        $this->json('GET', '/api/users/not-a-uuid/privacy')->seeStatusCode(422)->seeJsonStructure(['errors' => ['user_id']]);
    }
}
