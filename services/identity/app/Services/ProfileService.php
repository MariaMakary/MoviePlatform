<?php

namespace App\Services;

use App\Integration\Events\UserGenresUpdated;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use MoviePlatform\Common\Integration\EventPublisher;

class ProfileService
{
    public const AVATAR_FOLDER = 'avatars';

    public function __construct(private EventPublisher $events) {}

    public function update(User $user, string $name, ?string $bio): User
    {
        $bio = trim($bio ?? '');

        $user->update(['name' => trim($name), 'bio' => $bio === '' ? null : $bio]);

        return $user->load('genres');
    }

    public function updateAvatar(User $user, UploadedFile $file): User
    {
        $fileName = Str::uuid().'.'.$file->guessExtension();
        $file->move(self::avatarDirectory(), $fileName);

        $previous = $user->avatar;
        $user->update(['avatar' => $this->avatarUrl().'/'.$fileName]);
        $this->deleteAvatarFile($previous);

        return $user->load('genres');
    }

    public function changePassword(User $user, ?string $currentPassword, string $password): void
    {
        if ($user->password === null) {
            throw ValidationException::withMessages(['current_password' => ["This account has no password yet. Use 'Forgot password' to set one."]]);
        }

        if (! Hash::check($currentPassword ?? '', $user->password)) {
            throw ValidationException::withMessages(['current_password' => ['The current password is incorrect.']]);
        }

        $user->update(['password' => $password]);
    }

    public function updateGenres(User $user, array $genreIds): User
    {
        DB::transaction(function () use ($user, $genreIds) {
            User::whereKey($user->id)->lockForUpdate()->first();

            $user->genres()->delete();
            $user->genres()->createMany(array_map(fn (string $id) => ['genre_id' => strtolower($id)], array_values($genreIds)));
        });

        $user->load('genres');

        $this->events->publish(UserGenresUpdated::ROUTING_KEY, UserGenresUpdated::data($user));

        return $user;
    }

    public static function avatarDirectory(): string
    {
        return base_path('public/'.self::AVATAR_FOLDER);
    }

    private function avatarUrl(): string
    {
        return rtrim(config('app.url'), '/').'/'.self::AVATAR_FOLDER;
    }

    private function deleteAvatarFile(?string $url): void
    {
        if ($url !== null && str_starts_with($url, $this->avatarUrl().'/')) {
            File::delete(self::avatarDirectory().'/'.basename($url));
        }
    }
}
