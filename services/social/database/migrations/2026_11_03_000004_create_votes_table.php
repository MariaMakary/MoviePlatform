<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('votes', function (Blueprint $table) {
            $table->foreignUuid('movie_night_id')->constrained('movie_nights')->cascadeOnDelete();
            $table->uuid('user_id');
            $table->foreignUuid('candidate_id')->constrained('candidates')->cascadeOnDelete();
            $table->timestamps();

            $table->primary(['movie_night_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('votes');
    }
};
