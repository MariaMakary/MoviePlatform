<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('participants', function (Blueprint $table) {
            $table->foreignUuid('movie_night_id')->constrained('movie_nights')->cascadeOnDelete();
            $table->uuid('user_id');
            $table->string('status', 20)->default('pending');
            $table->timestamp('responded_at')->nullable();
            $table->timestamp('created_at');

            $table->primary(['movie_night_id', 'user_id']);
        });

        DB::statement("ALTER TABLE participants ADD CONSTRAINT participants_status_check CHECK (status IN ('pending', 'accepted', 'declined'))");
    }

    public function down(): void
    {
        Schema::dropIfExists('participants');
    }
};
