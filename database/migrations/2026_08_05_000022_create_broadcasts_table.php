<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Just an audit log of past broadcasts — the actual delivery reuses the
// existing `notifications` table (one row per recipient, same as every
// other notification in the app) plus FcmMessagingService for push, so a
// broadcast recipient's in-app notification list looks identical to any
// other notification they'd normally get.
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('broadcasts', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->text('message');
            $table->string('account_type')->nullable();
            $table->string('service_type')->nullable();
            $table->unsignedInteger('recipient_count')->default(0);
            $table->unsignedBigInteger('sent_by')->nullable();
            $table->timestamps();

            $table->foreign('sent_by')->references('id')->on('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('broadcasts');
    }
};
