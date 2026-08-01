<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('refresh_tokens', function (Blueprint $table) {
            $table->id();
            // Not a real foreign key: the users table is MyISAM (legacy
            // Voyager setup), which can't be an FK target at all — indexed
            // instead, enforced only at the application layer.
            $table->unsignedBigInteger('user_id');
            // Only the SHA-256 hash is stored — the raw token is returned to
            // the client once (at issue time) and never persisted, so a
            // database leak alone can't be used to impersonate a session.
            $table->string('token_hash', 64)->unique();
            $table->timestamp('expires_at');
            $table->timestamp('revoked_at')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'revoked_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('refresh_tokens');
    }
};
