<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('movies', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->integer('tmdb_id')->nullable()->unique();
            $table->string('title', 255);
            $table->text('overview')->nullable();
            $table->string('poster_path', 255)->nullable();
            $table->string('backdrop_path', 255)->nullable();
            $table->date('release_date')->nullable();
            $table->smallInteger('runtime')->nullable();
            $table->decimal('vote_average', 3, 1)->nullable();
            $table->double('popularity')->default(0);
            $table->string('trailer_key', 32)->nullable();
            $table->boolean('is_edited')->default(false);
            $table->timestamp('synced_at')->nullable();
            $table->timestamps();

            $table->fullText('title');
            $table->index('release_date');
            $table->index('popularity');
            $table->index('vote_average');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('movies');
    }
};
