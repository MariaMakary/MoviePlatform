<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Query\Expression;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_taste_profiles', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('user_id')->unique();
            $table->jsonb('genre_weights')->default(new Expression("'{}'::jsonb"));
            $table->jsonb('people_weights')->default(new Expression("'{}'::jsonb"));
            $table->jsonb('seen_movie_ids')->default(new Expression("'{}'::jsonb"));
            $table->jsonb('favorite_genre_ids')->default(new Expression("'[]'::jsonb"));
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_taste_profiles');
    }
};
