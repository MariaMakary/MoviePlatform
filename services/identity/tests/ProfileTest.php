<?php

namespace Tests;

use App\Models\User;
use App\Services\ProfileService;
use App\Services\TokenService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Laravel\Lumen\Testing\DatabaseTransactions;

class ProfileTest extends TestCase
{
    use DatabaseTransactions;

    private const PNG = 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNkYAAAAAYAAjCB0C8AAAAASUVORK5CYII=';
    private const JPEG = '/9j/4AAQSkZJRgABAQEASABIAAD/2wBDAP//////////////////////////////////////////////////////////////////////////////////////wgALCAABAAEBAREA/8QAFBABAAAAAAAAAAAAAAAAAAAAAP/aAAgBAQABPxA=';
    private const GIF = 'R0lGODlhAQABAIAAAAAAAP///yH5BAEAAAAALAAAAAABAAEAAAIBRAA7';

    private User $user;

    private array $uploaded = [];

    protected function setUp(): void
    {
        parent::setUp();

        $fixtures = base_path('tests/Fixtures/jwt');
        config([
            'jwt.private_key' => file_get_contents($fixtures.'/test-private.pem'),
            'jwt.public_key_path' => $fixtures.'/test-public.pem',
            'app.url' => 'http://localhost:8001',
        ]);

        $this->user = User::factory()->create(['name' => 'Maria Makary', 'email' => 'maria@example.com']);
    }

    protected function tearDown(): void
    {
        foreach ($this->uploaded as $path) {
            if (is_file($path)) {
                unlink($path);
            }
        }

        parent::tearDown();
    }

    private function headers(?User $user = null): array
    {
        $this->app['auth']->forgetGuards();

        return ['Authorization' => 'Bearer '.app(TokenService::class)->issue($user ?? $this->user)->token];
    }

    private function upload(UploadedFile $file, ?User $user = null): void
    {
        $this->app['auth']->forgetGuards();
        $token = app(TokenService::class)->issue($user ?? $this->user)->token;

        $this->call('POST', '/api/me/avatar', [], [], ['avatar' => $file], [
            'HTTP_AUTHORIZATION' => "Bearer $token",
            'HTTP_ACCEPT' => 'application/json',
        ]);

        $avatar = $this->response->getData(true)['data']['avatar'] ?? null;
        if ($avatar !== null) {
            $this->uploaded[] = $this->avatarPath($avatar);
        }
    }

    private function image(string $name, string $base64, int $paddingKilobytes = 0): UploadedFile
    {
        $path = tempnam(sys_get_temp_dir(), 'avatar');
        file_put_contents($path, base64_decode($base64).str_repeat("\0", $paddingKilobytes * 1024));
        $this->uploaded[] = $path;

        return new UploadedFile($path, $name, null, null, true);
    }

    private function avatarPath(string $url): string
    {
        return ProfileService::avatarDirectory().'/'.basename($url);
    }

    private function errors(): array
    {
        return $this->response->getData(true)['errors'] ?? [];
    }

    public function test_name_and_bio_are_updated(): void
    {
        $this->json('PUT', '/api/me', ['name' => '  Maria M.  ', 'bio' => 'Sci-fi first.'], $this->headers());

        $this->seeStatusCode(200)->seeJsonEquals(['data' => [
            'id' => $this->user->id, 'name' => 'Maria M.', 'email' => 'maria@example.com',
            'avatar' => null, 'role' => 'user', 'genre_ids' => [],
        ]]);
        $this->assertSame('Maria M.', $this->user->fresh()->name);
        $this->assertSame('Sci-fi first.', $this->user->fresh()->bio);

        $this->get("/api/users/{$this->user->id}")->seeJson(['name' => 'Maria M.', 'bio' => 'Sci-fi first.']);
    }

    public function test_a_missing_or_blank_bio_clears_it(): void
    {
        $this->user->update(['bio' => 'Old bio']);

        $this->json('PUT', '/api/me', ['name' => 'Maria', 'bio' => '   '], $this->headers());
        $this->seeStatusCode(200);
        $this->assertNull($this->user->fresh()->bio);

        $this->user->update(['bio' => 'Old bio']);

        $this->json('PUT', '/api/me', ['name' => 'Maria'], $this->headers());
        $this->seeStatusCode(200);
        $this->assertNull($this->user->fresh()->bio);
    }

    public function test_the_profile_fields_are_validated(): void
    {
        $this->json('PUT', '/api/me', ['name' => '   ', 'bio' => 'Old'], $this->headers());
        $this->seeStatusCode(422);
        $this->assertArrayHasKey('name', $this->errors());

        $this->json('PUT', '/api/me', ['name' => str_repeat('a', 101), 'bio' => str_repeat('b', 501)], $this->headers());
        $this->seeStatusCode(422);
        $this->assertSame(['name', 'bio'], array_keys($this->errors()));

        $this->json('PUT', '/api/me', ['name' => str_repeat('a', 100), 'bio' => str_repeat('b', 500)], $this->headers());
        $this->seeStatusCode(200);
    }

    public function test_email_and_role_cannot_be_changed_here(): void
    {
        $this->json('PUT', '/api/me', ['name' => 'Maria', 'email' => 'other@example.com', 'role' => 'admin', 'is_blocked' => true], $this->headers());

        $this->seeStatusCode(200);
        $user = $this->user->fresh();
        $this->assertSame('maria@example.com', $user->email);
        $this->assertSame('user', $user->role->value);
        $this->assertFalse($user->is_blocked);
    }

    public function test_a_png_avatar_is_saved_and_served_from_public(): void
    {
        $this->upload($this->image('me.png', self::PNG));

        $this->seeStatusCode(200);
        $url = $this->response->getData(true)['data']['avatar'];
        $this->assertMatchesRegularExpression('~^http://localhost:8001/avatars/[0-9a-f-]{36}\.png$~', $url);
        $this->assertSame($url, $this->user->fresh()->avatar);
        $this->assertFileExists($this->avatarPath($url));
        $this->assertSame(base_path('public/avatars'), dirname($this->avatarPath($url)));
    }

    public function test_a_jpg_avatar_is_saved_with_a_jpg_extension(): void
    {
        $this->upload($this->image('me.jpeg', self::JPEG));

        $this->seeStatusCode(200);
        $this->assertStringEndsWith('.jpg', $this->user->fresh()->avatar);
    }

    public function test_a_new_avatar_deletes_the_previous_file(): void
    {
        $this->upload($this->image('first.png', self::PNG));
        $first = $this->user->fresh()->avatar;

        $this->upload($this->image('second.png', self::PNG));
        $second = $this->user->fresh()->avatar;

        $this->assertNotSame($first, $second);
        $this->assertFileDoesNotExist($this->avatarPath($first));
        $this->assertFileExists($this->avatarPath($second));
    }

    public function test_an_external_avatar_is_replaced_without_deleting_any_file(): void
    {
        $this->user->update(['avatar' => 'https://lh3.googleusercontent.com/a/photo.png']);
        $other = User::factory()->create();
        $this->upload($this->image('other.png', self::PNG), $other);
        $otherFile = $this->avatarPath($other->fresh()->avatar);

        $this->user->update(['avatar' => 'https://lh3.googleusercontent.com/a/'.basename($otherFile)]);
        $this->upload($this->image('me.png', self::PNG));

        $this->seeStatusCode(200);
        $this->assertFileExists($otherFile);
    }

    public function test_a_gif_is_refused(): void
    {
        $this->upload($this->image('me.gif', self::GIF));

        $this->seeStatusCode(422);
        $this->assertArrayHasKey('avatar', $this->errors());
        $this->assertNull($this->user->fresh()->avatar);
    }

    public function test_a_gif_renamed_to_png_is_refused(): void
    {
        $this->upload($this->image('me.png', self::GIF));

        $this->seeStatusCode(422);
        $this->assertArrayHasKey('avatar', $this->errors());
    }

    public function test_a_file_over_2_mb_is_refused(): void
    {
        $this->upload($this->image('big.png', self::PNG, 2049));

        $this->seeStatusCode(422);
        $this->assertArrayHasKey('avatar', $this->errors());
        $this->assertNull($this->user->fresh()->avatar);
    }

    public function test_the_avatar_is_required(): void
    {
        $this->json('POST', '/api/me/avatar', [], $this->headers());

        $this->seeStatusCode(422);
        $this->assertArrayHasKey('avatar', $this->errors());
    }

    public function test_the_password_is_changed(): void
    {
        $this->json('PUT', '/api/me/password', [
            'current_password' => 'password', 'password' => 'new-password', 'password_confirmation' => 'new-password',
        ], $this->headers());

        $this->seeStatusCode(204);
        $this->assertTrue(Hash::check('new-password', $this->user->fresh()->password));

        $this->json('POST', '/api/login', ['email' => 'maria@example.com', 'password' => 'new-password'])->seeStatusCode(200);
        $this->json('POST', '/api/login', ['email' => 'maria@example.com', 'password' => 'password'])->seeStatusCode(401);
    }

    public function test_a_wrong_or_missing_current_password_gets_422(): void
    {
        foreach (['wrong-password', '', null] as $current) {
            $this->json('PUT', '/api/me/password', [
                'current_password' => $current, 'password' => 'new-password', 'password_confirmation' => 'new-password',
            ], $this->headers());

            $this->seeStatusCode(422)->seeJsonEquals([
                'message' => 'The given data was invalid.',
                'errors' => ['current_password' => ['The current password is incorrect.']],
            ]);
        }

        $this->assertTrue(Hash::check('password', $this->user->fresh()->password));
    }

    public function test_the_new_password_is_validated(): void
    {
        $this->json('PUT', '/api/me/password', [
            'current_password' => 'password', 'password' => 'short', 'password_confirmation' => 'other',
        ], $this->headers());

        $this->seeStatusCode(422);
        $this->assertArrayHasKey('password', $this->errors());
        $this->assertTrue(Hash::check('password', $this->user->fresh()->password));
    }

    public function test_an_account_without_a_password_is_told_to_use_forgot_password(): void
    {
        $social = User::factory()->create(['password' => null]);

        $this->json('PUT', '/api/me/password', [
            'current_password' => 'anything', 'password' => 'new-password', 'password_confirmation' => 'new-password',
        ], $this->headers($social));

        $this->seeStatusCode(422)->seeJsonEquals([
            'message' => 'The given data was invalid.',
            'errors' => ['current_password' => ["This account has no password yet. Use 'Forgot password' to set one."]],
        ]);
        $this->assertNull($social->fresh()->password);
    }

    public function test_guests_get_401_and_blocked_users_get_403(): void
    {
        $this->json('PUT', '/api/me', ['name' => 'Maria'])->seeStatusCode(401);
        $this->json('PUT', '/api/me/password', [])->seeStatusCode(401);
        $this->json('POST', '/api/me/avatar', [])->seeStatusCode(401);

        $blocked = User::factory()->blocked()->create(['name' => 'Blocked']);

        $this->json('PUT', '/api/me', ['name' => 'Renamed'], $this->headers($blocked))->seeStatusCode(403);
        $this->assertSame('Blocked', $blocked->fresh()->name);

        $this->upload($this->image('me.png', self::PNG), $blocked);
        $this->seeStatusCode(403);
    }
}
