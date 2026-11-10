<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_genres', function (Blueprint $table) {
            $table->foreignUuid('user_id')->constrained('users')->cascadeOnDelete();
            $table->uuid('genre_id');
            $table->timestamp('created_at')->nullable();

            $table->primary(['user_id', 'genre_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_genres');
    }
};
