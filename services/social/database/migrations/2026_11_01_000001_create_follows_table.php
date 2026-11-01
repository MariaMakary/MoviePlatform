<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('follows', function (Blueprint $table) {
            $table->uuid('follower_id');
            $table->uuid('following_id');
            $table->timestamp('created_at');

            $table->primary(['follower_id', 'following_id']);
            $table->index('following_id');
        });

        DB::statement('ALTER TABLE follows ADD CONSTRAINT follows_not_self_check CHECK (follower_id <> following_id)');
    }

    public function down(): void
    {
        Schema::dropIfExists('follows');
    }
};
