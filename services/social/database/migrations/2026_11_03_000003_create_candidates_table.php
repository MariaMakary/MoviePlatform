<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('candidates', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('movie_night_id')->constrained('movie_nights')->cascadeOnDelete();
            $table->uuid('movie_id');
            $table->uuid('added_by');
            $table->timestamp('created_at');

            $table->unique(['movie_night_id', 'movie_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('candidates');
    }
};
