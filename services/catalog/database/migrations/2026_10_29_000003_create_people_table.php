<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('people', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->integer('tmdb_id')->nullable()->unique();
            $table->string('name', 255);
            $table->string('profile_path', 255)->nullable();
            $table->text('biography')->nullable();
            $table->date('birthday')->nullable();
            $table->string('known_for', 50)->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('people');
    }
};
