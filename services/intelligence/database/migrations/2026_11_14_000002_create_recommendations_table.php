<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('recommendations', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('user_id');
            $table->uuid('movie_id');
            $table->decimal('score', 5, 4);
            $table->text('reason');
            $table->timestamps();

            // Recomputing replaces rows instead of adding duplicates.
            $table->unique(['user_id', 'movie_id']);
            $table->index(['user_id', 'score']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('recommendations');
    }
};
