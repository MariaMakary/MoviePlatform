<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sentiment_results', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('review_id')->unique();
            $table->string('label', 20);
            $table->decimal('score', 5, 4);
            $table->timestamps();
        });

        DB::statement("ALTER TABLE sentiment_results ADD CONSTRAINT sentiment_results_label_check CHECK (label IN ('positive', 'neutral', 'negative'))");
    }

    public function down(): void
    {
        Schema::dropIfExists('sentiment_results');
    }
};
