<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Query\Expression;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('activities', function (Blueprint $table) {
            $table->uuid('id')->primary();
            // The same event never creates two feed entries.
            $table->uuid('event_id')->unique();
            $table->uuid('user_id');
            $table->string('type', 30);
            $table->uuid('subject_id');
            $table->uuid('movie_id')->nullable();
            $table->jsonb('data')->default(new Expression("'{}'::jsonb"));
            $table->timestamp('created_at');

            $table->index(['user_id', 'created_at']);
            $table->index('movie_id');
        });

        DB::statement("ALTER TABLE activities ADD CONSTRAINT activities_type_check CHECK (type IN ('rating', 'review', 'watched', 'list'))");
    }

    public function down(): void
    {
        Schema::dropIfExists('activities');
    }
};
