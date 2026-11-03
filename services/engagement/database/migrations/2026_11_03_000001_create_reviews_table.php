<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reviews', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('user_id');
            $table->uuid('movie_id');
            $table->text('body');
            $table->string('sentiment_label', 20)->nullable();
            $table->decimal('sentiment_score', 5, 4)->nullable();
            $table->boolean('is_hidden')->default(false);
            $table->boolean('author_blocked')->default(false);
            $table->timestamps();

            $table->unique(['user_id', 'movie_id']);
            $table->index(['movie_id', 'created_at']);
        });

        DB::statement("ALTER TABLE reviews ADD CONSTRAINT reviews_sentiment_label_check CHECK (sentiment_label IN ('positive', 'neutral', 'negative'))");
    }

    public function down(): void
    {
        Schema::dropIfExists('reviews');
    }
};
