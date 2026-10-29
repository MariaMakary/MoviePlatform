<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('movie_person', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('movie_id')->constrained('movies')->cascadeOnDelete();
            $table->foreignUuid('person_id')->constrained('people')->cascadeOnDelete();
            $table->string('role', 20);
            $table->string('character', 255)->nullable();
            $table->smallInteger('cast_order')->nullable();

            $table->unique(['movie_id', 'person_id', 'role']);
            $table->index(['movie_id', 'role', 'cast_order']);
            $table->index('person_id');
        });

        DB::statement("ALTER TABLE movie_person ADD CONSTRAINT movie_person_role_check CHECK (role IN ('cast', 'director'))");
    }

    public function down(): void
    {
        Schema::dropIfExists('movie_person');
    }
};
