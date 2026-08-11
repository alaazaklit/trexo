<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('school_bus_subscriptions', function (Blueprint $table) {
            $table->engine = 'InnoDB';
            $table->id();
            $table->foreignId('route_id')->constrained('school_bus_routes')->onDelete('cascade');
            // Denormalized off route_id so the driver's own subscription
            // queries don't need a join through school_bus_routes.
            $table->foreignId('driver_id')->constrained('drivers')->onDelete('cascade');
            $table->foreignId('parent_user_id')->constrained('users')->onDelete('cascade');
            $table->string('student_name');
            $table->string('parent_name');
            $table->string('phone');
            $table->text('address');
            $table->text('notes')->nullable();
            // "active" (not "approved") to match the spec's own wording for
            // an accepted request.
            $table->enum('status', ['pending', 'active', 'rejected'])->default('pending');
            $table->text('rejection_reason')->nullable();
            $table->timestamp('accepted_at')->nullable();
            $table->timestamps();

            $table->index(['driver_id', 'status']);
            $table->index(['parent_user_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('school_bus_subscriptions');
    }
};
