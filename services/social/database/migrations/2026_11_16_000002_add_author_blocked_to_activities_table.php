<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('activities', function (Blueprint $table) {
            // Set by user.blocked, cleared by user.unblocked: a blocked user's activity is hidden everywhere.
            $table->boolean('author_blocked')->default(false);
        });
    }

    public function down(): void
    {
        Schema::table('activities', function (Blueprint $table) {
            $table->dropColumn('author_blocked');
        });
    }
};
