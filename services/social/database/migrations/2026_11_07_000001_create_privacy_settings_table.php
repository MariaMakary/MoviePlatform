<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('privacy_settings', function (Blueprint $table) {
            $table->uuid('user_id')->primary();
            $table->boolean('show_reviews')->default(true);
            $table->boolean('show_ratings')->default(true);
            $table->boolean('show_lists')->default(true);
            $table->boolean('show_history')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('privacy_settings');
    }
};
