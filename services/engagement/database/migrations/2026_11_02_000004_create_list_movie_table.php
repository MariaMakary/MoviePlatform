<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('list_movie', function (Blueprint $table) {
            $table->foreignUuid('list_id')->constrained('lists')->cascadeOnDelete();
            $table->uuid('movie_id');
            $table->timestamps();

            $table->primary(['list_id', 'movie_id']);
            $table->index('movie_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('list_movie');
    }
};
