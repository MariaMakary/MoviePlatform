<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('movie_nights', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('host_id');
            $table->string('title', 100);
            $table->text('description')->nullable();
            $table->timestamp('scheduled_at');
            $table->string('location', 255)->nullable();
            $table->string('meeting_link', 255)->nullable();
            $table->string('status', 20)->default('open');
            $table->uuid('winner_movie_id')->nullable();
            $table->timestamps();
        });

        DB::statement("ALTER TABLE movie_nights ADD CONSTRAINT movie_nights_status_check CHECK (status IN ('open', 'finalized', 'cancelled'))");
    }

    public function down(): void
    {
        Schema::dropIfExists('movie_nights');
    }
};
