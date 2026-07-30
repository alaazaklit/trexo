<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('ratings', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id'); // Assuming a foreign key to users table
            $table->unsignedBigInteger('product_id'); // Assuming a foreign key to a products table
            $table->tinyInteger('rating'); // Rating value, e.g., 1-5
            $table->text('review')->nullable(); // Optional review text
            $table->timestamps();
    
            // Add foreign key constraints if needed
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
            $table->foreign('product_id')->references('id')->on('products')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ratings');
    }
};
