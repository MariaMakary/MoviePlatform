<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('movie_genre', function (Blueprint $table) {
            $table->foreignUuid('movie_id')->constrained('movies')->cascadeOnDelete();
            $table->foreignUuid('genre_id')->constrained('genres')->cascadeOnDelete();

            $table->primary(['movie_id', 'genre_id']);
            $table->index('genre_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('movie_genre');
    }
};
