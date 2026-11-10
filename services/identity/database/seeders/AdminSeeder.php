<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use MoviePlatform\Common\Auth\Role;
use RuntimeException;

class AdminSeeder extends Seeder
{
    public const MIN_PASSWORD_LENGTH = 8;

    public function run(): void
    {
        $password = config('admin.password');

        if (! is_string($password) || strlen($password) < self::MIN_PASSWORD_LENGTH) {
            throw new RuntimeException('secrets/admin_password must hold a password of at least '.self::MIN_PASSWORD_LENGTH.' characters.');
        }

        $email = User::normalizeEmail(config('admin.email'));

        if (User::where('email', $email)->exists()) {
            $this->command?->info("{$email} already exists: nothing changed.");

            return;
        }

        $admin = new User(['name' => config('admin.name'), 'email' => $email, 'password' => $password]);
        $admin->role = Role::Admin;
        $admin->save();

        $this->command?->info("Admin {$email} created.");
    }
}
