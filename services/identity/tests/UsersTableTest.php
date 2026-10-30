<?php

namespace Tests;

use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Laravel\Lumen\Testing\DatabaseTransactions;
use MoviePlatform\Common\Auth\Role;

class UsersTableTest extends TestCase
{
    use DatabaseTransactions;

    private function assertRefused(callable $query, string $sqlState): void
    {
        try {
            DB::transaction($query);
            $this->fail("The database accepted a row it should refuse (expected SQLSTATE $sqlState).");
        } catch (QueryException $e) {
            $this->assertSame($sqlState, $e->getCode());
        }
    }

    public function test_users_columns_match_the_erd(): void
    {
        $columns = collect(DB::select(
            "select column_name, data_type, character_maximum_length, is_nullable, column_default from information_schema.columns
             where table_schema = current_schema() and table_name = 'users' order by ordinal_position"
        ))->mapWithKeys(fn ($c) => [$c->column_name => implode(' ', array_filter([
            $c->data_type,
            $c->character_maximum_length ? "({$c->character_maximum_length})" : null,
            $c->is_nullable === 'YES' ? 'NULL' : null,
            $c->column_default !== null ? 'default '.trim(Str::before($c->column_default, '::'), "'") : null,
        ]))])->all();

        $this->assertSame([
            'id' => 'uuid',
            'name' => 'character varying (100)',
            'email' => 'character varying (255)',
            'email_verified_at' => 'timestamp without time zone NULL',
            'password' => 'character varying (255) NULL',
            'avatar' => 'character varying (255) NULL',
            'bio' => 'text NULL',
            'role' => 'character varying (20) default user',
            'is_blocked' => 'boolean default false',
            'created_at' => 'timestamp without time zone NULL',
            'updated_at' => 'timestamp without time zone NULL',
        ], $columns);
    }

    public function test_email_is_unique(): void
    {
        User::factory()->create(['email' => 'maria@example.com']);

        $this->assertRefused(fn () => User::factory()->create(['email' => 'maria@example.com']), '23505');
    }

    public function test_an_unknown_role_is_refused_by_the_database(): void
    {
        $this->assertRefused(fn () => DB::table('users')->insert([
            'id' => (string) Str::orderedUuid(), 'name' => 'Maria', 'email' => 'maria@example.com', 'role' => 'moderator',
        ]), '23514');
    }

    public function test_role_is_read_as_the_role_enum(): void
    {
        $admin = User::factory()->admin()->create();

        $this->assertSame(Role::Admin, $admin->fresh()->role);
        $this->assertSame('admin', DB::table('users')->where('id', $admin->id)->value('role'));
    }

    public function test_database_defaults_for_a_user_created_without_them(): void
    {
        $id = (string) Str::orderedUuid();
        DB::table('users')->insert(['id' => $id, 'name' => 'Maria', 'email' => 'maria@example.com']);

        $user = User::find($id);
        $this->assertSame(Role::User, $user->role);
        $this->assertFalse($user->is_blocked);
        $this->assertNull($user->password);
    }

    public function test_a_new_user_gets_a_uuid_a_hashed_password_and_no_role_from_input(): void
    {
        $user = User::create([
            'name' => 'Maria',
            'email' => 'maria@example.com',
            'password' => 'secret-password',
            'role' => Role::Admin,
            'is_blocked' => true,
        ]);

        $this->assertTrue(Str::isUuid($user->id));
        $this->assertNotSame('secret-password', $user->password);
        $this->assertTrue(Hash::check('secret-password', $user->fresh()->password));
        $this->assertSame(Role::User, $user->fresh()->role);
        $this->assertFalse($user->fresh()->is_blocked);
        $this->assertArrayNotHasKey('password', $user->toArray());
    }

    public function test_a_social_login_user_has_no_password(): void
    {
        $user = User::factory()->create(['password' => null]);

        $this->assertNull($user->fresh()->password);
    }
}
