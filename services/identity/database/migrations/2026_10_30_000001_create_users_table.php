<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('users', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('name', 100);
            $table->string('email', 255)->unique();
            $table->timestamp('email_verified_at')->nullable();
            $table->string('password', 255)->nullable();
            $table->string('avatar', 255)->nullable();
            $table->text('bio')->nullable();
            $table->string('role', 20)->default('user');
            $table->boolean('is_blocked')->default(false);
            $table->timestamps();
        });

        DB::statement("ALTER TABLE users ADD CONSTRAINT users_role_check CHECK (role IN ('user', 'admin'))");
    }

    public function down(): void
    {
        Schema::dropIfExists('users');
    }
};
