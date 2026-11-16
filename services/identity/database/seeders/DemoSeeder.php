<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * The demo users of seeds/demo.json (task 1289), with their favourite genres. Same ids in every service.
 * Running it again replaces the demo users and leaves everyone else alone. Never in production.
 */
class DemoSeeder extends Seeder
{
    public function run(): void
    {
        if (app()->environment('production')) {
            throw new RuntimeException('The demo data is never seeded in production.');
        }

        $demo = json_decode(file_get_contents(base_path('../../seeds/demo.json')), true, flags: JSON_THROW_ON_ERROR);

        DB::transaction(function () use ($demo) {
            User::whereIn('id', array_column($demo['users'], 'id'))->delete();

            foreach ($demo['users'] as $row) {
                $taken = User::where('email', User::normalizeEmail($row['email']))->exists();

                if ($taken) {
                    throw new RuntimeException("{$row['email']} already belongs to another account: run on fresh databases (README, Demo data).");
                }

                $user = new User(['name' => $row['name'], 'email' => $row['email'], 'password' => $demo['password'], 'bio' => $row['bio']]);
                $user->id = $row['id'];
                $user->email_verified_at = $user->created_at = $user->updated_at = Carbon::parse($row['registered_at']);
                $user->save();

                foreach ($row['genres'] as $genre) {
                    DB::table('user_genres')->insert([
                        'user_id' => $user->id,
                        'genre_id' => $demo['genres'][$genre]['id'],
                        'created_at' => $user->created_at,
                    ]);
                }
            }
        });

        $this->command?->info(count($demo['users']).' demo users (password: '.$demo['password'].').');
    }
}
