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
        Schema::create('gift_codes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained('products')->onDelete('cascade');
            $table->decimal('value', 10, 2); // Value of the gift code (10, 25, 50, 100)
            $table->string('code')->unique(); // The actual gift card code
            $table->enum('status', ['available', 'sold', 'used'])->default('available');
            $table->unsignedBigInteger('order_id')->nullable(); // Will add FK later
            $table->timestamp('sold_at')->nullable();
            $table->timestamp('used_at')->nullable();
            $table->timestamps();
            
            $table->index(['product_id', 'status', 'value']);
            $table->index('status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('gift_codes');
    }
};
